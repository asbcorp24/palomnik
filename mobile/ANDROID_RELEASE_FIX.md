# Android release: WorkManager / Room startup crash

Если release APK падает до запуска Flutter со стеком вида:

```text
Unable to get provider androidx.startup.InitializationProvider
Failed to create an instance of class androidx.work.impl.WorkDatabase
```

это связано с R8/minification Android release-сборки: WorkManager использует Room, а сгенерированный `WorkDatabase_Impl` создаётся косвенно при AndroidX Startup.

В репозитории платформенные `android/` и `ios/` каталоги создаются локально через `flutter create`, поэтому после создания Android-проекта нужно один раз применить release guard:

```bash
cd mobile
flutter create . --platforms=android,ios --org ru.mospalomnik
flutter pub get
dart run tool/configure_android_release.dart
```

Скрипт:

- создаёт/дополняет `android/app/proguard-rules.pro`;
- сохраняет Room database implementations и `WorkDatabase_Impl` от удаления/поломки R8;
- сохраняет WorkManager / AndroidX Startup implementation classes;
- автоматически подключает `proguard-rules.pro` к release-блоку как для `build.gradle.kts`, так и для старого `build.gradle`.

После изменения обязательно пересобрать APK с очисткой старых результатов:

```bash
flutter clean
flutter pub get
dart run tool/configure_android_release.dart
flutter build apk --release \
  --dart-define=API_BASE_URL=https://mospalom.ru/api/v1 \
  --dart-define=SITE_BASE_URL=https://mospalom.ru
```

Перед установкой новой сборки рекомендуется удалить старую APK-версию с телефона, если она уже падала на старте.

Для AAB используется та же подготовка:

```bash
dart run tool/configure_android_release.dart
flutter build appbundle --release \
  --dart-define=API_BASE_URL=https://mospalom.ru/api/v1 \
  --dart-define=SITE_BASE_URL=https://mospalom.ru
```
