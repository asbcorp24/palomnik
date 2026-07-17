import '../core/api_client.dart';
import 'offline_store.dart';

class CachedApi {
  CachedApi._();

  static final CachedApi instance = CachedApi._();

  Future<dynamic> get(
    String endpoint, {
    Map<String, dynamic>? queryParameters,
    bool forceRefresh = false,
  }) async {
    final key = _key(endpoint, queryParameters);
    if (!forceRefresh) {
      final cached = await OfflineStore.instance.getCache(key);
      if (cached != null) {
        _refreshInBackground(endpoint, queryParameters, key);
        return cached;
      }
    }

    try {
      final response = await ApiClient.instance.dio.get(endpoint, queryParameters: queryParameters);
      await OfflineStore.instance.putCache(key, response.data);
      return response.data;
    } catch (_) {
      final cached = await OfflineStore.instance.getCache(key);
      if (cached != null) return cached;
      rethrow;
    }
  }

  Future<void> _refreshInBackground(
    String endpoint,
    Map<String, dynamic>? queryParameters,
    String key,
  ) async {
    try {
      final response = await ApiClient.instance.dio.get(endpoint, queryParameters: queryParameters);
      await OfflineStore.instance.putCache(key, response.data);
    } catch (_) {
      // Cached content stays available when the network is unavailable.
    }
  }

  String _key(String endpoint, Map<String, dynamic>? queryParameters) {
    if (queryParameters == null || queryParameters.isEmpty) return endpoint;
    final keys = queryParameters.keys.toList()..sort();
    final suffix = keys.map((key) => '$key=${queryParameters[key]}').join('&');
    return '$endpoint?$suffix';
  }
}
