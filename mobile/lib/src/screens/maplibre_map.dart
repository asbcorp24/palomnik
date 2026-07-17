import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:maplibre/maplibre.dart';

import '../core/api_client.dart';
import '../data/cached_api.dart';
import '../data/offline_store.dart';
import '../theme/app_theme.dart';
import 'advanced_features.dart';
import 'user_features.dart';

class MapLibreMapTab extends StatefulWidget {
  const MapLibreMapTab({super.key});

  @override
  State<MapLibreMapTab> createState() => _MapLibreMapTabState();
}

class _MapLibreMapTabState extends State<MapLibreMapTab> {
  static const _configuredStyle = String.fromEnvironment('MAP_STYLE_URL');

  final _search = TextEditingController();
  MapController? _controller;
  List<Map<String, dynamic>> _objects = const [];
  LineString? _activeRoute;
  bool _loading = true;
  String? _error;
  String _mode = 'pedestrian';
  String? _routeSummary;

  String get _styleUrl => _configuredStyle.isNotEmpty
      ? _configuredStyle
      : '${ApiClient.siteBaseUrl}/api/v1/map/style.json';

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _load({bool refresh = false}) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final payload = await CachedApi.instance.get(
        '/objects',
        queryParameters: {
          'per_page': 100,
          if (_search.text.trim().isNotEmpty) 'q': _search.text.trim(),
        },
        forceRefresh: refresh,
      ) as Map;
      final items = (payload['data'] as List? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .where((item) {
            final location = _location(item);
            return location['latitude'] != null && location['longitude'] != null;
          })
          .toList();
      if (mounted) setState(() => _objects = items);
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.instance.messageFrom(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _showObject(Map<String, dynamic> item) async {
    final location = _location(item);
    final latitude = (location['latitude'] as num).toDouble();
    final longitude = (location['longitude'] as num).toDouble();
    await _controller?.animateCamera(
      center: Geographic(lon: longitude, lat: latitude),
      zoom: 14,
    );
    if (!mounted) return;

    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${item['name'] ?? ''}',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              Text('${location['address'] ?? item['address'] ?? ''}', style: const TextStyle(color: Colors.black54)),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: () {
                        Navigator.pop(context);
                        Navigator.push(context, MaterialPageRoute(builder: (_) => ObjectDetailScreen(slug: '${item['slug']}')));
                      },
                      icon: const Icon(Icons.church),
                      label: const Text('Карточка'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () {
                        Navigator.pop(context);
                        _buildRoute(item);
                      },
                      icon: const Icon(Icons.route),
                      label: const Text('Маршрут'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () async {
                        final detail = await CachedApi.instance.get('/objects/${item['slug']}', forceRefresh: true) as Map;
                        await OfflineStore.instance.saveObject(Map<String, dynamic>.from(detail['data'] as Map));
                        if (context.mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Карточка сохранена для работы без сети.')));
                        }
                      },
                      icon: const Icon(Icons.download_for_offline_outlined),
                      label: const Text('Офлайн'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () {
                        Navigator.pop(context);
                        Navigator.push(context, MaterialPageRoute(builder: (_) => GeoVisitScreen(initialObject: item)));
                      },
                      icon: const Icon(Icons.where_to_vote_outlined),
                      label: const Text('Отметиться'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _myLocation() async {
    try {
      final allowed = await _locationPermission();
      if (!allowed) throw Exception('Разрешение на геолокацию не предоставлено.');
      final position = await Geolocator.getCurrentPosition(desiredAccuracy: LocationAccuracy.high);
      await _controller?.enableLocation();
      await _controller?.animateCamera(
        center: Geographic(lon: position.longitude, lat: position.latitude),
        zoom: 14,
      );
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.instance.messageFrom(error))));
      }
    }
  }

  Future<void> _buildRoute(Map<String, dynamic> item) async {
    setState(() {
      _routeSummary = 'Строим маршрут…';
      _error = null;
    });
    try {
      final allowed = await _locationPermission();
      if (!allowed) throw Exception('Разрешение на геолокацию не предоставлено.');
      final position = await Geolocator.getCurrentPosition(desiredAccuracy: LocationAccuracy.high);
      final location = _location(item);
      final response = await ApiClient.instance.dio.post('/map/route', data: {
        'mode': _mode,
        'locations': [
          {'latitude': position.latitude, 'longitude': position.longitude},
          {'latitude': location['latitude'], 'longitude': location['longitude']},
        ],
      });
      final data = Map<String, dynamic>.from(response.data['data'] as Map);
      final geometry = Map<String, dynamic>.from(data['geometry'] as Map);
      final coordinates = (geometry['coordinates'] as List)
          .map((value) {
            final coordinate = value as List;
            return Geographic(
              lon: (coordinate[0] as num).toDouble(),
              lat: (coordinate[1] as num).toDouble(),
            );
          })
          .toList();
      if (coordinates.length < 2) throw Exception('Маршрут не найден.');

      setState(() {
        _activeRoute = LineString(coordinates: coordinates);
        final km = ((data['distance_meters'] as num?)?.toDouble() ?? 0) / 1000;
        final minutes = (((data['duration_seconds'] as num?)?.toDouble() ?? 0) / 60).round();
        _routeSummary = '${item['name']} · ${km.toStringAsFixed(1)} км · примерно $minutes мин.';
      });

      final minLon = coordinates.map((point) => point.lon).reduce((a, b) => a < b ? a : b);
      final maxLon = coordinates.map((point) => point.lon).reduce((a, b) => a > b ? a : b);
      final minLat = coordinates.map((point) => point.lat).reduce((a, b) => a < b ? a : b);
      final maxLat = coordinates.map((point) => point.lat).reduce((a, b) => a > b ? a : b);
      await _controller?.fitBounds(
        bounds: LngLatBounds(
          longitudeWest: minLon,
          longitudeEast: maxLon,
          latitudeSouth: minLat,
          latitudeNorth: maxLat,
        ),
        padding: const EdgeInsets.fromLTRB(48, 130, 48, 150),
      );
    } catch (error) {
      if (mounted) {
        setState(() => _routeSummary = null);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(ApiClient.instance.messageFrom(error)), backgroundColor: Colors.red),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final markers = _objects.map((item) {
      final location = _location(item);
      return Marker(
        size: const Size(46, 46),
        point: Geographic(
          lon: (location['longitude'] as num).toDouble(),
          lat: (location['latitude'] as num).toDouble(),
        ),
        alignment: Alignment.bottomCenter,
        child: GestureDetector(
          onTap: () => _showObject(item),
          child: Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: _color('${item['type'] is Map ? item['type']['marker_color'] : item['marker_color'] ?? ''}'),
              shape: BoxShape.circle,
              border: Border.all(color: Colors.white, width: 3),
              boxShadow: const [BoxShadow(color: Colors.black26, blurRadius: 8, offset: Offset(0, 3))],
            ),
            child: const Icon(Icons.church, color: Colors.white, size: 23),
          ),
        ),
      );
    }).toList();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Интерактивная карта'),
        actions: [
          PopupMenuButton<String>(
            initialValue: _mode,
            tooltip: 'Вид маршрута',
            onSelected: (value) => setState(() => _mode = value),
            itemBuilder: (_) => const [
              PopupMenuItem(value: 'pedestrian', child: Text('Пешком')),
              PopupMenuItem(value: 'auto', child: Text('Автомобиль')),
              PopupMenuItem(value: 'bicycle', child: Text('Велосипед')),
              PopupMenuItem(value: 'bus', child: Text('Автобус')),
              PopupMenuItem(value: 'multimodal', child: Text('Общественный транспорт')),
            ],
            icon: const Icon(Icons.directions),
          ),
          IconButton(onPressed: _myLocation, tooltip: 'Моё местоположение', icon: const Icon(Icons.my_location)),
          IconButton(onPressed: () => _load(refresh: true), tooltip: 'Обновить', icon: const Icon(Icons.refresh)),
        ],
      ),
      body: Stack(
        children: [
          MapLibreMap(
            options: MapOptions(
              initCenter: Geographic(lon: 37.618423, lat: 55.751244),
              initZoom: 8.5,
              style: _styleUrl,
            ),
            onMapCreated: (controller) => _controller = controller,
            children: [
              WidgetLayer(markers: markers),
              const SourceAttribution(),
              const MapCompass(),
              const MapControlButtons(),
            ],
            layers: [
              if (_activeRoute != null)
                PolylineLayer(
                  polylines: [_activeRoute!],
                  color: AppTheme.gold,
                  width: 5,
                  blur: 1,
                ),
            ],
          ),
          Positioned(
            left: 12,
            right: 12,
            top: 12,
            child: Material(
              elevation: 4,
              borderRadius: BorderRadius.circular(18),
              child: TextField(
                controller: _search,
                textInputAction: TextInputAction.search,
                onSubmitted: (_) => _load(refresh: true),
                decoration: InputDecoration(
                  hintText: 'Храм, адрес или святыня',
                  prefixIcon: const Icon(Icons.search),
                  suffixIcon: IconButton(onPressed: () => _load(refresh: true), icon: const Icon(Icons.arrow_forward)),
                ),
              ),
            ),
          ),
          if (_loading) const Positioned(top: 84, left: 16, right: 16, child: LinearProgressIndicator()),
          if (_routeSummary != null)
            Positioned(
              left: 14,
              right: 14,
              bottom: 18,
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Row(
                    children: [
                      const Icon(Icons.route, color: AppTheme.gold),
                      const SizedBox(width: 10),
                      Expanded(child: Text(_routeSummary!)),
                      IconButton(
                        onPressed: () => setState(() {
                          _activeRoute = null;
                          _routeSummary = null;
                        }),
                        icon: const Icon(Icons.close),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          if (_error != null)
            Positioned(
              left: 16,
              right: 16,
              bottom: 24,
              child: Material(
                color: Colors.red.shade50,
                borderRadius: BorderRadius.circular(16),
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Text(_error!, style: TextStyle(color: Colors.red.shade900)),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

Map<String, dynamic> _location(Map<String, dynamic> item) {
  final value = item['location'];
  if (value is Map) return Map<String, dynamic>.from(value);
  return {
    'latitude': item['latitude'],
    'longitude': item['longitude'],
    'address': item['address'],
  };
}

Color _color(String value) {
  final normalized = value.replaceFirst('#', '').trim();
  if (normalized.length != 6) return AppTheme.gold;
  return Color(int.parse('FF$normalized', radix: 16));
}
