import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'api_client.dart';

class SessionController extends ChangeNotifier {
  static const _tokenKey = 'auth_token';
  static const _storage = FlutterSecureStorage();

  final ApiClient api = ApiClient.instance;

  String? _token;
  Map<String, dynamic>? _user;
  bool _restoring = true;

  bool get isAuthenticated => _token != null && _token!.isNotEmpty;
  bool get isRestoring => _restoring;
  Map<String, dynamic>? get user => _user;

  Future<void> restore() async {
    _token = await _storage.read(key: _tokenKey);
    api.setToken(_token);

    if (isAuthenticated) {
      try {
        final response = await api.dio.get('/auth/me');
        _user = Map<String, dynamic>.from(response.data['user'] as Map);
      } catch (_) {
        await _storage.delete(key: _tokenKey);
        _token = null;
        _user = null;
        api.setToken(null);
      }
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
    _user = Map<String, dynamic>.from(response.data['user'] as Map);
    notifyListeners();
  }

  Future<void> logout() async {
    try {
      if (isAuthenticated) await api.dio.post('/auth/logout');
    } finally {
      await _storage.delete(key: _tokenKey);
      _token = null;
      _user = null;
      api.setToken(null);
      notifyListeners();
    }
  }

  Future<void> _acceptAuthResponse(Map response) async {
    _token = response['token'] as String;
    _user = Map<String, dynamic>.from(response['user'] as Map);
    await _storage.write(key: _tokenKey, value: _token);
    api.setToken(_token);
    notifyListeners();
  }
}
