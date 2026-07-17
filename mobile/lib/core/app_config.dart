class AppConfig {
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );

  static const String websiteBaseUrl = String.fromEnvironment(
    'WEBSITE_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000',
  );

  static const String yandexMapKitApiKey = String.fromEnvironment(
    'YANDEX_MAPKIT_API_KEY',
    defaultValue: '',
  );
}
