# Развёртывание MapLibre, OpenMapTiles и Valhalla

## Назначение

Эта схема заменяет Яндекс.Карты в проекте «Московский паломник» единым открытым стеком:

```text
Laravel-сайт             MapLibre GL JS
Flutter                  MapLibre Flutter
Картографические данные  OpenStreetMap
Векторный формат         OpenMapTiles
Тайлы                    TileServer GL или совместимый сервер
Маршрутизация            Valhalla
```

## Режим разработки

Без собственного TileServer Laravel возвращает резервный растровый стиль:

```text
GET /api/v1/map/style.json
```

В `.env`:

```env
MAP_STYLE_URL=
OPENMAPTILES_TILE_URL=
MAP_GLYPHS_URL=
MAP_RASTER_TILE_URL=https://tile.openstreetmap.org/{z}/{x}/{y}.png
MAP_OFFLINE_ENABLED=false
VALHALLA_URL=https://valhalla.openstreetmap.de
```

Этот режим нужен только для локальной проверки. Публичный сервер OpenStreetMap не должен использоваться для массового скачивания офлайн-пакетов или высокой production-нагрузки.

## Production-архитектура

Рекомендуемая схема:

```text
maps.example.ru       TileServer GL / OpenMapTiles
route.example.ru      Valhalla
palomnik.example.ru   Laravel + API
```

Laravel:

```env
OPENMAPTILES_TILE_URL=https://maps.example.ru/data/v3/{z}/{x}/{y}.pbf
MAP_GLYPHS_URL=https://maps.example.ru/fonts/{fontstack}/{range}.pbf
MAP_ATTRIBUTION="© OpenStreetMap contributors"
MAP_OFFLINE_ENABLED=true
MAP_OFFLINE_TILE_LIMIT=100000
VALHALLA_URL=https://route.example.ru
VALHALLA_TIMEOUT=20
```

После изменения:

```bash
php artisan optimize:clear
```

## TileServer GL

Подготовьте `.mbtiles` с регионом, например:

```text
maps/data/moscow-region.mbtiles
```

Пример запуска официального Docker-образа:

```bash
docker run --rm \
  -v "$(pwd)/maps/data:/data" \
  -p 8080:8080 \
  maptiler/tileserver-gl:latest
```

Проверьте:

```text
http://127.0.0.1:8080
```

Фактический идентификатор набора данных зависит от имени MBTiles и конфигурации TileServer. Уточните URL `.pbf` в TileJSON, который показывает сервер.

Пример:

```env
OPENMAPTILES_TILE_URL=http://127.0.0.1:8080/data/moscow-region/{z}/{x}/{y}.pbf
```

Для локального Android Emulator адрес компьютера:

```env
OPENMAPTILES_TILE_URL=http://10.0.2.2:8080/data/moscow-region/{z}/{x}/{y}.pbf
MAP_GLYPHS_URL=http://10.0.2.2:8080/fonts/{fontstack}/{range}.pbf
```

При этом Laravel также должен отдавать style.json с URL, доступными устройству. В production всегда используйте HTTPS.

## Valhalla

Для production рекомендуется собственный экземпляр с заранее построенными тайлами маршрутизации по нужному региону.

Один из вариантов контейнера:

```bash
docker run --rm \
  -p 8002:8002 \
  -v "$(pwd)/valhalla:/custom_files" \
  ghcr.io/nilsnolde/docker-valhalla/valhalla:latest
```

В каталог `valhalla` помещается PBF региона и конфигурация контейнера. Построение тайлов может занять продолжительное время и требует памяти и диска.

Laravel:

```env
VALHALLA_URL=http://127.0.0.1:8002
```

Проверка через API проекта:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/map/route \
  -H "Content-Type: application/json" \
  -d '{
    "mode":"pedestrian",
    "locations":[
      {"latitude":55.751244,"longitude":37.618423},
      {"latitude":55.781000,"longitude":37.702000}
    ]
  }'
```

## Виды маршрутов

Проект принимает:

```text
pedestrian   пешком
auto         автомобиль
bicycle      велосипед
bus          автобус
multimodal   мультимодальный маршрут
```

Для полноценного общественного транспорта Valhalla должен быть настроен с актуальными GTFS-данными. Без GTFS мультимодальный результат может быть ограничен.

## Офлайн-карты Flutter

Офлайн-загрузка включается только при собственном или лицензированном сервере:

```env
MAP_OFFLINE_ENABLED=true
MAP_OFFLINE_TILE_LIMIT=100000
```

В приложении подготовлены пакеты:

- Москва: масштабы 8–14;
- Московская область: масштабы 6–11.

Перед публикацией необходимо проверить реальный размер пакетов на Android и iOS. При превышении лимита уменьшите максимальный масштаб или территорию.

Публичный растровый fallback нельзя использовать для этой функции.

## Спутниковый слой

OpenStreetMap не предоставляет спутниковые изображения. Подключайте только поставщика, лицензия которого разрешает использование в веб-приложении и Flutter:

```env
MAP_SATELLITE_TILE_URL=https://provider.example/{z}/{x}/{y}.jpg
```

## Исторический слой

После геопривязки исторической карты опубликуйте её как XYZ/TMS-тайлы:

```env
MAP_HISTORIC_TILE_URL=https://maps.example.ru/historic/{z}/{x}/{y}.png
```

На сайте слой будет доступен в переключателе. Для приложения отдельный переключатель можно подключить к тому же URL.

## Проверка Laravel

```bash
php artisan optimize:clear
php artisan route:list --path=api/v1/map
php artisan test --filter=MapApiTest
```

Проверьте вручную:

```text
http://127.0.0.1:8000/map
http://127.0.0.1:8000/api/v1/map/config
http://127.0.0.1:8000/api/v1/map/style.json
```

## Проверка Flutter

```bash
cd mobile
flutter pub get
flutter analyze
flutter test
flutter run \
  --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1 \
  --dart-define=SITE_BASE_URL=http://10.0.2.2:8000
```

После проверки:

- маркеры открывают карточки;
- GPS показывает пользователя;
- Valhalla строит линию маршрута;
- расстояние и время отображаются;
- офлайн-пакеты недоступны при `MAP_OFFLINE_ENABLED=false`;
- при разрешённом TileServer пакет скачивается и остаётся после перезапуска.
