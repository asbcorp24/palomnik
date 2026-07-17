import 'package:flutter/foundation.dart';
import 'package:moscow_pilgrim/core/api_client.dart';
import 'package:moscow_pilgrim/models/models.dart';

class SessionController extends ChangeNotifier {
  SessionController._();

  static final SessionController instance = SessionController._();

  final ApiClient _api = ApiClient.instance;

  UserProfile? _user;
  bool _initialized = false;
  bool _busy = false;
  String? _error;

  UserProfile? get user => _user;
  bool get initialized => _initialized;
  bool get busy => _busy;
  bool get isAuthenticated => _user != null;
  String? get error => _error;

  Future<void> bootstrap() async {
    if (_initialized) return;
    _busy = true;
    notifyListeners();

    try {
      if (await _api.hasToken()) {
        final response = await _api.getJson('/auth/me');
        _user = UserProfile.fromJson(
          Map<String, dynamic>.from(response['user'] as Map),
        );
      }
    } on ApiException catch (error) {
      if (error.statusCode == 401) {
        await _api.clearToken();
      }
      _user = null;
    } finally {
      _busy = false;
      _initialized = true;
      notifyListeners();
    }
  }

  Future<bool> login({required String email, required String password}) async {
    return _authenticate('/auth/login', {
      'email': email.trim(),
      'password': password,
      'device_name': 'Flutter mobile',
    });
  }

  Future<bool> register({
    required String name,
    required String email,
    String? phone,
    required String password,
  }) async {
    return _authenticate('/auth/register', {
      'name': name.trim(),
      'email': email.trim(),
      'phone': phone?.trim(),
      'password': password,
      'password_confirmation': password,
      'consent': true,
      'device_name': 'Flutter mobile',
    });
  }

  Future<bool> _authenticate(String path, Map<String, dynamic> payload) async {
    _busy = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _api.postJson(path, data: payload);
      final token = response['token']?.toString();
      if (token == null || token.isEmpty || response['user'] is! Map) {
        throw const ApiException('Сервер вернул неполные данные авторизации.');
      }
      await _api.saveToken(token);
      _user = UserProfile.fromJson(
        Map<String, dynamic>.from(response['user'] as Map),
      );
      return true;
    } on ApiException catch (error) {
      _error = error.message;
      return false;
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  Future<void> refreshUser() async {
    if (!await _api.hasToken()) return;
    try {
      final response = await _api.getJson('/auth/me');
      _user = UserProfile.fromJson(
        Map<String, dynamic>.from(response['user'] as Map),
      );
      notifyListeners();
    } on ApiException catch (error) {
      if (error.statusCode == 401) {
        await logout(localOnly: true);
      }
    }
  }

  void replaceUser(UserProfile user) {
    _user = user;
    notifyListeners();
  }

  Future<void> logout({bool localOnly = false}) async {
    if (!localOnly) {
      try {
        await _api.postJson('/auth/logout');
      } on ApiException {
        // Локальный выход должен работать даже при недоступном сервере.
      }
    }
    await _api.clearToken();
    _user = null;
    _error = null;
    notifyListeners();
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
