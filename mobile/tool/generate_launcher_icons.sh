#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [ ! -d android ] || [ ! -d ios ]; then
  flutter create . --platforms=android,ios --org ru.mospalomnik
fi

flutter pub get
dart run flutter_launcher_icons -f flutter_launcher_icons.yaml

echo "Launcher-иконки Android и iOS обновлены из assets/images/app_icon.png"
