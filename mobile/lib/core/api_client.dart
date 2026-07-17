import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:moscow_pilgrim/core/app_config.dart';

class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode, this.fields});

  final String message;
  final int? statusCode;
  final Map<String, List<String>>? fields;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient._()
      : _dio = Dio(
          BaseOptions(
            baseUrl: AppConfig.apiBaseUrl,
            connectTimeout: const Duration(seconds: 20),
            receiveTimeout: const Duration(seconds: 30),
            sendTimeout: const Duration(seconds: 30),
            headers: const {
              Headers.acceptHeader: Headers.jsonContentType,
            },
          ),
        ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _storage.read(key: _tokenKey);
          if (token != null && token.isNotEmpty) {
            options.headers[HttpHeaders.authorizationHeader] = 'Bearer $token';
          }
          handler.next(options);
        },
        onError: (error, handler) {
          if (kDebugMode) {
            debugPrint('API ${error.requestOptions.method} ${error.requestOptions.uri}: ${error.message}');
          }
          handler.next(error);
        },
      ),
    );
  }

  static final ApiClient instance = ApiClient._();
  static const _tokenKey = 'palomnik_api_token';

  final Dio _dio;
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  Future<bool> hasToken() async {
    final token = await _storage.read(key: _tokenKey);
    return token != null && token.isNotEmpty;
  }

  Future<void> saveToken(String token) => _storage.write(key: _tokenKey, value: token);

  Future<void> clearToken() => _storage.delete(key: _tokenKey);

  Future<Map<String, dynamic>> getJson(
    String path, {
    Map<String, dynamic>? query,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        path,
        queryParameters: query,
      );
      return response.data ?? <String, dynamic>{};
    } on DioException catch (error) {
      throw _mapError(error);
    }
  }

  Future<Map<String, dynamic>> postJson(
    String path, {
    Object? data,
    Map<String, dynamic>? query,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        path,
        data: data,
        queryParameters: query,
      );
      return response.data ?? <String, dynamic>{};
    } on DioException catch (error) {
      throw _mapError(error);
    }
  }

  Future<Map<String, dynamic>> putJson(String path, {Object? data}) async {
    try {
      final response = await _dio.put<Map<String, dynamic>>(path, data: data);
      return response.data ?? <String, dynamic>{};
    } on DioException catch (error) {
      throw _mapError(error);
    }
  }

  Future<Map<String, dynamic>> deleteJson(String path, {Object? data}) async {
    try {
      final response = await _dio.delete<Map<String, dynamic>>(path, data: data);
      return response.data ?? <String, dynamic>{};
    } on DioException catch (error) {
      throw _mapError(error);
    }
  }

  Future<Map<String, dynamic>> upload(
    String path, {
    required Map<String, dynamic> fields,
    File? file,
    String fileField = 'avatar',
  }) async {
    try {
      final data = Map<String, dynamic>.from(fields);
      if (file != null) {
        data[fileField] = await MultipartFile.fromFile(
          file.path,
          filename: file.uri.pathSegments.last,
        );
      }
      final response = await _dio.post<Map<String, dynamic>>(
        path,
        data: FormData.fromMap(data),
      );
      return response.data ?? <String, dynamic>{};
    } on DioException catch (error) {
      throw _mapError(error);
    }
  }

  ApiException _mapError(DioException error) {
    final response = error.response;
    final data = response?.data;
    final fields = <String, List<String>>{};
    String message = 'Не удалось выполнить запрос. Проверьте подключение к интернету.';

    if (data is Map) {
      final rawMessage = data['message'];
      if (rawMessage is String && rawMessage.trim().isNotEmpty) {
        message = rawMessage;
      }
      final errors = data['errors'];
      if (errors is Map) {
        for (final entry in errors.entries) {
          final value = entry.value;
          if (value is List) {
            fields[entry.key.toString()] = value.map((item) => item.toString()).toList();
          } else if (value != null) {
            fields[entry.key.toString()] = [value.toString()];
          }
        }
        if (fields.isNotEmpty) {
          message = fields.values.first.first;
        }
      }
    }

    if (error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout ||
        error.type == DioExceptionType.sendTimeout) {
      message = 'Сервер не ответил вовремя.';
    } else if (error.type == DioExceptionType.connectionError) {
      message = 'Нет соединения с сервером ${AppConfig.apiBaseUrl}.';
    }

    return ApiException(
      message,
      statusCode: response?.statusCode,
      fields: fields.isEmpty ? null : fields,
    );
  }
}
