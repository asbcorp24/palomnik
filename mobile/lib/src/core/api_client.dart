import 'package:dio/dio.dart';

class ApiClient {
  ApiClient._();

  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );

  static const siteBaseUrl = String.fromEnvironment(
    'SITE_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000',
  );

  static final ApiClient instance = ApiClient._();

  final Dio dio = Dio(
    BaseOptions(
      baseUrl: apiBaseUrl,
      connectTimeout: const Duration(seconds: 20),
      receiveTimeout: const Duration(seconds: 30),
      headers: const {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ),
  );

  void setToken(String? token) {
    if (token == null || token.isEmpty) {
      dio.options.headers.remove('Authorization');
      return;
    }
    dio.options.headers['Authorization'] = 'Bearer $token';
  }

  String messageFrom(Object error) {
    if (error is DioException) {
      final data = error.response?.data;
      if (data is Map<String, dynamic>) {
        final message = data['message'];
        if (message is String && message.isNotEmpty) return message;
        final errors = data['errors'];
        if (errors is Map) {
          final messages = <String>[];
          for (final value in errors.values) {
            if (value is List) messages.addAll(value.map((item) => '$item'));
          }
          if (messages.isNotEmpty) return messages.join('\n');
        }
      }
      if (error.type == DioExceptionType.connectionError ||
          error.type == DioExceptionType.connectionTimeout) {
        return 'Не удалось подключиться к серверу.';
      }
    }
    return 'Произошла ошибка. Повторите попытку.';
  }
}
