#!/usr/bin/env python3
"""Download Moscow and Moscow Oblast Orthodox places in small cached Overpass tiles.

This module reuses filtering and JSON conversion from
``fetch_moscow_region_churches.py`` but avoids one large administrative-area
query. Successful tile responses are cached, so an interrupted run resumes
without downloading completed tiles again. A tile that still receives 504 is
automatically divided into four smaller tiles.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import sys
import time
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Iterable

import fetch_moscow_region_churches as base

ENDPOINT = "https://overpass.private.coffee/api/interpreter"

# Broad bounding boxes. The Overpass query additionally intersects every tile
# with the administrative area, so objects outside the selected region are not
# included.
REGIONS = (
    ("RU-MOW", "Москва", (55.45, 37.10, 56.05, 38.10)),
    ("RU-MOS", "Московская область", (54.10, 35.00, 57.10, 40.40)),
)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Скачать православные храмы Москвы и Московской области "
            "небольшими запросами через Overpass Private Coffee."
        )
    )
    parser.add_argument(
        "--output",
        default="database/seeders/data/moscow-region-orthodox-places.json",
        help="Итоговый JSON-файл Laravel seeder.",
    )
    parser.add_argument(
        "--cache-dir",
        default="storage/app/osm-overpass-cache",
        help="Каталог ответов отдельных квадратов.",
    )
    parser.add_argument(
        "--tile-size",
        type=float,
        default=0.5,
        help="Начальный размер квадрата в градусах (по умолчанию 0.5).",
    )
    parser.add_argument(
        "--min-tile-size",
        type=float,
        default=0.0625,
        help="Минимальный размер после автоматического деления квадрата.",
    )
    parser.add_argument(
        "--timeout",
        type=int,
        default=300,
        help="HTTP-таймаут одного запроса в секундах.",
    )
    parser.add_argument(
        "--retries",
        type=int,
        default=2,
        help="Попыток для одного размера квадрата до его деления.",
    )
    parser.add_argument(
        "--request-delay",
        type=float,
        default=2.0,
        help="Пауза после успешного запроса в секундах.",
    )
    parser.add_argument(
        "--clear-cache",
        action="store_true",
        help="Удалить ранее сохранённые ответы и скачать всё заново.",
    )
    return parser.parse_args()


def frange(start: float, stop: float, step: float) -> Iterable[float]:
    value = start
    while value < stop - 1e-9:
        yield value
        value += step


def initial_tiles(
    bounds: tuple[float, float, float, float], tile_size: float
) -> list[tuple[float, float, float, float]]:
    south, west, north, east = bounds
    result: list[tuple[float, float, float, float]] = []
    for tile_south in frange(south, north, tile_size):
        for tile_west in frange(west, east, tile_size):
            result.append(
                (
                    tile_south,
                    tile_west,
                    min(tile_south + tile_size, north),
                    min(tile_west + tile_size, east),
                )
            )
    return result


def build_tile_query(
    region_iso: str, bounds: tuple[float, float, float, float]
) -> str:
    south, west, north, east = bounds
    bbox = f"{south:.7f},{west:.7f},{north:.7f},{east:.7f}"
    return f'''[out:json][timeout:240][maxsize:536870912];
area["ISO3166-2"="{region_iso}"]["boundary"="administrative"]->.region;
(
  nwr(area.region)({bbox})["name"]["amenity"="place_of_worship"];
  nwr(area.region)({bbox})["name"]["amenity"="monastery"];
  nwr(area.region)({bbox})["name"]["building"~"^(church|cathedral|chapel|monastery)$"];
);
out center tags qt;'''


def tile_key(
    region_iso: str, bounds: tuple[float, float, float, float]
) -> str:
    raw = region_iso + ":" + ":".join(f"{value:.7f}" for value in bounds)
    digest = hashlib.sha1(raw.encode("utf-8")).hexdigest()[:16]
    return f"{region_iso.lower()}-{digest}.json"


def load_cached(path: Path) -> dict[str, Any] | None:
    if not path.is_file():
        return None
    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return None
    return payload if isinstance(payload, dict) and "elements" in payload else None


def split_tile(
    bounds: tuple[float, float, float, float]
) -> list[tuple[float, float, float, float]]:
    south, west, north, east = bounds
    middle_lat = (south + north) / 2
    middle_lon = (west + east) / 2
    return [
        (south, west, middle_lat, middle_lon),
        (south, middle_lon, middle_lat, east),
        (middle_lat, west, north, middle_lon),
        (middle_lat, middle_lon, north, east),
    ]


def tile_span(bounds: tuple[float, float, float, float]) -> float:
    south, west, north, east = bounds
    return max(north - south, east - west)


def download_tile(
    *,
    region_iso: str,
    region_name: str,
    bounds: tuple[float, float, float, float],
    cache_dir: Path,
    timeout: int,
    retries: int,
    delay: float,
    min_tile_size: float,
    level: int = 0,
) -> list[dict[str, Any]]:
    cache_path = cache_dir / tile_key(region_iso, bounds)
    cached = load_cached(cache_path)
    indent = "  " * level

    if cached is not None:
        print(
            f"{indent}[{region_iso}] кэш {cache_path.name}: "
            f"{len(cached.get('elements', []))} элементов",
            flush=True,
        )
        return list(cached.get("elements", []))

    south, west, north, east = bounds
    label = (
        f"{region_iso} {south:.4f},{west:.4f}–{north:.4f},{east:.4f}"
    )

    try:
        payload = base.request_overpass(
            build_tile_query(region_iso, bounds),
            timeout=timeout,
            retries=retries,
            request_label=label,
        )
    except base.ImportErrorWithContext as exc:
        if tile_span(bounds) <= min_tile_size + 1e-9:
            raise base.ImportErrorWithContext(
                f"Минимальный квадрат {label} также не загрузился: {exc}"
            ) from exc

        print(
            f"{indent}[{region_iso}] 504/тайм-аут — делю квадрат на 4 части",
            file=sys.stderr,
            flush=True,
        )
        elements: list[dict[str, Any]] = []
        for child in split_tile(bounds):
            elements.extend(
                download_tile(
                    region_iso=region_iso,
                    region_name=region_name,
                    bounds=child,
                    cache_dir=cache_dir,
                    timeout=timeout,
                    retries=retries,
                    delay=delay,
                    min_tile_size=min_tile_size,
                    level=level + 1,
                )
            )
        return elements

    cache_path.parent.mkdir(parents=True, exist_ok=True)
    cache_path.write_text(
        json.dumps(payload, ensure_ascii=False),
        encoding="utf-8",
    )
    element_count = len(payload.get("elements", []))
    print(f"{indent}[{region_iso}] сохранено: {element_count}", flush=True)
    time.sleep(max(0.0, delay))
    return list(payload.get("elements", []))


def main() -> int:
    args = parse_args()
    base.OVERPASS_ENDPOINTS = (ENDPOINT,)

    if args.tile_size <= 0 or args.min_tile_size <= 0:
        raise SystemExit("Размер квадрата должен быть больше нуля.")
    if args.min_tile_size > args.tile_size:
        raise SystemExit("--min-tile-size не может быть больше --tile-size.")

    output_path = Path(args.output)
    cache_dir = Path(args.cache_dir)

    if args.clear_cache and cache_dir.exists():
        for path in cache_dir.glob("*.json"):
            path.unlink()
        print(f"Кэш очищен: {cache_dir}")

    all_items: list[dict[str, Any]] = []
    skipped = Counter()
    raw_counts: dict[str, int] = {}

    for region_iso, region_name, bounds in REGIONS:
        tiles = initial_tiles(bounds, args.tile_size)
        print(f"\n{region_name}: начальных квадратов {len(tiles)}", flush=True)
        region_elements: list[dict[str, Any]] = []

        for index, tile in enumerate(tiles, start=1):
            print(
                f"[{region_iso}] квадрат {index}/{len(tiles)}",
                flush=True,
            )
            region_elements.extend(
                download_tile(
                    region_iso=region_iso,
                    region_name=region_name,
                    bounds=tile,
                    cache_dir=cache_dir,
                    timeout=args.timeout,
                    retries=args.retries,
                    delay=args.request_delay,
                    min_tile_size=args.min_tile_size,
                )
            )

        raw_counts[region_iso] = len(region_elements)
        for element in region_elements:
            item, reason = base.element_to_item(element, region_name)
            if item is not None:
                all_items.append(item)
            elif reason:
                skipped[reason] += 1

    objects, nearby_duplicates = base.deduplicate(all_items)
    skipped["nearby_duplicates"] += nearby_duplicates
    type_counts = Counter(item["type"] for item in objects)
    region_counts = Counter(item["region"] for item in objects)

    snapshot = {
        "meta": {
            "generated_at": datetime.now(timezone.utc).isoformat(),
            "source": "OpenStreetMap contributors via Overpass API",
            "endpoint": ENDPOINT,
            "license": "ODbL 1.0",
            "regions": [region[0] for region in REGIONS],
            "raw_counts": raw_counts,
            "imported_count": len(objects),
            "type_counts": dict(type_counts),
            "region_counts": dict(region_counts),
            "skipped": dict(skipped),
            "notice": (
                "Это снимок OpenStreetMap, а не официальный реестр. "
                "Записи требуют редакционной проверки."
            ),
        },
        "objects": objects,
    }

    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(
        json.dumps(snapshot, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )

    print("\nГотово")
    print(f"Файл: {output_path}")
    print(f"Всего объектов: {len(objects)}")
    print(f"По типам: {dict(type_counts)}")
    print(f"По регионам: {dict(region_counts)}")
    print(f"Пропущено: {dict(skipped)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
