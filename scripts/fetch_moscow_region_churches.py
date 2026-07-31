#!/usr/bin/env python3
"""Download Orthodox churches, chapels and monasteries for Moscow and Moscow Oblast.

The script uses OpenStreetMap data through public Overpass API mirrors and writes
Laravel seed data to:

    database/seeders/data/moscow-region-orthodox-places.json

No third-party Python packages are required.
"""

from __future__ import annotations

import argparse
import json
import math
import re
import sys
import time
import unicodedata
import urllib.error
import urllib.parse
import urllib.request
from collections import Counter
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

OVERPASS_ENDPOINTS = (
    "https://overpass.kumi.systems/api/interpreter",
    "https://overpass-api.de/api/interpreter",
    "https://overpass.private.coffee/api/interpreter",
)

REGIONS = (
    ("RU-MOW", "Москва"),
    ("RU-MOS", "Московская область"),
)

QUERIES = (
    ('amenity', 'place_of_worship'),
    ('amenity', 'monastery'),
    ('building', 'church|cathedral|chapel|monastery'),
)

NON_ORTHODOX_DENOMINATIONS = {
    "roman_catholic",
    "catholic",
    "protestant",
    "lutheran",
    "baptist",
    "evangelical",
    "pentecostal",
    "methodist",
    "adventist",
    "seventh_day_adventist",
    "jehovahs_witness",
    "mormon",
    "new_apostolic",
    "armenian_apostolic",
    "anglican",
    "presbyterian",
    "reformed",
    "quaker",
    "unitarian",
    "mennonite",
}

NON_ORTHODOX_NAME_RE = re.compile(
    r"(католич|лютеран|протестант|баптист|евангель|адвентист|"
    r"пятидесят|армянск|мормон|иегов|англикан|методист|"
    r"пресвитериан|реформат|свидетел[ья] иеговы)",
    re.IGNORECASE,
)

CHRISTIAN_PLACE_NAME_RE = re.compile(
    r"(храм|церков|собор|часовн|монастыр|подворье|пустынь|"
    r"church|cathedral|chapel|monastery)",
    re.IGNORECASE,
)

LIFECYCLE_PREFIXES = ("abandoned:", "disused:", "demolished:", "razed:")


@dataclass(frozen=True)
class Coordinates:
    latitude: float
    longitude: float


class ImportErrorWithContext(RuntimeError):
    pass


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Скачать православные храмы Москвы и Московской области из OpenStreetMap."
    )
    parser.add_argument(
        "--output",
        default="database/seeders/data/moscow-region-orthodox-places.json",
        help="Путь к итоговому JSON-файлу.",
    )
    parser.add_argument(
        "--request-delay",
        type=float,
        default=2.0,
        help="Пауза между запросами Overpass в секундах.",
    )
    parser.add_argument(
        "--timeout",
        type=int,
        default=600,
        help="Таймаут одного HTTP-запроса в секундах.",
    )
    parser.add_argument(
        "--retries",
        type=int,
        default=3,
        help="Количество попыток на каждом зеркале Overpass.",
    )
    return parser.parse_args()


def build_query(region_iso: str, key: str, value_pattern: str) -> str:
    if "|" in value_pattern:
        selector = f'["{key}"~"^({value_pattern})$"]'
    else:
        selector = f'["{key}"="{value_pattern}"]'

    return f'''[out:json][timeout:550][maxsize:1073741824];
area["ISO3166-2"="{region_iso}"]["boundary"="administrative"]->.region;
nwr(area.region)["name"]{selector};
out center tags qt;'''


def request_overpass(
    query: str,
    *,
    timeout: int,
    retries: int,
    request_label: str,
) -> dict[str, Any]:
    payload = urllib.parse.urlencode({"data": query}).encode("utf-8")
    last_error: Exception | None = None

    for endpoint in OVERPASS_ENDPOINTS:
        for attempt in range(1, retries + 1):
            try:
                request = urllib.request.Request(
                    endpoint,
                    data=payload,
                    method="POST",
                    headers={
                        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                        "User-Agent": (
                            "MoscowPilgrimSeeder/1.0 "
                            "(+https://github.com/asbcorp24/palomnik)"
                        ),
                    },
                )
                print(
                    f"[{request_label}] {endpoint}, попытка {attempt}/{retries}",
                    flush=True,
                )
                with urllib.request.urlopen(request, timeout=timeout) as response:
                    body = response.read().decode("utf-8")
                decoded = json.loads(body)
                if not isinstance(decoded, dict) or "elements" not in decoded:
                    raise ImportErrorWithContext("Overpass вернул неожиданный JSON")
                return decoded
            except (
                urllib.error.URLError,
                urllib.error.HTTPError,
                TimeoutError,
                json.JSONDecodeError,
                ImportErrorWithContext,
            ) as exc:
                last_error = exc
                delay = min(30, attempt * 5)
                print(f"  Ошибка: {exc}. Повтор через {delay} с.", file=sys.stderr)
                time.sleep(delay)

    raise ImportErrorWithContext(
        f"Не удалось выполнить запрос {request_label}: {last_error}"
    )


def first_text(tags: dict[str, Any], *keys: str) -> str | None:
    for key in keys:
        value = tags.get(key)
        if value is None:
            continue
        text = str(value).strip()
        if text:
            return text
    return None


def normalize_text(value: str) -> str:
    value = unicodedata.normalize("NFKC", value).casefold().replace("ё", "е")
    return re.sub(r"[^a-zа-я0-9]+", " ", value).strip()


def extract_coordinates(element: dict[str, Any]) -> Coordinates | None:
    if element.get("lat") is not None and element.get("lon") is not None:
        return Coordinates(float(element["lat"]), float(element["lon"]))

    center = element.get("center") or {}
    if center.get("lat") is not None and center.get("lon") is not None:
        return Coordinates(float(center["lat"]), float(center["lon"]))

    return None


def is_active(tags: dict[str, Any]) -> bool:
    if tags.get("construction") or tags.get("building") == "construction":
        return False
    if tags.get("ruins") == "yes" or tags.get("historic") == "ruins":
        return False

    has_lifecycle_tag = any(
        str(key).startswith(LIFECYCLE_PREFIXES) for key in tags.keys()
    )
    if has_lifecycle_tag and tags.get("amenity") not in {
        "place_of_worship",
        "monastery",
    }:
        return False

    return True


def is_orthodox_candidate(tags: dict[str, Any], name: str) -> bool:
    religion = str(tags.get("religion") or "").strip().casefold()
    if religion and religion != "christian":
        return False

    denomination = str(tags.get("denomination") or "").strip().casefold()
    if denomination in NON_ORTHODOX_DENOMINATIONS:
        return False

    if NON_ORTHODOX_NAME_RE.search(name):
        return False

    if denomination in {
        "orthodox",
        "russian_orthodox",
        "eastern_orthodox",
        "old_believers",
    }:
        return True

    building = str(tags.get("building") or "").strip().casefold()
    amenity = str(tags.get("amenity") or "").strip().casefold()

    if building in {"church", "cathedral", "chapel", "monastery"}:
        return True
    if amenity == "monastery":
        return True

    return bool(CHRISTIAN_PLACE_NAME_RE.search(name))


def infer_object_type(tags: dict[str, Any], name: str) -> str:
    haystack = normalize_text(
        " ".join(
            filter(
                None,
                [
                    name,
                    str(tags.get("building") or ""),
                    str(tags.get("amenity") or ""),
                    str(tags.get("place") or ""),
                ],
            )
        )
    )

    if (
        "монастыр" in haystack
        or "пустынь" in haystack
        or "monastery" in haystack
        or tags.get("amenity") == "monastery"
        or tags.get("building") == "monastery"
    ):
        return "monastery"

    if (
        "часовн" in haystack
        or "chapel" in haystack
        or tags.get("building") == "chapel"
    ):
        return "chapel"

    return "temple"


def build_address(tags: dict[str, Any], region_name: str) -> str:
    full_address = first_text(tags, "addr:full")
    if full_address:
        return full_address

    parts: list[str] = []
    candidates = (
        first_text(tags, "addr:region") or region_name,
        first_text(tags, "addr:city", "addr:town", "addr:village", "addr:place"),
        first_text(tags, "addr:suburb", "addr:district"),
        first_text(tags, "addr:street"),
    )

    for value in candidates:
        if value and value not in parts:
            parts.append(value)

    house_number = first_text(tags, "addr:housenumber")
    if house_number:
        if parts:
            parts[-1] = f"{parts[-1]}, {house_number}"
        else:
            parts.append(house_number)

    return ", ".join(parts) if parts else region_name


def build_schedule(tags: dict[str, Any]) -> str | None:
    rows: list[str] = []
    service_times = first_text(tags, "service_times")
    opening_hours = first_text(tags, "opening_hours")

    if service_times:
        rows.append(f"Богослужения: {service_times}")
    if opening_hours:
        rows.append(f"Часы работы: {opening_hours}")

    return "\n".join(rows) or None


def haversine_meters(a: Coordinates, b: Coordinates) -> float:
    earth_radius_m = 6_371_000.0
    lat1, lon1 = math.radians(a.latitude), math.radians(a.longitude)
    lat2, lon2 = math.radians(b.latitude), math.radians(b.longitude)
    delta_lat = lat2 - lat1
    delta_lon = lon2 - lon1

    h = (
        math.sin(delta_lat / 2) ** 2
        + math.cos(lat1) * math.cos(lat2) * math.sin(delta_lon / 2) ** 2
    )
    return earth_radius_m * 2 * math.atan2(math.sqrt(h), math.sqrt(1 - h))


def richness(item: dict[str, Any]) -> int:
    score = sum(
        1
        for field in ("address", "phone", "email", "website", "schedule_text")
        if item.get(field)
    )
    if item["osm_type"] != "node":
        score += 2
    return score


def element_to_item(
    element: dict[str, Any], region_name: str
) -> tuple[dict[str, Any] | None, str | None]:
    tags = element.get("tags") or {}
    name = first_text(tags, "name:ru", "name")
    if not name:
        return None, "unnamed"
    if not is_active(tags):
        return None, "inactive"
    if not is_orthodox_candidate(tags, name):
        return None, "non_orthodox"

    coordinates = extract_coordinates(element)
    if coordinates is None:
        return None, "no_coordinates"

    osm_type = str(element["type"])
    osm_id = int(element["id"])

    return {
        "source_id": f"{osm_type}/{osm_id}",
        "slug": f"osm-{osm_type}-{osm_id}",
        "osm_type": osm_type,
        "osm_id": osm_id,
        "source_url": f"https://www.openstreetmap.org/{osm_type}/{osm_id}",
        "region": region_name,
        "type": infer_object_type(tags, name),
        "name": name,
        "address": build_address(tags, region_name),
        "latitude": round(coordinates.latitude, 7),
        "longitude": round(coordinates.longitude, 7),
        "phone": first_text(tags, "contact:phone", "phone"),
        "email": first_text(tags, "contact:email", "email"),
        "website": first_text(tags, "contact:website", "website"),
        "schedule_text": build_schedule(tags),
    }, None


def deduplicate(items: list[dict[str, Any]]) -> tuple[list[dict[str, Any]], int]:
    # First remove exact OSM duplicates caused by overlapping queries.
    unique_by_source: dict[str, dict[str, Any]] = {}
    for item in items:
        existing = unique_by_source.get(item["source_id"])
        if existing is None or richness(item) > richness(existing):
            unique_by_source[item["source_id"]] = item

    ordered = sorted(
        unique_by_source.values(),
        key=lambda item: (-richness(item), item["source_id"]),
    )

    result: list[dict[str, Any]] = []
    removed = 0

    # Then merge node + building/relation records with the same name and type nearby.
    for item in ordered:
        key = (item["type"], normalize_text(item["name"]))
        current_coordinates = Coordinates(item["latitude"], item["longitude"])
        duplicate_found = False

        for existing in result:
            existing_key = (
                existing["type"],
                normalize_text(existing["name"]),
            )
            if existing_key != key:
                continue

            existing_coordinates = Coordinates(
                existing["latitude"], existing["longitude"]
            )
            if haversine_meters(current_coordinates, existing_coordinates) <= 80:
                duplicate_found = True
                removed += 1
                break

        if not duplicate_found:
            result.append(item)

    result.sort(
        key=lambda item: (
            item["region"],
            item["type"],
            normalize_text(item["name"]),
            item["source_id"],
        )
    )
    return result, removed


def main() -> int:
    args = parse_args()
    output_path = Path(args.output)
    all_items: list[dict[str, Any]] = []
    skipped = Counter()
    raw_counts: dict[str, int] = {}

    for region_iso, region_name in REGIONS:
        region_raw_count = 0
        for key, value_pattern in QUERIES:
            label = f"{region_iso} {key}={value_pattern}"
            payload = request_overpass(
                build_query(region_iso, key, value_pattern),
                timeout=args.timeout,
                retries=args.retries,
                request_label=label,
            )
            elements = payload.get("elements", [])
            region_raw_count += len(elements)

            for element in elements:
                item, skip_reason = element_to_item(element, region_name)
                if item is not None:
                    all_items.append(item)
                elif skip_reason:
                    skipped[skip_reason] += 1

            time.sleep(max(0.0, args.request_delay))

        raw_counts[region_iso] = region_raw_count

    objects, nearby_duplicates = deduplicate(all_items)
    skipped["nearby_duplicates"] += nearby_duplicates
    type_counts = Counter(item["type"] for item in objects)
    region_counts = Counter(item["region"] for item in objects)

    snapshot = {
        "meta": {
            "generated_at": datetime.now(timezone.utc).isoformat(),
            "source": "OpenStreetMap contributors via Overpass API",
            "license": "ODbL 1.0",
            "regions": [region_iso for region_iso, _ in REGIONS],
            "raw_counts": raw_counts,
            "imported_count": len(objects),
            "type_counts": dict(type_counts),
            "region_counts": dict(region_counts),
            "skipped": dict(skipped),
            "notice": (
                "Это снимок данных OpenStreetMap, а не официальный реестр. "
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
    print("\nСледующая команда:")
    print("php artisan db:seed --class=MoscowRegionChurchSeeder --force")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except KeyboardInterrupt:
        print("\nОстановлено пользователем.", file=sys.stderr)
        raise SystemExit(130)
    except Exception as exc:
        print(f"\nОшибка: {exc}", file=sys.stderr)
        raise SystemExit(1)
