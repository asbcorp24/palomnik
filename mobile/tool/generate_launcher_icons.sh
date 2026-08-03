#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

flutter pub get
dart run flutter_launcher_icons -f flutter_launcher_icons.yaml

echo "Launcher-иконки Android и iOS обновлены из assets/icon/app_icon.jpg"
