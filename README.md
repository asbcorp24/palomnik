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
Маршрутизация             Valhalla
Храмы и святыни           Laravel API + MySQL
Офлайн-карта приложения   MapLibre OfflineManager
Офлайн-карточки            SQLite
```

Единый стиль карты:

```text
GET /api/v1/map/style.json
```

Возможности картографического сервера:

```text
GET /api/v1/map/config
```

Построение маршрута:

```text
POST /api/v1/map/route
```

Для разработки без OpenMapTiles используется резервный растровый слой. Массовая офлайн-загрузка отключена по умолчанию и включается только при собственном или лицензированном сервере тайлов.

Подробная настройка находится в:

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

## Настройка MapLibre и OpenMapTiles

Минимальный режим разработки работает с резервным растровым слоем.

Для production укажите:

```env
MAP_STYLE_URL=
OPENMAPTILES_TILE_URL=https://maps.example/data/v3/{z}/{x}/{y}.pbf
MAP_GLYPHS_URL=https://maps.example/fonts/{fontstack}/{range}.pbf
MAP_ATTRIBUTION="© OpenStreetMap contributors"
VALHALLA_URL=https://route.example
VALHALLA_TIMEOUT=20
```

Для разрешения мобильных офлайн-регионов:

```env
MAP_OFFLINE_ENABLED=true
MAP_OFFLINE_TILE_LIMIT=100000
```

Эту настройку нельзя включать при использовании публичного `tile.openstreetmap.org`.

Необязательные слои:

```env
MAP_SATELLITE_TILE_URL=
MAP_HISTORIC_TILE_URL=
```

После изменения `.env`:

```bash
php artisan optimize:clear
```

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
