#!/usr/bin/env python3
"""Run the Moscow-region church importer through one fixed Overpass endpoint."""

from __future__ import annotations

import fetch_moscow_region_churches as importer


if __name__ == "__main__":
    importer.OVERPASS_ENDPOINTS = (
        "https://overpass.private.coffee/api/interpreter",
    )
    raise SystemExit(importer.main())
