#!/usr/bin/env python3
"""Create Laravel seed JSON from a local OpenStreetMap PBF extract.

This importer does not use Overpass. Download a Moscow + Moscow Oblast extract
in .osm.pbf format, then run this script locally.

Example:
    python scripts/fetch_moscow_region_churches_from_pbf.py storage/app/moscow-region.osm.pbf

Requires:
    pip install osmium
"""

from __future__ import annotations

import argparse
import json
import sys
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Iterable

try:
    import osmium
except ImportError as exc:  # pragma: no cover - user-facing dependency check
    raise SystemExit(
        "Не установлен пакет osmium. Выполните:\n"
        "C:\\Python311\\python.exe -m pip install osmium"
    ) from exc

import fetch_moscow_region_churches as base


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Сформировать JSON православных храмов, часовен и монастырей "
            "из локального файла OpenStreetMap .osm.pbf."
        )
    )
    parser.add_argument("input", help="Путь к скачанному файлу .osm.pbf")
    parser.add_argument(
        "--output",
        default="database/seeders/data/moscow-region-orthodox-places.json",
        help="Путь к итоговому JSON Laravel seeder.",
    )
    parser.add_argument(
        "--location-index",
        default="flex_mem",
        help=(
            "Индекс координат pyosmium. Для небольшого регионального PBF "
            "оставьте flex_mem."
        ),
    )
    return parser.parse_args()


def first_text(tags: dict[str, Any], *keys: str) -> str | None:
    return base.first_text(tags, *keys)


def infer_region(tags: dict[str, Any]) -> str:
    value = first_text(tags, "addr:region", "is_in:region", "region") or ""
    normalized = base.normalize_text(value)

    if "московская область" in normalized:
        return "Московская область"
    if normalized == "москва" or normalized.startswith("город москва"):
        return "Москва"

    city = first_text(tags, "addr:city", "addr:town") or ""
    if base.normalize_text(city) == "москва":
        return "Москва"

    return "Москва и Московская область"


def flatten_coordinates(value: Any) -> Iterable[tuple[float, float]]:
    if (
        isinstance(value, list)
        and len(value) >= 2
        and isinstance(value[0], (int, float))
        and isinstance(value[1], (int, float))
    ):
        yield float(value[0]), float(value[1])
        return

    if isinstance(value, list):
        for child in value:
            yield from flatten_coordinates(child)


def geometry_center(geometry: dict[str, Any]) -> tuple[float, float] | None:
    points = list(flatten_coordinates(geometry.get("coordinates")))
    if not points:
        return None

    longitudes = [point[0] for point in points]
    latitudes = [point[1] for point in points]
    return (
        (min(latitudes) + max(latitudes)) / 2,
        (min(longitudes) + max(longitudes)) / 2,
    )


class OrthodoxPlaceHandler(osmium.SimpleHandler):
    def __init__(self) -> None:
        super().__init__()
        self.items: list[dict[str, Any]] = []
        self.skipped: Counter[str] = Counter()
        self.seen_sources: set[str] = set()
        self.geometry_factory = osmium.geom.GeoJSONFactory()

    def _append(
        self,
        *,
        osm_type: str,
        osm_id: int,
        tags_object: Any,
        latitude: float,
        longitude: float,
    ) -> None:
        tags = dict(tags_object)
        name = first_text(tags, "name:ru", "name")

        if not name:
            self.skipped["unnamed"] += 1
            return
        if not base.is_active(tags):
            self.skipped["inactive"] += 1
            return
        if not base.is_orthodox_candidate(tags, name):
            self.skipped["non_orthodox"] += 1
            return

        source_id = f"{osm_type}/{osm_id}"
        if source_id in self.seen_sources:
            return
        self.seen_sources.add(source_id)

        region = infer_region(tags)
        item = {
            "source_id": source_id,
            "slug": f"osm-{osm_type}-{osm_id}",
            "osm_type": osm_type,
            "osm_id": osm_id,
            "source_url": f"https://www.openstreetmap.org/{osm_type}/{osm_id}",
            "region": region,
            "type": base.infer_object_type(tags, name),
            "name": name,
            "address": base.build_address(tags, region),
            "latitude": round(latitude, 7),
            "longitude": round(longitude, 7),
            "phone": first_text(tags, "contact:phone", "phone"),
            "email": first_text(tags, "contact:email", "email"),
            "website": first_text(tags, "contact:website", "website"),
            "schedule_text": base.build_schedule(tags),
        }
        self.items.append(item)

    def node(self, node: Any) -> None:
        tags = dict(node.tags)
        if not tags or not first_text(tags, "name:ru", "name"):
            return
        if not node.location.valid():
            self.skipped["no_coordinates"] += 1
            return

        self._append(
            osm_type="node",
            osm_id=int(node.id),
            tags_object=tags,
            latitude=float(node.location.lat),
            longitude=float(node.location.lon),
        )

    def way(self, way: Any) -> None:
        # Closed ways are processed by area(), which also handles multipolygons.
        if way.is_closed():
            return

        tags = dict(way.tags)
        if not tags or not first_text(tags, "name:ru", "name"):
            return

        points: list[tuple[float, float]] = []
        for node_ref in way.nodes:
            if node_ref.location.valid():
                points.append(
                    (float(node_ref.location.lat), float(node_ref.location.lon))
                )

        if not points:
            self.skipped["no_coordinates"] += 1
            return

        latitude = sum(point[0] for point in points) / len(points)
        longitude = sum(point[1] for point in points) / len(points)
        self._append(
            osm_type="way",
            osm_id=int(way.id),
            tags_object=tags,
            latitude=latitude,
            longitude=longitude,
        )

    def area(self, area: Any) -> None:
        tags = dict(area.tags)
        if not tags or not first_text(tags, "name:ru", "name"):
            return

        try:
            geometry = json.loads(self.geometry_factory.create_multipolygon(area))
        except (RuntimeError, ValueError, json.JSONDecodeError):
            self.skipped["invalid_geometry"] += 1
            return

        center = geometry_center(geometry)
        if center is None:
            self.skipped["no_coordinates"] += 1
            return

        osm_type = "way" if area.from_way() else "relation"
        self._append(
            osm_type=osm_type,
            osm_id=int(area.orig_id()),
            tags_object=tags,
            latitude=center[0],
            longitude=center[1],
        )


def main() -> int:
    args = parse_args()
    input_path = Path(args.input)
    output_path = Path(args.output)

    if not input_path.is_file():
        raise SystemExit(f"Не найден PBF-файл: {input_path}")

    print(f"Читаю локальный файл: {input_path}", flush=True)
    handler = OrthodoxPlaceHandler()
    handler.apply_file(
        str(input_path),
        locations=True,
        idx=args.location_index,
    )

    objects, nearby_duplicates = base.deduplicate(handler.items)
    handler.skipped["nearby_duplicates"] += nearby_duplicates

    type_counts = Counter(item["type"] for item in objects)
    region_counts = Counter(item["region"] for item in objects)

    snapshot = {
        "meta": {
            "generated_at": datetime.now(timezone.utc).isoformat(),
            "source": "OpenStreetMap local PBF extract",
            "source_file": input_path.name,
            "license": "ODbL 1.0",
            "imported_count": len(objects),
            "type_counts": dict(type_counts),
            "region_counts": dict(region_counts),
            "skipped": dict(handler.skipped),
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
    print(f"JSON: {output_path}")
    print(f"Всего объектов: {len(objects)}")
    print(f"По типам: {dict(type_counts)}")
    print(f"По регионам: {dict(region_counts)}")
    print(f"Пропущено: {dict(handler.skipped)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
