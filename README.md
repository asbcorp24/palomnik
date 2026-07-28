# Московский паломник

Единая цифровая платформа для организации, планирования и сопровождения паломнических поездок по Москве и Московской области.

Проект включает:

- публичный Laravel-сайт;
- личный кабинет паломника;
- кабинет представителя храма и паломнической службы;
- административную панель;
- REST API;
- Flutter-приложение для Android и iOS.

## Стек

- Laravel 9;
- PHP 8.1+;
- MySQL 8;
- Laravel Sanctum;
- Blade + Bootstrap 5;
- MapLibre GL JS;
- MapLibre Flutter;
- OpenStreetMap / OpenMapTiles;
- Valhalla для маршрутизации;
- SQLite и MapLibre OfflineManager для мобильного офлайн-режима;
- Firebase Cloud Messaging;
- PWA Service Worker;
- Laravel database notifications;
- QR-билеты и проверка камерой.

## Картографическая архитектура

```text
Сайт                      MapLibre GL JS
Flutter                   MapLibre Flutter
Базовые данные            OpenStreetMap
Векторные тайлы           OpenMapTiles
Сервер тайлов             TileServer GL или совместимый поставщик
Растровые тайлы           прямой источник или серверный Laravel-кэш
Маршрутизация             Valhalla
Храмы и святыни           Laravel API + MySQL
Офлайн-карта приложения   MapLibre OfflineManager
Офлайн-карточки           SQLite
```

Единый стиль карты:

```text
GET /api/v1/map/style.json
```

Текущая конфигурация карты:

```text
GET /api/v1/map/config
```

Построение маршрута:

```text
POST /api/v1/map/route
```

Серверный маршрут растровых тайлов в режиме `cache`:

```text
GET /api/v1/map/tiles/{z}/{x}/{y}.png
```

Для разработки без OpenMapTiles используется резервный растровый слой. Массовая офлайн-загрузка отключена по умолчанию и включается только при собственном или лицензированном сервере тайлов.

Подробная дополнительная документация находится в:

```text
docs/maps-openstack.md
mobile/README.md
```

## Основные страницы сайта

```text
/                              главная
/map                           интерактивная MapLibre-карта
/objects                       каталог храмов и святынь
/objects/{slug}                карточка объекта
/routes                        каталог маршрутов
/routes/{slug}                 маршрут и ближайшие поездки
/calendar                      календарь событий
/calendar/{slug}               карточка события
/calendar/{slug}/ics           экспорт события в календарь
/community                     сообщество
/community/together            совместные паломничества
/community/together/{slug}     группа, заявки и обсуждение
/community/together/my         мои созданные группы и заявки
/community/{slug}              путевая заметка
/register                      регистрация паломника
/login                         вход паломника
/profile                       личный кабинет
/profile/favorites             избранное
/profile/bookings              бронирования и QR-билеты
/bookings/{id}/ticket          электронный QR-билет
/bookings/{id}/calendar.ics    поездка для календаря
/profile/achievements          достижения
/profile/activity              отзывы, посещения, блог и медиа
/profile/blocked-users         заблокированные пользователи
/profile/settings              профиль и настройки
/notifications                 центр уведомлений
/my-routes                     персональные маршруты
/privacy                       политика обработки данных
/terms                         правила использования
```

## Пользовательские функции

- регистрация и вход;
- хранение имени, email, телефона, аватара, даты рождения и интересов;
- фиксация согласия на обработку персональных данных;
- настройки уведомлений, приватности, темы и размера шрифта;
- статистика профиля;
- персональные списки избранного;
- отметка посещения с геолокацией;
- достижения;
- отзывы с модерацией;
- фото и видео с геометками;
- блог и путевые заметки;
- каталог маршрутов и расписание поездок;
- бронирование мест с контролем вместимости;
- защищённый QR-билет;
- отмена бронирования;
- экспорт событий и поездок в `.ics`;
- конструктор персонального маршрута;
- построение маршрутов через Valhalla;
- сохранение карточек для чтения без сети;
- совместные паломничества;
- заявки и управление участниками;
- закрытое обсуждение группы;
- жалобы и блокировка пользователей;
- внутренние и push-уведомления.

## Интерактивная карта

Сайт и Flutter используют MapLibre и общие источники данных.

Реализованы:

- маркеры храмов, монастырей, часовен и святых источников;
- цвета маркеров по типам объектов;
- кластеризация на сайте;
- поиск по названию, адресу и святыням;
- фильтры по типу, викариатству, благочинию и святыне;
- определение текущего местоположения;
- карточка объекта из маркера;
- построение пеших, автомобильных, велосипедных, автобусных и мультимодальных маршрутов;
- отображение расстояния и примерного времени;
- необязательные спутниковый и исторический растровые слои;
- единая атрибуция OpenStreetMap;
- серверное кэширование реально просмотренных растровых тайлов;
- переключение между серверным кэшем и прямой загрузкой тайлов;
- ограничение максимального размера серверного кэша;
- мобильные офлайн-регионы при разрешённой конфигурации сервера.

## Календарь событий

Поддерживаются:

- помесячный календарь;
- поиск и фильтры;
- богослужения;
- престольные праздники;
- крестные ходы;
- паломнические поездки;
- лекции и встречи;
- семейные и молодёжные мероприятия;
- благотворительные события;
- многодневные события;
- экспорт `.ics`;
- связь с храмом, маршрутом и поездкой.

Управление:

```text
/admin/calendar
/admin/calendar/create
```

## QR-билеты

При бронировании создаются:

- читаемый код `MP-...`;
- защищённый токен;
- QR-код;
- страница билета;
- печатная версия;
- экспорт поездки в календарь.

Проверка билетов:

```text
/service/tickets/scanner
```

Сканер фиксирует сотрудника, время проверки и количество прибывших участников, а также блокирует повторное использование билета.

## Кабинет представителя

```text
/service
/service/objects
/service/objects/{slug}/edit
/service/tickets/scanner
```

Представитель может:

- работать только с закреплёнными объектами;
- предлагать изменения карточки;
- обновлять расписание и контакты;
- добавлять медиаматериалы;
- отслеживать модерацию;
- проверять QR-билеты.

## Административная панель

```text
/admin/login
/admin
/admin/calendar
/admin/together
/admin/representatives
/admin/service-review
/admin/safety
```

В админке доступны:

- CRUD объектов и справочников;
- медиаматериалы;
- маршруты и поездки;
- бронирования и QR-билеты;
- календарь событий;
- достижения;
- посещения;
- модерация отзывов, блога и медиа;
- совместные паломничества;
- представители храмов;
- жалобы и блокировки;
- пользователи и аналитика.

## Установка Laravel

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Для Linux/macOS:

```bash
cp .env.example .env
```

Создайте базу MySQL `palomnik` и укажите параметры подключения в `.env`.

## Настройка карты и тайлов

### Приоритет источников карты

Настройки применяются в следующем порядке:

1. Если заполнен `MAP_STYLE_URL`, MapLibre использует внешний `style.json`. Источники и URL тайлов определяются этим внешним стилем, поэтому `MAP_TILE_MODE` может не влиять на карту.
2. Если `MAP_STYLE_URL` пуст, но заполнен `OPENMAPTILES_TILE_URL`, Laravel формирует внутренний стиль с векторными тайлами OpenMapTiles.
3. Если обе предыдущие настройки пусты, используется растровый источник `MAP_RASTER_TILE_URL` и выбранный режим `MAP_TILE_MODE`.

Для использования реализованного серверного кэша оставьте пустыми:

```env
MAP_STYLE_URL=
OPENMAPTILES_TILE_URL=
```

### Полный набор настроек карты

```env
# Внешний style.json. Если заполнен, имеет наивысший приоритет.
MAP_STYLE_URL=

# Векторные тайлы собственного или лицензированного OpenMapTiles-сервера.
OPENMAPTILES_TILE_URL=

# Шрифты для векторного стиля MapLibre.
MAP_GLYPHS_URL=

# Растровый источник, используемый в режимах cache и direct.
MAP_RASTER_TILE_URL=https://tile.openstreetmap.org/{z}/{x}/{y}.png

# cache — загружать через Laravel и сохранять на сервере;
# direct — загружать напрямую в браузере с MAP_RASTER_TILE_URL.
MAP_TILE_MODE=cache

# Настройки серверного кэша. Используются только при MAP_TILE_MODE=cache.
MAP_TILE_CACHE_DISK=local
MAP_TILE_CACHE_DIRECTORY=map-tiles/osm
MAP_TILE_CACHE_MAX_SIZE_MB=1024
MAP_TILE_DEFAULT_TTL=604800
MAP_TILE_BROWSER_TTL=86400
MAP_TILE_CONNECT_TIMEOUT=5
MAP_TILE_TIMEOUT=15
MAP_TILE_MAX_ZOOM=19
MAP_TILE_USER_AGENT=

# Дополнительные необязательные растровые слои.
MAP_SATELLITE_TILE_URL=
MAP_HISTORIC_TILE_URL=

# Обязательная атрибуция источника карты.
MAP_ATTRIBUTION="© OpenStreetMap contributors"

# Мобильные офлайн-регионы.
MAP_OFFLINE_ENABLED=false
MAP_OFFLINE_TILE_LIMIT=100000

# Маршрутизация.
VALHALLA_URL=https://valhalla.openstreetmap.de
VALHALLA_TIMEOUT=20
```

### Описание переменных

| Переменная | Значение и назначение |
|---|---|
| `MAP_STYLE_URL` | URL готового внешнего `style.json`. Имеет приоритет над внутренним стилем Laravel. |
| `OPENMAPTILES_TILE_URL` | Шаблон URL векторных `.pbf`-тайлов, например `https://maps.example/data/v3/{z}/{x}/{y}.pbf`. |
| `MAP_GLYPHS_URL` | Шаблон URL шрифтов MapLibre, например `https://maps.example/fonts/{fontstack}/{range}.pbf`. |
| `MAP_RASTER_TILE_URL` | Шаблон растрового источника. По умолчанию `https://tile.openstreetmap.org/{z}/{x}/{y}.png`. |
| `MAP_TILE_MODE` | Режим растровых тайлов: только `cache` или `direct`. При неизвестном значении используется `cache`. |
| `MAP_TILE_CACHE_DISK` | Laravel Filesystem disk для хранения тайлов. По умолчанию `local`. |
| `MAP_TILE_CACHE_DIRECTORY` | Каталог внутри выбранного диска. По умолчанию `map-tiles/osm`. |
| `MAP_TILE_CACHE_MAX_SIZE_MB` | Максимальный размер серверного кэша в мегабайтах. `1024` = 1 ГБ, `0` = без ограничения. |
| `MAP_TILE_DEFAULT_TTL` | Срок актуальности тайла в секундах, если внешний источник не прислал `Cache-Control` или `Expires`. По умолчанию 604800 секунд, то есть 7 суток. |
| `MAP_TILE_BROWSER_TTL` | Время хранения локального URL тайла браузером. По умолчанию 86400 секунд, то есть 1 сутки. |
| `MAP_TILE_CONNECT_TIMEOUT` | Максимальное время установки соединения с источником тайлов в секундах. |
| `MAP_TILE_TIMEOUT` | Полный тайм-аут запроса одного тайла в секундах. |
| `MAP_TILE_MAX_ZOOM` | Максимальный допустимый масштаб растровых тайлов. По умолчанию 19. |
| `MAP_TILE_USER_AGENT` | Идентификатор и контакт сервера при обращении к внешнему поставщику тайлов. Для production рекомендуется указать реальный домен и email. |
| `MAP_SATELLITE_TILE_URL` | Необязательный лицензированный спутниковый слой. |
| `MAP_HISTORIC_TILE_URL` | Необязательный исторический слой с геопривязанными тайлами. |
| `MAP_ATTRIBUTION` | Текст атрибуции, отображаемый на карте. |
| `MAP_OFFLINE_ENABLED` | Разрешает создание мобильных офлайн-регионов. Не включать для публичного сервера OpenStreetMap. |
| `MAP_OFFLINE_TILE_LIMIT` | Максимальное число тайлов мобильного офлайн-региона. |
| `VALHALLA_URL` | Адрес сервера построения маршрутов Valhalla. |
| `VALHALLA_TIMEOUT` | Тайм-аут обращения к Valhalla в секундах. |

Старая переменная `MAP_TILE_CACHE_ENABLED` поддерживается только для обратной совместимости, когда `MAP_TILE_MODE` ещё не задан. Для новой конфигурации используйте `MAP_TILE_MODE`.

### Режим `cache`

Настройка:

```env
MAP_TILE_MODE=cache
MAP_RASTER_TILE_URL=https://tile.openstreetmap.org/{z}/{x}/{y}.png
MAP_TILE_CACHE_MAX_SIZE_MB=1024
```

Схема запроса:

```text
MapLibre в браузере
        ↓
/api/v1/map/tiles/{z}/{x}/{y}.png
        ↓
storage/app/map-tiles/osm
        ↓ только при отсутствии или обновлении
https://tile.openstreetmap.org/{z}/{x}/{y}.png
```

Порядок работы:

1. Браузер запрашивает тайл только у Laravel-сервера.
2. Laravel проверяет наличие тайла в локальном хранилище.
3. Свежий тайл немедленно отдаётся с диска.
4. При отсутствии тайл загружается с `MAP_RASTER_TILE_URL`, отдаётся пользователю и сохраняется на сервере.
5. Устаревший тайл перепроверяется через `ETag` или `Last-Modified`, если источник передал эти заголовки.
6. Если внешний источник временно недоступен, но локальный тайл существует, пользователю отдаётся старая копия.
7. Предварительная массовая загрузка региона не выполняется: сохраняются только реально запрошенные тайлы.

Файлы по умолчанию сохраняются так:

```text
storage/app/map-tiles/osm/10/619/319.png
storage/app/map-tiles/osm/10/619/319.png.json
storage/app/map-tiles/osm/.cache-size.json
```

Назначение файлов:

- `.png` — изображение тайла;
- `.png.json` — метаданные тайла: время загрузки, срок актуальности, `ETag`, `Last-Modified`, исходный URL;
- `.cache-size.json` — служебный индекс текущего размера кэша, чтобы не пересчитывать все файлы при каждом запросе.

### Ограничение размера кэша

```env
MAP_TILE_CACHE_MAX_SIZE_MB=1024
```

Значение задаётся в мегабайтах:

```text
256   = 256 МБ
512   = 512 МБ
1024  = 1 ГБ
2048  = 2 ГБ
0     = без ограничения
```

При достижении лимита:

- уже сохранённые тайлы продолжают отдаваться с диска;
- новые тайлы продолжают загружаться через сервер и отображаться пользователю;
- новые тайлы не записываются на диск;
- старые файлы автоматически не удаляются;
- размер каталога не должен увеличиваться сверх лимита из-за новых тайлов.

Для полного освобождения места кэш можно удалить вручную.

Linux:

```bash
rm -rf storage/app/map-tiles/osm
```

Windows PowerShell:

```powershell
Remove-Item -Recurse -Force storage/app/map-tiles/osm
```

Каталог и служебный индекс будут созданы заново при следующем запросе карты.

### Диагностика серверного кэша

Laravel добавляет к ответу заголовок `X-Map-Tile-Cache`.

| Заголовок | Значение |
|---|---|
| `HIT` | Свежий тайл получен из локального кэша. |
| `MISS` | Тайла не было; он скачан и сохранён. |
| `REFRESH` | Устаревший тайл скачан заново и заменён. |
| `REVALIDATED` | Источник подтвердил, что локальная копия не изменилась. |
| `STALE` | Источник недоступен; отдана старая локальная копия. |
| `BYPASS-LIMIT` | Тайл получен, но не сохранён из-за лимита размера кэша. |
| `REVALIDATED-LIMIT` | Тайл подтверждён источником, но метаданные не перезаписаны из-за лимита. |
| `ERROR` | Тайла нет в кэше и получить его у источника не удалось. |

Проверка конкретного тайла:

```bash
curl -I https://your-domain.example/api/v1/map/tiles/10/619/319.png
```

Проверка активного режима:

```bash
curl https://your-domain.example/api/v1/map/config
```

В режиме `cache` API возвращает:

```json
{
  "data": {
    "provider": "server-cached-raster",
    "tile_mode": "cache",
    "tile_cache_enabled": true
  }
}
```

### Режим `direct`

Настройка:

```env
MAP_TILE_MODE=direct
MAP_RASTER_TILE_URL=https://tile.openstreetmap.org/{z}/{x}/{y}.png
```

В этом режиме внутренний `style.json` передаёт MapLibre внешний URL тайлов:

```text
https://tile.openstreetmap.org/{z}/{x}/{y}.png
```

Особенности режима:

- браузер обращается напрямую к `MAP_RASTER_TILE_URL`;
- Laravel не скачивает и не сохраняет растровые тайлы;
- `MAP_TILE_CACHE_MAX_SIZE_MB` и остальные параметры серверного кэша не используются;
- ранее сохранённые файлы кэша не удаляются;
- серверный маршрут `/api/v1/map/tiles/...` отключён и возвращает `404`;
- доступность карты зависит от доступности внешнего источника для каждого пользователя.

В режиме `direct` API `/api/v1/map/config` возвращает:

```json
{
  "data": {
    "provider": "direct-raster",
    "tile_mode": "direct",
    "tile_cache_enabled": false
  }
}
```

### Быстрое переключение режима

Включить серверный кэш:

```env
MAP_TILE_MODE=cache
MAP_TILE_CACHE_MAX_SIZE_MB=1024
```

Переключить на прямую загрузку:

```env
MAP_TILE_MODE=direct
```

После каждого изменения `.env` обязательно выполните:

```bash
php artisan optimize:clear
```

Если используется production-кэш конфигурации, затем можно снова выполнить:

```bash
php artisan config:cache
```

Переключение режима не удаляет ранее сохранённые тайлы.

### Production-настройка `User-Agent`

Для серверного режима рекомендуется указать реальный домен и контактный адрес:

```env
MAP_TILE_USER_AGENT="MoscowPilgrim/1.0 (+https://palomnik.example; admin@palomnik.example)"
```

Не оставляйте в production тестовый домен или несуществующую почту.

### Векторные тайлы OpenMapTiles

Для собственного TileServer GL:

```env
MAP_STYLE_URL=
OPENMAPTILES_TILE_URL=https://maps.example/data/v3/{z}/{x}/{y}.pbf
MAP_GLYPHS_URL=https://maps.example/fonts/{fontstack}/{range}.pbf
MAP_ATTRIBUTION="© OpenStreetMap contributors"
```

Когда `OPENMAPTILES_TILE_URL` заполнен, внутренний стиль использует векторные тайлы. Параметр `MAP_TILE_MODE` относится только к резервному растровому режиму и не переключает OpenMapTiles на растровый кэш.

Для production рекомендуется собственный или лицензированный сервер тайлов.

### Мобильные офлайн-регионы

```env
MAP_OFFLINE_ENABLED=true
MAP_OFFLINE_TILE_LIMIT=100000
```

Офлайн-регионы нельзя включать при использовании публичного `tile.openstreetmap.org`. Эта возможность предназначена для собственного или лицензированного источника тайлов, правила которого разрешают офлайн-загрузку.

### Необязательные слои

```env
MAP_SATELLITE_TILE_URL=
MAP_HISTORIC_TILE_URL=
```

Используйте только источники, лицензия которых разрешает показ и необходимый способ кэширования.

## Flutter-приложение

Исходный код находится в `mobile`.

```bash
cd mobile
flutter create . --platforms=android,ios --org ru.mospalomnik
flutter pub get
flutter analyze
flutter test
flutter run
```

Для физического телефона задайте адрес сервера:

```bash
flutter run \
  --dart-define=API_BASE_URL=http://192.168.1.100:8000/api/v1 \
  --dart-define=SITE_BASE_URL=http://192.168.1.100:8000
```

Подробнее: `mobile/README.md`.

## Firebase push

```env
FIREBASE_PUSH_ENABLED=false
FIREBASE_PROJECT_ID=
FIREBASE_SERVICE_ACCOUNT_PATH=storage/app/firebase/service-account.json
```

Мобильные файлы:

```text
mobile/android/app/google-services.json
mobile/ios/Runner/GoogleService-Info.plist
```

## Обновление проекта

```bash
git pull origin main
composer install
composer dump-autoload
php artisan optimize:clear
php artisan migrate --seed
php artisan storage:link
php artisan test
```

Flutter:

```bash
cd mobile
flutter pub get
flutter analyze
flutter test
```

## API v1

```text
GET  /api/v1/health
GET  /api/v1/map/config
GET  /api/v1/map/style.json
GET  /api/v1/map/tiles/{z}/{x}/{y}.png    только MAP_TILE_MODE=cache
POST /api/v1/map/route
GET  /api/v1/directories/object-types
GET  /api/v1/directories/vicariates
GET  /api/v1/directories/deaneries
GET  /api/v1/directories/sanctities
GET  /api/v1/objects
GET  /api/v1/objects/{slug}
POST /api/v1/auth/register
POST /api/v1/auth/login
GET  /api/v1/auth/me                    auth:sanctum
```

Мобильные пользовательские endpoints находятся под:

```text
/api/v1/mobile/*
```

## Что требует внешней интеграции

- production OpenMapTiles/TileServer;
- production Valhalla;
- лицензированный спутниковый слой;
- подготовленные геопривязанные исторические тайлы;
- реальная платёжная система и онлайн-касса;
- утверждённые юридические документы;
- Firebase-проект для внешних push-уведомлений.

## Тесты

```bash
php artisan test
```

```bash
cd mobile
flutter analyze
flutter test
```

Разработка ведётся в ветке `main`.
