import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'api_client.dart';
import 'push_service.dart';
import 'user_access.dart';

class SessionController extends ChangeNotifier {
  static const _tokenKey = 'auth_token';
  static const _userKey = 'auth_user';
  static const _storage = FlutterSecureStorage();

  final ApiClient api = ApiClient.instance;

  String? _token;
  Map<String, dynamic>? _user;
  bool _restoring = true;

  bool get isAuthenticated =>
      _token != null && _token!.isNotEmpty && _user != null;
  bool get isRestoring => _restoring;
  Map<String, dynamic>? get user => _user;
  UserAccess get access => UserAccess.fromUser(
        _user ?? const <String, dynamic>{},
        siteBaseUrl: ApiClient.siteBaseUrl,
      );

  Future<void> restore() async {
    _token = await _storage.read(key: _tokenKey);
    _user = await _readStoredUser();
    api.setToken(_token);

    if (_token != null && _token!.isNotEmpty) {
      try {
        final response = await api.dio.get('/auth/me');
        await _setUser(
          Map<String, dynamic>.from(response.data['user'] as Map),
        );
      } catch (error) {
        if (_isUnauthorized(error) || _user == null) {
          await _clearSession(notify: false);
        }
      }
    }

    if (isAuthenticated) {
      await PushService.instance.initialize();
    }

    _restoring = false;
    notifyListeners();
  }

  Future<void> login({required String email, required String password}) async {
    final response = await api.dio.post('/auth/login', data: {
      'email': email.trim(),
      'password': password,
      'device_name': 'Flutter mobile',
    });
    await _acceptAuthResponse(response.data as Map);
  }

  Future<void> register({
    required String name,
    required String email,
    required String phone,
    required String password,
  }) async {
    final response = await api.dio.post('/auth/register', data: {
      'name': name.trim(),
      'email': email.trim(),
      'phone': phone.trim().isEmpty ? null : phone.trim(),
      'password': password,
      'password_confirmation': password,
      'consent': true,
      'device_name': 'Flutter mobile',
    });
    await _acceptAuthResponse(response.data as Map);
  }

  Future<void> refreshProfile() async {
    if (!isAuthenticated) return;
    final response = await api.dio.get('/mobile/profile');
    await _setUser(
      Map<String, dynamic>.from(response.data['user'] as Map),
    );
    notifyListeners();
  }

  Future<void> logout() async {
    try {
      if (isAuthenticated) {
        await PushService.instance.unregister();
        await api.dio.post('/auth/logout');
      }
    } finally {
      await _clearSession();
    }
  }

  Future<void> _acceptAuthResponse(Map response) async {
    _token = response['token'] as String;
    await _setUser(Map<String, dynamic>.from(response['user'] as Map));
    await _storage.write(key: _tokenKey, value: _token);
    api.setToken(_token);
    await PushService.instance.initialize();
    notifyListeners();
  }

  Future<void> _setUser(Map<String, dynamic> value) async {
    _user = value;
    await _storage.write(key: _userKey, value: jsonEncode(value));
  }

  Future<Map<String, dynamic>?> _readStoredUser() async {
    final encoded = await _storage.read(key: _userKey);
    if (encoded == null || encoded.isEmpty) return null;

    try {
      final decoded = jsonDecode(encoded);
      return decoded is Map
          ? Map<String, dynamic>.from(decoded)
          : null;
    } catch (_) {
      await _storage.delete(key: _userKey);
      return null;
    }
  }

  Future<void> _clearSession({bool notify = true}) async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _userKey);
    _token = null;
    _user = null;
    api.setToken(null);
    if (notify) notifyListeners();
  }

  bool _isUnauthorized(Object error) {
    return error is DioException &&
        (error.response?.statusCode == 401 ||
            error.response?.statusCode == 419);
  }
}
