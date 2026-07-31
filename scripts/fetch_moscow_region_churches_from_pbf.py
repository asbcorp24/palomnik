#!/usr/bin/env python3
"""Create Laravel seed JSON files from a local OpenStreetMap PBF extract.

Outputs:
1. pilgrimage objects (temples, chapels, monasteries and holy springs);
2. nearby infrastructure linked to each object (parking, cafes and hotels).

The importer does not use Overpass.

Example:
    python scripts/fetch_moscow_region_churches_from_pbf.py storage/app/moscow-region.osm.pbf

Requires:
    pip install osmium
"""

from __future__ import annotations

import argparse
import json
import math
import re
from collections import Counter, defaultdict
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Iterable

try:
    import osmium
except ImportError as exc:
    raise SystemExit(
        "Не установлен пакет osmium. Выполните:\n"
        "C:\\Python311\\python.exe -m pip install osmium"
    ) from exc

import fetch_moscow_region_churches as base


ORTHODOX_DENOMINATIONS = {
    "orthodox",
    "russian_orthodox",
    "eastern_orthodox",
    "old_believers",
}

# These are auxiliary/commercial objects, not pilgrimage destinations.
EXCLUDED_MAIN_NAME_RE = re.compile(
    r"("
    r"церковн\w*\s+лавк|иконн\w*\s+лавк|лавк|магазин|киоск|"
    r"\bпридел\b|\bпредел\b|"
    r"трапезн|просфорн|крестильн|"
    r"воскресн\w*\s+школ|"
    r"духовн\w*[-\s]+просветитель|"
    r"администрац|канцеляр|"
    r"приходск\w*\s+дом|дом\s+причта|"
    r"колокольн|звонниц"
    r")",
    re.IGNORECASE,
)

NON_ORTHODOX_NAME_RE = re.compile(
    r"("
    r"католич|кост[её]л|кирх|лютеран|протестант|баптист|"
    r"евангель|адвентист|пятидесят|армянск|мормон|иегов|"
    r"англикан|методист|пресвитериан|реформат|"
    r"мечет|синагог|дацан|буддий"
    r")",
    re.IGNORECASE,
)

TEMPLE_NAME_RE = re.compile(
    r"(храм|церков|собор|базилик|church|cathedral)", re.IGNORECASE
)
CHAPEL_NAME_RE = re.compile(r"(часовн|chapel)", re.IGNORECASE)
MONASTERY_NAME_RE = re.compile(
    r"(монастыр|подворье|пустынь|monastery)", re.IGNORECASE
)
HOLY_SPRING_NAME_RE = re.compile(
    r"("
    r"свят\w*\s+(источник|ключ|родник)|"
    r"(источник|ключ|родник)\s+свят|"
    r"источник\s+во\s+имя|"
    r"купел\w*\s+у\s+(источник|родник)|"
    r"holy\s+spring"
    r")",
    re.IGNORECASE,
)

COMMERCIAL_AMENITIES = {
    "cafe",
    "restaurant",
    "fast_food",
    "food_court",
    "marketplace",
    "bank",
    "pharmacy",
    "school",
    "kindergarten",
    "community_centre",
}

POI_CATEGORY_ORDER = {
    "parking": 1,
    "cafe": 2,
    "hotel": 3,
}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Сформировать JSON паломнических объектов и ближайшей инфраструктуры "
            "из локального файла OpenStreetMap .osm.pbf."
        )
    )
    parser.add_argument("input", help="Путь к скачанному файлу .osm.pbf")
    parser.add_argument(
        "--output",
        default="database/seeders/data/moscow-region-orthodox-places.json",
        help="JSON храмов, монастырей, часовен и святых источников.",
    )
    parser.add_argument(
        "--nearby-output",
        default="database/seeders/data/moscow-region-nearby-points.json",
        help="JSON ближайших парковок, кафе и гостиниц.",
    )
    parser.add_argument(
        "--location-index",
        default="flex_mem",
        help="Индекс координат pyosmium.",
    )
    parser.add_argument("--parking-radius", type=int, default=800)
    parser.add_argument("--cafe-radius", type=int, default=1200)
    parser.add_argument("--hotel-radius", type=int, default=2500)
    parser.add_argument("--parking-limit", type=int, default=3)
    parser.add_argument("--cafe-limit", type=int, default=4)
    parser.add_argument("--hotel-limit", type=int, default=3)
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


def classify_main_object(tags: dict[str, Any], name: str) -> str | None:
    """Broad religious recognition with focused auxiliary-object exclusions."""
    if not base.is_active(tags):
        return None
    if EXCLUDED_MAIN_NAME_RE.search(name) or NON_ORTHODOX_NAME_RE.search(name):
        return None

    religion = str(tags.get("religion") or "").strip().casefold()
    denomination = str(tags.get("denomination") or "").strip().casefold()
    building = str(tags.get("building") or "").strip().casefold()
    amenity = str(tags.get("amenity") or "").strip().casefold()
    natural = str(tags.get("natural") or "").strip().casefold()
    place = str(tags.get("place") or "").strip().casefold()
    historic = str(tags.get("historic") or "").strip().casefold()

    if religion and religion != "christian":
        return None
    if denomination in base.NON_ORTHODOX_DENOMINATIONS:
        return None

    # A shop/office/cafe must never become a temple because of its name.
    if tags.get("shop") or tags.get("office") or amenity in COMMERCIAL_AMENITIES:
        return None

    if natural == "spring" or amenity in {"drinking_water", "fountain"}:
        if (
            HOLY_SPRING_NAME_RE.search(name)
            or denomination in ORTHODOX_DENOMINATIONS
            or religion == "christian"
        ):
            return "holy-spring"
        return None

    if (
        amenity == "monastery"
        or building == "monastery"
        or place == "monastery"
        or MONASTERY_NAME_RE.search(name)
    ):
        return "monastery"

    if building == "chapel" or CHAPEL_NAME_RE.search(name):
        return "chapel"

    if building in {"church", "cathedral"}:
        return "temple"

    if amenity == "place_of_worship" and building != "chapel":
        return "temple"

    if historic in {"church", "cathedral"}:
        return "temple"

    # Restore the original broad fallback for poorly tagged OSM objects.
    # Focused exclusions above keep church shops and auxiliary buildings out.
    if TEMPLE_NAME_RE.search(name):
        return "temple"

    if denomination in ORTHODOX_DENOMINATIONS and religion in {"", "christian"}:
        if amenity == "place_of_worship":
            return "temple"

    return None


def classify_nearby_point(tags: dict[str, Any], name: str | None) -> str | None:
    amenity = str(tags.get("amenity") or "").strip().casefold()
    tourism = str(tags.get("tourism") or "").strip().casefold()

    if amenity == "parking":
        return "parking"

    if amenity in {"cafe", "restaurant", "fast_food", "food_court"}:
        return "cafe" if name else None

    if tourism in {
        "hotel",
        "guest_house",
        "hostel",
        "motel",
        "apartment",
        "chalet",
    }:
        return "hotel" if name else None

    return None


def default_poi_name(category: str, tags: dict[str, Any]) -> str:
    if category == "parking":
        parking_type = str(tags.get("parking") or "").strip().casefold()
        labels = {
            "surface": "Наземная парковка",
            "multi-storey": "Многоуровневая парковка",
            "underground": "Подземная парковка",
            "street_side": "Уличная парковка",
        }
        return labels.get(parking_type, "Парковка")
    return "Точка инфраструктуры"


def distance_label(distance_meters: int) -> str:
    if distance_meters < 1000:
        return f"{distance_meters} м"
    return f"{distance_meters / 1000:.1f} км".replace(".", ",")


class LocalPbfHandler(osmium.SimpleHandler):
    def __init__(self) -> None:
        super().__init__()
        self.main_items: list[dict[str, Any]] = []
        self.poi_items: list[dict[str, Any]] = []
        self.skipped: Counter[str] = Counter()
        self.main_seen: set[str] = set()
        self.poi_seen: set[str] = set()
        self.geometry_factory = osmium.geom.GeoJSONFactory()

    def _consume(
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
        source_id = f"{osm_type}/{osm_id}"

        if name:
            object_type = classify_main_object(tags, name)
            if object_type and source_id not in self.main_seen:
                self.main_seen.add(source_id)
                region = infer_region(tags)
                self.main_items.append(
                    {
                        "source_id": source_id,
                        "slug": f"osm-{osm_type}-{osm_id}",
                        "osm_type": osm_type,
                        "osm_id": osm_id,
                        "source_url": (
                            f"https://www.openstreetmap.org/{osm_type}/{osm_id}"
                        ),
                        "region": region,
                        "type": object_type,
                        "name": name,
                        "address": base.build_address(tags, region),
                        "latitude": round(latitude, 7),
                        "longitude": round(longitude, 7),
                        "phone": first_text(tags, "contact:phone", "phone"),
                        "email": first_text(tags, "contact:email", "email"),
                        "website": first_text(tags, "contact:website", "website"),
                        "schedule_text": base.build_schedule(tags),
                    }
                )

        poi_category = classify_nearby_point(tags, name)
        if poi_category and source_id not in self.poi_seen:
            self.poi_seen.add(source_id)
            region = infer_region(tags)
            self.poi_items.append(
                {
                    "source_id": source_id,
                    "category": poi_category,
                    "name": name or default_poi_name(poi_category, tags),
                    "address": base.build_address(tags, region),
                    "latitude": round(latitude, 7),
                    "longitude": round(longitude, 7),
                    "phone": first_text(tags, "contact:phone", "phone"),
                    "website": first_text(tags, "contact:website", "website"),
                    "schedule_text": base.build_schedule(tags),
                }
            )

    def node(self, node: Any) -> None:
        tags = dict(node.tags)
        if not tags or not node.location.valid():
            return
        self._consume(
            osm_type="node",
            osm_id=int(node.id),
            tags_object=tags,
            latitude=float(node.location.lat),
            longitude=float(node.location.lon),
        )

    def way(self, way: Any) -> None:
        if way.is_closed():
            return

        tags = dict(way.tags)
        if not tags:
            return

        points = [
            (float(node_ref.location.lat), float(node_ref.location.lon))
            for node_ref in way.nodes
            if node_ref.location.valid()
        ]
        if not points:
            return

        self._consume(
            osm_type="way",
            osm_id=int(way.id),
            tags_object=tags,
            latitude=sum(point[0] for point in points) / len(points),
            longitude=sum(point[1] for point in points) / len(points),
        )

    def area(self, area: Any) -> None:
        tags = dict(area.tags)
        if not tags:
            return

        try:
            geometry = json.loads(self.geometry_factory.create_multipolygon(area))
        except (RuntimeError, ValueError, json.JSONDecodeError):
            self.skipped["invalid_geometry"] += 1
            return

        center = geometry_center(geometry)
        if center is None:
            return

        self._consume(
            osm_type="way" if area.from_way() else "relation",
            osm_id=int(area.orig_id()),
            tags_object=tags,
            latitude=center[0],
            longitude=center[1],
        )


def deduplicate_pois(items: list[dict[str, Any]]) -> list[dict[str, Any]]:
    """Merge node/building duplicates with the same category/name within 50 m."""
    ordered = sorted(
        items,
        key=lambda item: (
            item["category"],
            base.normalize_text(item["name"]),
            item["source_id"],
        ),
    )
    result: list[dict[str, Any]] = []

    for item in ordered:
        item_coordinates = base.Coordinates(item["latitude"], item["longitude"])
        duplicate = False
        for existing in reversed(result[-100:]):
            if existing["category"] != item["category"]:
                continue
            if base.normalize_text(existing["name"]) != base.normalize_text(
                item["name"]
            ):
                continue
            existing_coordinates = base.Coordinates(
                existing["latitude"], existing["longitude"]
            )
            if base.haversine_meters(item_coordinates, existing_coordinates) <= 50:
                duplicate = True
                break
        if not duplicate:
            result.append(item)

    return result


def spatial_cell(latitude: float, longitude: float, size: float = 0.02) -> tuple[int, int]:
    return (math.floor(latitude / size), math.floor(longitude / size))


def nearby_candidates(
    object_item: dict[str, Any],
    category: str,
    radius_meters: int,
    spatial_index: dict[tuple[int, int], list[dict[str, Any]]],
    cell_size: float = 0.02,
) -> list[tuple[int, dict[str, Any]]]:
    latitude = float(object_item["latitude"])
    longitude = float(object_item["longitude"])
    origin = base.Coordinates(latitude, longitude)

    lat_degrees = radius_meters / 111_000
    lon_scale = max(0.2, math.cos(math.radians(latitude)))
    lon_degrees = radius_meters / (111_000 * lon_scale)
    cell_range = max(
        1,
        math.ceil(max(lat_degrees, lon_degrees) / cell_size),
    )
    center_cell = spatial_cell(latitude, longitude, cell_size)

    found: list[tuple[int, dict[str, Any]]] = []
    for lat_offset in range(-cell_range, cell_range + 1):
        for lon_offset in range(-cell_range, cell_range + 1):
            for candidate in spatial_index.get(
                (center_cell[0] + lat_offset, center_cell[1] + lon_offset), []
            ):
                if candidate["category"] != category:
                    continue
                distance = round(
                    base.haversine_meters(
                        origin,
                        base.Coordinates(
                            candidate["latitude"], candidate["longitude"]
                        ),
                    )
                )
                if distance <= radius_meters:
                    found.append((distance, candidate))

    found.sort(key=lambda row: (row[0], base.normalize_text(row[1]["name"])))
    return found


def build_nearby_links(
    objects: list[dict[str, Any]],
    poi_items: list[dict[str, Any]],
    radii: dict[str, int],
    limits: dict[str, int],
) -> list[dict[str, Any]]:
    spatial_index: dict[tuple[int, int], list[dict[str, Any]]] = defaultdict(list)
    for item in poi_items:
        spatial_index[spatial_cell(item["latitude"], item["longitude"])].append(item)

    links: list[dict[str, Any]] = []

    for object_item in objects:
        for category in ("parking", "cafe", "hotel"):
            matches = nearby_candidates(
                object_item,
                category,
                radii[category],
                spatial_index,
            )[: limits[category]]

            for position, (distance, point) in enumerate(matches, start=1):
                links.append(
                    {
                        "object_slug": object_item["slug"],
                        "source_id": point["source_id"],
                        "category": category,
                        "name": point["name"],
                        "description": (
                            f"Расстояние от объекта: {distance_label(distance)}. "
                            "Данные OpenStreetMap."
                        ),
                        "distance_meters": distance,
                        "address": point["address"],
                        "latitude": point["latitude"],
                        "longitude": point["longitude"],
                        "phone": point["phone"],
                        "website": point["website"],
                        "schedule_text": point["schedule_text"],
                        "sort_order": (
                            POI_CATEGORY_ORDER[category] * 10_000
                            + distance
                            + position
                        ),
                    }
                )

    links.sort(
        key=lambda item: (
            item["object_slug"],
            POI_CATEGORY_ORDER[item["category"]],
            item["distance_meters"],
            base.normalize_text(item["name"]),
        )
    )
    return links


def main() -> int:
    args = parse_args()
    input_path = Path(args.input)
    output_path = Path(args.output)
    nearby_output_path = Path(args.nearby_output)

    if not input_path.is_file():
        raise SystemExit(f"Не найден PBF-файл: {input_path}")

    radii = {
        "parking": max(0, args.parking_radius),
        "cafe": max(0, args.cafe_radius),
        "hotel": max(0, args.hotel_radius),
    }
    limits = {
        "parking": max(0, args.parking_limit),
        "cafe": max(0, args.cafe_limit),
        "hotel": max(0, args.hotel_limit),
    }

    print(f"Читаю локальный файл: {input_path}", flush=True)
    handler = LocalPbfHandler()
    handler.apply_file(
        str(input_path),
        locations=True,
        idx=args.location_index,
    )

    objects, nearby_duplicates = base.deduplicate(handler.main_items)
    handler.skipped["nearby_duplicates"] += nearby_duplicates

    poi_items = deduplicate_pois(handler.poi_items)
    nearby_links = build_nearby_links(objects, poi_items, radii, limits)

    type_counts = Counter(item["type"] for item in objects)
    region_counts = Counter(item["region"] for item in objects)
    poi_counts = Counter(item["category"] for item in nearby_links)

    generated_at = datetime.now(timezone.utc).isoformat()

    places_snapshot = {
        "meta": {
            "generated_at": generated_at,
            "source": "OpenStreetMap local PBF extract",
            "source_file": input_path.name,
            "license": "ODbL 1.0",
            "imported_count": len(objects),
            "type_counts": dict(type_counts),
            "region_counts": dict(region_counts),
            "skipped": dict(handler.skipped),
            "included_types": [
                "temple",
                "monastery",
                "chapel",
                "holy-spring",
            ],
            "notice": (
                "Используется широкое распознавание религиозных объектов "
                "с исключением лавок, приделов и служебных зданий."
            ),
        },
        "objects": objects,
    }

    nearby_snapshot = {
        "meta": {
            "generated_at": generated_at,
            "source": "OpenStreetMap local PBF extract",
            "source_file": input_path.name,
            "license": "ODbL 1.0",
            "imported_count": len(nearby_links),
            "category_counts": dict(poi_counts),
            "radii_meters": radii,
            "limits_per_object": limits,
            "notice": (
                "Для каждого паломнического объекта сохранены только ближайшие "
                "точки в пределах установленного радиуса."
            ),
        },
        "points": nearby_links,
    }

    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(
        json.dumps(places_snapshot, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )

    nearby_output_path.parent.mkdir(parents=True, exist_ok=True)
    nearby_output_path.write_text(
        json.dumps(nearby_snapshot, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )

    print("\nГотово")
    print(f"Основные объекты: {output_path}")
    print(f"Ближайшие точки: {nearby_output_path}")
    print(f"Всего основных объектов: {len(objects)}")
    print(f"По типам: {dict(type_counts)}")
    print(f"Всего привязок инфраструктуры: {len(nearby_links)}")
    print(f"По категориям: {dict(poi_counts)}")
    print(f"Радиусы: {radii}")
    print(f"Лимиты на объект: {limits}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
