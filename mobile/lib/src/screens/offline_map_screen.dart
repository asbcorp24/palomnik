import 'package:flutter/material.dart';
import 'package:maplibre/maplibre.dart';

import '../core/api_client.dart';

class OfflineMapScreen extends StatefulWidget {
  const OfflineMapScreen({super.key});

  @override
  State<OfflineMapScreen> createState() => _OfflineMapScreenState();
}

class _OfflineMapScreenState extends State<OfflineMapScreen> {
  static const _configuredStyle = String.fromEnvironment('MAP_STYLE_URL');

  Future<OfflineManager>? _managerFuture;
  List<OfflineRegion> _regions = const [];
  bool _loading = true;
  bool _downloading = false;
  bool _offlineEnabled = false;
  int _tileLimit = 100000;
  double? _progress;
  String? _status;

  String get _styleUrl => _configuredStyle.isNotEmpty
      ? _configuredStyle
      : '${ApiClient.siteBaseUrl}/api/v1/map/style.json';

  @override
  void initState() {
    super.initState();
    _initialize();
  }

  @override
  void dispose() {
    _managerFuture?.then((manager) => manager.dispose());
    super.dispose();
  }

  Future<void> _initialize() async {
    setState(() => _loading = true);
    try {
      final response = await ApiClient.instance.dio.get('/map/config');
      final config = Map<String, dynamic>.from(response.data['data'] as Map);
      _offlineEnabled = config['offline_enabled'] == true;
      _tileLimit = (config['offline_tile_limit'] as num?)?.toInt() ?? 100000;

      if (!OfflineManager.isSupported) {
        _status = 'Офлайн-карты не поддерживаются на этой платформе.';
        return;
      }
      if (!_offlineEnabled) {
        _status = 'Загрузка отключена до подключения собственного или лицензированного сервера OpenMapTiles.';
        return;
      }

      _managerFuture = OfflineManager.createInstance();
      final manager = await _managerFuture!;
      manager.setOfflineTileCountLimit(amount: _tileLimit);
      _regions = await manager.listOfflineRegions();
    } catch (error) {
      _status = 'Не удалось подготовить офлайн-карты: $error';
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadRegions() async {
    final managerFuture = _managerFuture;
    if (managerFuture == null) return;
    setState(() => _loading = true);
    try {
      final manager = await managerFuture;
      final regions = await manager.listOfflineRegions();
      if (mounted) setState(() => _regions = regions);
    } catch (error) {
      if (mounted) setState(() => _status = 'Не удалось прочитать офлайн-карты: $error');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _download(_OfflinePreset preset) async {
    final managerFuture = _managerFuture;
    if (_downloading || managerFuture == null || !_offlineEnabled) return;

    final accepted = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Скачать «${preset.name}»?'),
        content: Text(
          'Будут сохранены векторные тайлы уровней ${preset.minZoom.toInt()}–${preset.maxZoom.toInt()}. '
          'Загрузка может занять заметное время и место в памяти устройства. Используйте Wi‑Fi.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Отмена')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Скачать')),
        ],
      ),
    );
    if (accepted != true || !mounted) return;

    setState(() {
      _downloading = true;
      _progress = 0;
      _status = 'Подготовка загрузки…';
    });

    try {
      final manager = await managerFuture;
      final pixelDensity = MediaQuery.devicePixelRatioOf(context).clamp(1.0, 3.0).toDouble();
      final updates = manager.downloadRegion(
        mapStyleUrl: _styleUrl,
        bounds: preset.bounds,
        minZoom: preset.minZoom,
        maxZoom: preset.maxZoom,
        pixelDensity: pixelDensity,
        metadata: {'name': preset.name, 'preset': preset.id},
      );

      await for (final update in updates) {
        if (!mounted) return;
        setState(() {
          _progress = update.progress;
          _status = update.downloadCompleted
              ? 'Карта «${preset.name}» загружена.'
              : 'Загружено ${update.loadedTiles} из ${update.totalTiles} тайлов';
        });
      }
      await _loadRegions();
    } catch (error) {
      if (mounted) setState(() => _status = 'Ошибка загрузки: $error');
    } finally {
      if (mounted) setState(() => _downloading = false);
    }
  }

  Future<void> _delete(OfflineRegion region) async {
    final managerFuture = _managerFuture;
    if (managerFuture == null) return;

    final name = '${region.metadata['name'] ?? 'Офлайн-карта'}';
    final accepted = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Удалить офлайн-карту?'),
        content: Text(name),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Отмена')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Удалить')),
        ],
      ),
    );
    if (accepted != true) return;

    final manager = await managerFuture;
    await manager.deleteRegion(regionId: region.id);
    await _loadRegions();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Офлайн-карты')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(_offlineEnabled ? Icons.info_outline : Icons.lock_outline),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      _offlineEnabled
                          ? 'Офлайн-пакеты используют тот же стиль OpenMapTiles, что сайт и обычная карта. Лимит этого приложения: $_tileLimit тайлов.'
                          : 'Массовая загрузка с публичного сервера OpenStreetMap не выполняется. Администратор должен подключить собственный или лицензированный TileServer и включить MAP_OFFLINE_ENABLED.',
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text('Доступные пакеты', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 10),
          ..._presets.map(
            (preset) => Card(
              child: ListTile(
                leading: const Icon(Icons.download_for_offline_outlined),
                title: Text(preset.name),
                subtitle: Text('${preset.description}\nМасштабы ${preset.minZoom.toInt()}–${preset.maxZoom.toInt()}'),
                isThreeLine: true,
                trailing: FilledButton(
                  onPressed: !_offlineEnabled || _downloading ? null : () => _download(preset),
                  child: const Text('Скачать'),
                ),
              ),
            ),
          ),
          if (_loading)
            const Padding(padding: EdgeInsets.all(30), child: Center(child: CircularProgressIndicator())),
          if (_downloading || _status != null) ...[
            const SizedBox(height: 16),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (_progress != null) LinearProgressIndicator(value: _progress),
                    if (_progress != null) const SizedBox(height: 12),
                    Text(_status ?? ''),
                  ],
                ),
              ),
            ),
          ],
          const SizedBox(height: 22),
          Row(
            children: [
              Expanded(child: Text('Сохранено на устройстве', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700))),
              IconButton(onPressed: _managerFuture == null ? null : _loadRegions, icon: const Icon(Icons.refresh)),
            ],
          ),
          if (!_loading && _regions.isEmpty)
            const Card(child: Padding(padding: EdgeInsets.all(24), child: Text('Офлайн-карты пока не загружены.')))
          else
            ..._regions.map(
              (region) => Card(
                child: ListTile(
                  leading: const Icon(Icons.offline_pin),
                  title: Text('${region.metadata['name'] ?? 'Офлайн-карта'}'),
                  subtitle: Text('Масштабы ${region.minZoom.toInt()}–${region.maxZoom.toInt()}'),
                  trailing: IconButton(
                    onPressed: () => _delete(region),
                    icon: const Icon(Icons.delete_outline, color: Colors.red),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _OfflinePreset {
  const _OfflinePreset({
    required this.id,
    required this.name,
    required this.description,
    required this.bounds,
    required this.minZoom,
    required this.maxZoom,
  });

  final String id;
  final String name;
  final String description;
  final LngLatBounds bounds;
  final double minZoom;
  final double maxZoom;
}

const _presets = [
  _OfflinePreset(
    id: 'moscow',
    name: 'Москва',
    description: 'Москва и ближайшие окрестности с подробными улицами и зданиями.',
    bounds: LngLatBounds(
      longitudeWest: 36.75,
      longitudeEast: 38.20,
      latitudeSouth: 55.35,
      latitudeNorth: 56.10,
    ),
    minZoom: 8,
    maxZoom: 14,
  ),
  _OfflinePreset(
    id: 'moscow-region',
    name: 'Московская область',
    description: 'Обзорная карта области для поездок между городами и святынями.',
    bounds: LngLatBounds(
      longitudeWest: 35.10,
      longitudeEast: 40.30,
      latitudeSouth: 54.20,
      latitudeNorth: 57.05,
    ),
    minZoom: 6,
    maxZoom: 11,
  ),
];
