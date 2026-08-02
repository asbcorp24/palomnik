# Мобильное приложение «Московский паломник»

Flutter-приложение для Android и iOS. Оно использует тот же Laravel API и ту же базу данных, что и основной сайт. Административные и рабочие операции выполняются в защищённых веб-панелях, ссылки на которые мобильное приложение показывает в соответствии с ролью пользователя.

Текущая версия приложения: `0.4.0+4`.

## Реализовано

- регистрация и вход через Laravel Sanctum;
- безопасное хранение токена и профиля пользователя;
- восстановление сессии при временной потере сети;
- обновление роли и разрешений из Laravel API;
- отображение роли, её описания и статуса подтверждения email;
- ссылки только на разрешённые рабочие кабинеты сотрудников;
- главная страница, каталог храмов и подробные карточки;
- фотографии святынь, иерархия монастырей и храмов, места рядом;
- статус актуальности информации об объекте;
- нативная карта MapLibre;
- единый стиль OpenMapTiles для сайта и приложения;
- собственные маркеры храмов, монастырей, часовен и точек интереса;
- поиск объектов на карте;
- определение местоположения;
- построение маршрутов через Laravel-прокси к Valhalla;
- пешие, автомобильные, велосипедные, автобусные и мультимодальные маршруты;
- сохранение карточек объектов в SQLite;
- загрузка офлайн-регионов MapLibre при подключённом лицензированном TileServer;
- календарь событий;
- каталог маршрутов и бронирование поездок;
- QR-билеты;
- сообщество и «Паломничество вместе»;
- закрытый групповой чат;
- отзывы и отметки посещений с геолокацией;
- избранное;
- достижения и статистика;
- конструктор персональных маршрутов;
- путевые заметки;
- паломнические фотографии и видео;
- Firebase push-уведомления после добавления конфигурационных файлов Firebase;
- светлая, тёмная и системная темы.

## Роли и рабочие кабинеты

Laravel передаёт мобильному приложению:

```text
role
role_label
role_description
capabilities
workspaces
```

Приложение не определяет права самостоятельно и не показывает универсальную административную панель. Сервер формирует разрешённые кабинеты для конкретного пользователя.

### Паломник

Использует каталог, карту, маршруты, бронирования, QR-билеты, сообщество, фотографии и личный кабинет. Рабочие панели не отображаются.

### Редактор объектов

Дополнительно получает ссылку на кабинет представителя объекта. В нём доступны только закреплённые храмы и монастыри, заявки на изменение сведений и загрузка материалов.

### Паломническая служба

Получает ссылки на рабочий кабинет объектов и панель паломнической службы: маршруты, поездки, CRM заявок, участники и проверка QR-билетов.

### Модератор

Получает ссылку на панель модератора: публикации, фотографии, посещения, совместные паломничества, жалобы и изменения от представителей объектов.

### Администратор

Получает панель управления каталогом, маршрутами, CRM, модерацией, обычными пользователями и журналом действий.

### Главный администратор

Получает полный доступ, включая управление ролями администраторов и системными настройками.

После изменения роли на сервере пользователь нажимает кнопку обновления в мобильном профиле. При следующем запуске приложение также автоматически получает актуальную роль и разрешения.

## Картографический стек

```text
Flutter UI              MapLibre Flutter
Векторные данные        OpenMapTiles
Сервер тайлов           собственный TileServer GL или совместимый провайдер
Маршрутизация           Valhalla
Объекты платформы       Laravel API + MySQL
Офлайн-регионы          MapLibre OfflineManager
Офлайн-карточки         SQLite
```

По умолчанию приложение получает стиль карты с Laravel:

```text
GET /api/v1/map/style.json
```

Информация о доступности офлайн-карт:

```text
GET /api/v1/map/config
```

Маршрутизация:

```text
POST /api/v1/map/route
```

## Требования

- Flutter stable с Dart 3.10 или новее;
- Android 7 / API 24 или новее;
- iOS 13 или новее;
- Laravel-сервер с HTTPS для рабочей версии;
- для полноценной production-карты — собственный или лицензированный сервер векторных тайлов;
- для офлайн-пакетов — `MAP_OFFLINE_ENABLED=true` только после подключения такого сервера.

## Создание Android и iOS проектов

Платформенные папки создаются один раз установленным Flutter SDK:

```bash
cd mobile
flutter create . --platforms=android,ios --org ru.mospalomnik
flutter pub get
```

После `flutter create` проверьте, что существующие `lib`, `pubspec.yaml` и тесты не были заменены.

## Android

В `android/app/src/main/AndroidManifest.xml` нужны разрешения:

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
<uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
```

Для локальной разработки по HTTP временно добавьте в `<application>`:

```xml
android:usesCleartextTraffic="true"
```

В release-сборке используйте HTTPS и удалите разрешение cleartext-трафика.

Для Firebase положите:

```text
android/app/google-services.json
```

## iOS

В `ios/Runner/Info.plist` добавьте описание геолокации:

```xml
<key>NSLocationWhenInUseUsageDescription</key>
<string>Геолокация используется для показа пользователя на карте, построения маршрута и подтверждения посещения.</string>
```

Для Firebase положите:

```text
ios/Runner/GoogleService-Info.plist
```

Для локального HTTP потребуется временное исключение App Transport Security. В рабочей версии используйте HTTPS.

## Запуск Laravel

В корне проекта:

```bash
php artisan optimize:clear
php artisan migrate --seed
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
```

## Android Emulator

```bash
cd mobile
flutter run \
  --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1 \
  --dart-define=SITE_BASE_URL=http://10.0.2.2:8000
```

Стиль будет загружен автоматически с:

```text
http://10.0.2.2:8000/api/v1/map/style.json
```

## Физический Android-телефон

```bash
flutter run \
  --dart-define=API_BASE_URL=http://192.168.1.100:8000/api/v1 \
  --dart-define=SITE_BASE_URL=http://192.168.1.100:8000
```

Телефон и компьютер должны находиться в одной сети. Windows Firewall должен разрешать порт `8000`.

## Рабочий сервер

```bash
flutter run \
  --dart-define=API_BASE_URL=https://palom.specdpo.ru/api/v1 \
  --dart-define=SITE_BASE_URL=https://palom.specdpo.ru
```

`SITE_BASE_URL` используется в том числе для открытия разрешённых рабочих кабинетов сотрудников.

## Внешний style.json

Вместо Laravel-стиля можно передать URL напрямую:

```bash
flutter run \
  --dart-define=API_BASE_URL=https://palomnik.example/api/v1 \
  --dart-define=SITE_BASE_URL=https://palomnik.example \
  --dart-define=MAP_STYLE_URL=https://maps.example/styles/palomnik/style.json
```

## Проверка проекта

```bash
flutter pub get
flutter analyze
flutter test
```

## Release Android

```bash
flutter build appbundle --release \
  --dart-define=API_BASE_URL=https://palom.specdpo.ru/api/v1 \
  --dart-define=SITE_BASE_URL=https://palom.specdpo.ru
```

APK для внутреннего тестирования:

```bash
flutter build apk --release \
  --dart-define=API_BASE_URL=https://palom.specdpo.ru/api/v1 \
  --dart-define=SITE_BASE_URL=https://palom.specdpo.ru
```

## Важные ограничения

- публичный `tile.openstreetmap.org` используется только как резервный слой разработки;
- офлайн-загрузка отключена по умолчанию, чтобы приложение не скачивало публичные OSM-тайлы массово;
- для production и офлайн-карт нужно подключить OpenMapTiles/TileServer и установить `MAP_OFFLINE_ENABLED=true`;
- демонстрационный публичный Valhalla подходит для разработки, но рабочий сервис должен использовать собственный или гарантированный маршрутизатор;
- старый неиспользуемый Yandex MapKit-класс пока сохранён в исходниках совместимости и не подключён к нижней навигации; после успешной сборки и проверки MapLibre его можно удалить вместе с зависимостью `yandex_mapkit`;
- административные формы не дублируются в Flutter: приложение открывает защищённую веб-панель, а Laravel повторно проверяет роль на каждом запросе;
- реальные платежи пока не подключены.
