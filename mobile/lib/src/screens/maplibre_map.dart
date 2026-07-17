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
  LineString? _routeLine;
  bool _loading = true;
  String? _error;
  String? _routeSummary;
  String _routeMode = 'pedestrian';

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

  Future<void> _load({bool forceRefresh = false}) async {
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
        forceRefresh: forceRefresh,
      ) as Map;

      final objects = (payload['data'] as List? ?? const [])
          .map((value) => Map<String, dynamic>.from(value as Map))
          .where((object) {
            final location = _location(object);
            return location['latitude'] is num && location['longitude'] is num;
          })
          .toList();

      if (mounted) setState(() => _objects = objects);
    } catch (error) {
      if (mounted) setState(() => _error = _message(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _showObject(Map<String, dynamic> object) async {
    final location = _location(object);
    final point = Geographic(
      lon: (location['longitude'] as num).toDouble(),
      lat: (location['latitude'] as num).toDouble(),
    );
    await _controller?.animateCamera(center: point, zoom: 14);
    if (!mounted) return;

    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${object['name'] ?? ''}',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              Text(
                '${location['address'] ?? object['address'] ?? ''}',
                style: const TextStyle(color: Colors.black54),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: () {
                        Navigator.pop(context);
                        Navigator.push(
                          context,
                          MaterialPageRoute(builder: (_) => ObjectDetailScreen(slug: '${object['slug']}')),
                        );
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
                        _buildRoute(object);
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
                      onPressed: () => _saveOffline(object),
                      icon: const Icon(Icons.download_for_offline_outlined),
                      label: const Text('Офлайн'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () {
                        Navigator.pop(context);
                        Navigator.push(
                          context,
                          MaterialPageRoute(builder: (_) => GeoVisitScreen(initialObject: object)),
                        );
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

  Future<void> _saveOffline(Map<String, dynamic> object) async {
    try {
      final payload = await CachedApi.instance.get(
        '/objects/${object['slug']}',
        forceRefresh: true,
      ) as Map;
      await OfflineStore.instance.saveObject(
        Map<String, dynamic>.from(payload['data'] as Map),
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Карточка сохранена для работы без сети.')),
        );
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_message(error)), backgroundColor: Colors.red),
        );
      }
    }
  }

  Future<Position> _position() async {
    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) throw Exception('Включите геолокацию на устройстве.');

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
      throw Exception('Разрешение на геолокацию не предоставлено.');
    }

    return Geolocator.getCurrentPosition(desiredAccuracy: LocationAccuracy.high);
  }

  Future<void> _showMyLocation() async {
    try {
      final position = await _position();
      await _controller?.enableLocation();
      await _controller?.animateCamera(
        center: Geographic(lon: position.longitude, lat: position.latitude),
        zoom: 14,
      );
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(_message(error))));
      }
    }
  }

  Future<void> _buildRoute(Map<String, dynamic> object) async {
    setState(() {
      _routeSummary = 'Строим маршрут…';
      _error = null;
    });

    try {
      final position = await _position();
      final location = _location(object);
      final response = await ApiClient.instance.dio.post(
        '/map/route',
        data: {
          'mode': _routeMode,
          'locations': [
            {'latitude': position.latitude, 'longitude': position.longitude},
            {'latitude': location['latitude'], 'longitude': location['longitude']},
          ],
        },
      );

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

      final distance = ((data['distance_meters'] as num?)?.toDouble() ?? 0) / 1000;
      final minutes = (((data['duration_seconds'] as num?)?.toDouble() ?? 0) / 60).round();
      setState(() {
        _routeLine = LineString(coordinates: coordinates);
        _routeSummary = '${object['name']} · ${distance.toStringAsFixed(1)} км · примерно $minutes мин.';
      });

      await _controller?.fitBounds(
        bounds: LngLatBounds.fromPoints(coordinates),
        padding: const EdgeInsets.fromLTRB(48, 130, 48, 150),
      );
    } catch (error) {
      if (mounted) {
        setState(() => _routeSummary = null);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_message(error)), backgroundColor: Colors.red),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final markers = _objects.map((object) {
      final location = _location(object);
      return Marker(
        size: const Size(46, 46),
        point: Geographic(
          lon: (location['longitude'] as num).toDouble(),
          lat: (location['latitude'] as num).toDouble(),
        ),
        alignment: Alignment.bottomCenter,
        child: GestureDetector(
          onTap: () => _showObject(object),
          child: Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: _markerColor(object),
              shape: BoxShape.circle,
              border: Border.all(color: Colors.white, width: 3),
              boxShadow: const [
                BoxShadow(color: Colors.black26, blurRadius: 8, offset: Offset(0, 3)),
              ],
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
            initialValue: _routeMode,
            tooltip: 'Вид маршрута',
            onSelected: (value) => setState(() => _routeMode = value),
            itemBuilder: (_) => const [
              PopupMenuItem(value: 'pedestrian', child: Text('Пешком')),
              PopupMenuItem(value: 'auto', child: Text('Автомобиль')),
              PopupMenuItem(value: 'bicycle', child: Text('Велосипед')),
              PopupMenuItem(value: 'bus', child: Text('Автобус')),
              PopupMenuItem(value: 'multimodal', child: Text('Общественный транспорт')),
            ],
            icon: const Icon(Icons.directions),
          ),
          IconButton(
            onPressed: _showMyLocation,
            tooltip: 'Моё местоположение',
            icon: const Icon(Icons.my_location),
          ),
          IconButton(
            onPressed: () => _load(forceRefresh: true),
            tooltip: 'Обновить',
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: Stack(
        children: [
          MapLibreMap(
            options: MapOptions(
              initCenter: Geographic(lon: 37.618423, lat: 55.751244),
              initZoom: 8.5,
              initStyle: _styleUrl,
            ),
            onMapCreated: (controller) => _controller = controller,
            layers: [
              if (_routeLine != null)
                PolylineLayer(
                  polylines: [_routeLine!],
                  color: AppTheme.gold,
                  width: 5,
                  blur: 1,
                ),
            ],
            children: [
              WidgetLayer(markers: markers),
              const SourceAttribution(),
              const MapCompass(),
              const MapControlButtons(),
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
                onSubmitted: (_) => _load(forceRefresh: true),
                decoration: InputDecoration(
                  hintText: 'Храм, адрес или святыня',
                  prefixIcon: const Icon(Icons.search),
                  suffixIcon: IconButton(
                    onPressed: () => _load(forceRefresh: true),
                    icon: const Icon(Icons.arrow_forward),
                  ),
                ),
              ),
            ),
          ),
          if (_loading)
            const Positioned(top: 84, left: 16, right: 16, child: LinearProgressIndicator()),
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
                          _routeLine = null;
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

Map<String, dynamic> _location(Map<String, dynamic> object) {
  final location = object['location'];
  if (location is Map) return Map<String, dynamic>.from(location);
  return {
    'latitude': object['latitude'],
    'longitude': object['longitude'],
    'address': object['address'],
  };
}

Color _markerColor(Map<String, dynamic> object) {
  final type = object['type'];
  final value = type is Map ? type['marker_color'] : object['marker_color'];
  final normalized = '$value'.replaceFirst('#', '').trim();
  final parsed = int.tryParse('FF$normalized', radix: 16);
  return normalized.length == 6 && parsed != null ? Color(parsed) : AppTheme.gold;
}

String _message(Object error) {
  final apiMessage = ApiClient.instance.messageFrom(error);
  if (apiMessage != 'Произошла ошибка. Повторите попытку.') return apiMessage;
  return error.toString().replaceFirst('Exception: ', '');
}
