#!/usr/bin/env python3
"""Resilient Moscow-region church importer.

Uses cached tiled downloads, hard-coded OSM area IDs for Moscow and Moscow
Oblast, and automatic fallback between public Overpass API instances.
"""

from __future__ import annotations

import fetch_moscow_region_churches as base
import fetch_moscow_region_churches_grid as grid

OVERPASS_ENDPOINTS = (
    "https://overpass.private.coffee/api/interpreter",
    "https://overpass-api.de/api/interpreter",
)

AREA_IDS = {
    "RU-MOW": 3600102269,  # OSM relation 102269 + 3,600,000,000
    "RU-MOS": 3600051490,  # OSM relation 51490 + 3,600,000,000
}


def build_tile_query(
    region_iso: str,
    bounds: tuple[float, float, float, float],
) -> str:
    south, west, north, east = bounds
    bbox = f"{south:.7f},{west:.7f},{north:.7f},{east:.7f}"
    area_id = AREA_IDS[region_iso]

    return f'''[out:json][timeout:180][maxsize:268435456];
area({area_id})->.region;
(
  nwr(area.region)({bbox})["name"]["amenity"="place_of_worship"];
  nwr(area.region)({bbox})["name"]["amenity"="monastery"];
  nwr(area.region)({bbox})["name"]["building"~"^(church|cathedral|chapel|monastery)$"];
);
out center tags qt;'''


_original_request_overpass = base.request_overpass


def request_overpass_with_fallback(*args, **kwargs):
    base.OVERPASS_ENDPOINTS = OVERPASS_ENDPOINTS
    return _original_request_overpass(*args, **kwargs)


def main() -> int:
    base.request_overpass = request_overpass_with_fallback
    grid.build_tile_query = build_tile_query
    grid.ENDPOINT = "auto-fallback"
    grid.REGIONS = (
        ("RU-MOW", "Москва", (55.45, 37.10, 56.05, 38.10)),
        ("RU-MOS", "Московская область", (54.10, 35.00, 57.10, 40.40)),
    )
    return grid.main()


if __name__ == "__main__":
    raise SystemExit(main())
