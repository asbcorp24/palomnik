import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';

class ApiClient {
  ApiClient._() {
    dio.interceptors.add(
      InterceptorsWrapper(
        onResponse: (response, handler) async {
          if (_canCache(response.requestOptions) && response.data != null) {
            final preferences = await SharedPreferences.getInstance();
            await preferences.setString(_cacheKey(response.requestOptions), jsonEncode(response.data));
            await preferences.setInt('${_cacheKey(response.requestOptions)}:saved_at', DateTime.now().millisecondsSinceEpoch);
          }
          handler.next(response);
        },
        onError: (error, handler) async {
          if (_canCache(error.requestOptions) && _isConnectionError(error)) {
            final preferences = await SharedPreferences.getInstance();
            final cached = preferences.getString(_cacheKey(error.requestOptions));
            if (cached != null) {
              handler.resolve(
                Response<dynamic>(
                  requestOptions: error.requestOptions,
                  data: jsonDecode(cached),
                  statusCode: 200,
                  statusMessage: 'Offline cache',
                  extra: const {'offline_cache': true},
                ),
              );
              return;
            }
          }
          handler.next(error);
        },
      ),
    );
  }

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
      if (_isConnectionError(error)) {
        return 'Не удалось подключиться к серверу. Ранее загруженные публичные данные доступны офлайн.';
      }
    }
    return 'Произошла ошибка. Повторите попытку.';
  }

  bool _canCache(RequestOptions options) {
    if (options.method.toUpperCase() != 'GET') return false;
    final path = options.path;
    return path == '/objects' ||
        path.startsWith('/objects/') ||
        path == '/mobile/home' ||
        path.startsWith('/mobile/routes') ||
        path.startsWith('/mobile/calendar') ||
        path.startsWith('/mobile/community') ||
        path.startsWith('/mobile/together');
  }

  bool _isConnectionError(DioException error) {
    return error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout ||
        error.type == DioExceptionType.sendTimeout;
  }

  String _cacheKey(RequestOptions options) {
    final query = options.queryParameters.entries.toList()
      ..sort((a, b) => a.key.compareTo(b.key));
    final suffix = query.map((entry) => '${entry.key}=${entry.value}').join('&');
    return 'api-cache:${options.path}${suffix.isEmpty ? '' : '?$suffix'}';
  }
}
