import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api_client.dart';
import '../data/cached_api.dart';
import '../theme/app_theme.dart';
import '../widgets/audio_guide_player.dart';
import '../widgets/zoomable_network_image.dart';

class AudioCatalogTab extends StatefulWidget {
  const AudioCatalogTab({super.key});

  @override
  State<AudioCatalogTab> createState() => _AudioCatalogTabState();
}

class _AudioCatalogTabState extends State<AudioCatalogTab> {
  final TextEditingController _search = TextEditingController();
  late Future<List<Map<String, dynamic>>> _future = _load();

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<List<Map<String, dynamic>>> _load({bool refresh = false}) async {
    final payload = await CachedApi.instance.get(
      '/objects',
      queryParameters: {
        'per_page': 50,
        if (_search.text.trim().isNotEmpty) 'q': _search.text.trim(),
      },
      forceRefresh: refresh,
    ) as Map;
    return _mapList(payload['data']);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Храмы и святыни'),
        actions: [
          IconButton(
            tooltip: 'Все аудиогиды',
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const AudioGuidesScreen()),
            ),
            icon: const Icon(Icons.headphones_outlined),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => setState(
                () => _future = _load(refresh: true),
              ),
              decoration: InputDecoration(
                hintText: 'Название, адрес или святыня',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: IconButton(
                  onPressed: () => setState(
                    () => _future = _load(refresh: true),
                  ),
                  icon: const Icon(Icons.arrow_forward),
                ),
              ),
            ),
          ),
          Expanded(
            child: FutureBuilder<List<Map<String, dynamic>>>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const Center(child: CircularProgressIndicator());
                }
                if (snapshot.hasError) {
                  return _ErrorPane(
                    error: snapshot.error!,
                    onRetry: () => setState(
                      () => _future = _load(refresh: true),
                    ),
                  );
                }
                final items = snapshot.data ?? const [];
                if (items.isEmpty) {
                  return const Center(child: Text('Объекты не найдены'));
                }
                return RefreshIndicator(
                  onRefresh: () async {
                    final future = _load(refresh: true);
                    setState(() => _future = future);
                    await future;
                  },
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                    itemCount: items.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (context, index) => AudioObjectPreviewCard(
                      item: items[index],
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class AudioObjectPreviewCard extends StatelessWidget {
  const AudioObjectPreviewCard({super.key, required this.item});

  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    final cover = _coverUrl(item);
    final location = _asMap(item['location']);
    final address = item['address'] ?? location['address'];
    final name = '${item['name'] ?? 'Паломнический объект'}';

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: item['slug'] == null
            ? null
            : () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => AudioObjectDetailScreen(
                      slug: '${item['slug']}',
                    ),
                  ),
                ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (cover != null)
              ZoomableNetworkImage(
                url: cover,
                caption: name,
                height: 150,
                width: double.infinity,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => _objectPlaceholder(),
              )
            else
              _objectPlaceholder(),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.church, color: AppTheme.green),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          name,
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                        ),
                        if ('$address'.trim().isNotEmpty) ...[
                          const SizedBox(height: 6),
                          Text(
                            '$address',
                            style: const TextStyle(color: Colors.black54),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const Icon(Icons.chevron_right),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class AudioObjectDetailScreen extends StatefulWidget {
  const AudioObjectDetailScreen({super.key, required this.slug});

  final String slug;

  @override
  State<AudioObjectDetailScreen> createState() =>
      _AudioObjectDetailScreenState();
}

class _AudioObjectDetailScreenState extends State<AudioObjectDetailScreen> {
  late Future<Map<String, dynamic>> _future = _load();

  Future<Map<String, dynamic>> _load() async {
    final objectResponse = await ApiClient.instance.dio.get(
      '/objects/${widget.slug}',
    );
    Map<String, dynamic>? guide;
    try {
      final guideResponse = await ApiClient.instance.dio.get(
        '/mobile/audio-guides/objects/${widget.slug}',
      );
      guide = _asMap(guideResponse.data['data']);
    } on DioException catch (error) {
      if (error.response?.statusCode != 404) rethrow;
    }

    return {
      'object': _asMap(objectResponse.data['data']),
      'guide': guide,
    };
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Карточка объекта')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _ErrorPane(
              error: snapshot.error!,
              onRetry: () => setState(() => _future = _load()),
            );
          }

          final payload = snapshot.data!;
          final item = _asMap(payload['object']);
          final guide = _asMap(payload['guide']);
          final cover = _coverUrl(item);
          final location = _asMap(item['location']);
          final contacts = _asMap(item['contacts']);
          final sanctities = _mapList(item['sanctities']);
          final lat = location['latitude'];
          final lon = location['longitude'];
          final name = '${item['name'] ?? 'Паломнический объект'}';

          return ListView(
            padding: const EdgeInsets.only(bottom: 32),
            children: [
              if (cover != null)
                ZoomableNetworkImage(
                  url: cover,
                  caption: name,
                  height: 270,
                  width: double.infinity,
                  fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => _objectPlaceholder(height: 270),
                )
              else
                _objectPlaceholder(height: 270),
              Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                            color: AppTheme.green,
                            fontWeight: FontWeight.w700,
                          ),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(Icons.location_on_outlined, color: AppTheme.gold),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            '${location['address'] ?? 'Адрес уточняется'}',
                          ),
                        ),
                      ],
                    ),
                    if (lat != null && lon != null) ...[
                      const SizedBox(height: 14),
                      FilledButton.icon(
                        onPressed: () => launchUrl(
                          Uri.parse(
                            'https://yandex.ru/maps/?rtext=~$lat,$lon&rtt=auto',
                          ),
                          mode: LaunchMode.externalApplication,
                        ),
                        icon: const Icon(Icons.directions),
                        label: const Text('Построить маршрут'),
                      ),
                    ],
                    if (guide['url'] != null) ...[
                      const SizedBox(height: 22),
                      AudioGuidePlayer(
                        url: '${guide['url']}',
                        title: '${guide['title'] ?? item['name'] ?? 'Аудиогид'}',
                        transcript: guide['transcript']?.toString(),
                      ),
                    ],
                    if (_text(item['short_description']) != null)
                      _TextSection(
                        title: 'Кратко',
                        text: '${item['short_description']}',
                      ),
                    if (_text(item['description']) != null)
                      _TextSection(
                        title: 'Описание',
                        text: '${item['description']}',
                      ),
                    if (_text(item['history']) != null)
                      _TextSection(
                        title: 'История',
                        text: '${item['history']}',
                      ),
                    if (sanctities.isNotEmpty) ...[
                      const _Heading('Святыни'),
                      ...sanctities.map(
                        (sanctity) => Card(
                          margin: const EdgeInsets.only(bottom: 10),
                          child: ListTile(
                            leading: const Icon(
                              Icons.auto_awesome,
                              color: AppTheme.gold,
                            ),
                            title: Text('${sanctity['name'] ?? ''}'),
                            subtitle: _text(sanctity['description']) == null
                                ? null
                                : Text('${sanctity['description']}'),
                          ),
                        ),
                      ),
                    ],
                    if (_text(item['schedule']) != null)
                      _TextSection(
                        title: 'Расписание богослужений',
                        text: '${item['schedule']}',
                      ),
                    if (contacts.values.any((value) => _text(value) != null)) ...[
                      const _Heading('Контакты'),
                      Card(
                        child: Column(
                          children: [
                            if (_text(contacts['phone']) != null)
                              ListTile(
                                leading: const Icon(Icons.phone),
                                title: Text('${contacts['phone']}'),
                                onTap: () => launchUrl(
                                  Uri.parse('tel:${contacts['phone']}'),
                                ),
                              ),
                            if (_text(contacts['website']) != null)
                              ListTile(
                                leading: const Icon(Icons.language),
                                title: Text('${contacts['website']}'),
                                onTap: () => launchUrl(
                                  Uri.parse('${contacts['website']}'),
                                  mode: LaunchMode.externalApplication,
                                ),
                              ),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class AudioRoutesScreen extends StatefulWidget {
  const AudioRoutesScreen({super.key});

  @override
  State<AudioRoutesScreen> createState() => _AudioRoutesScreenState();
}

class _AudioRoutesScreenState extends State<AudioRoutesScreen> {
  late Future<List<Map<String, dynamic>>> _future = _load();

  Future<List<Map<String, dynamic>>> _load({bool refresh = false}) async {
    final payload = await CachedApi.instance.get(
      '/mobile/routes',
      forceRefresh: refresh,
    ) as Map;
    return _mapList(payload['data']);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Паломнические маршруты')),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _ErrorPane(
              error: snapshot.error!,
              onRetry: () => setState(() => _future = _load(refresh: true)),
            );
          }
          final routes = snapshot.data ?? const [];
          return RefreshIndicator(
            onRefresh: () async {
              final future = _load(refresh: true);
              setState(() => _future = future);
              await future;
            },
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: routes.length,
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (context, index) {
                final route = routes[index];
                return Card(
                  child: ListTile(
                    contentPadding: const EdgeInsets.all(16),
                    leading: const CircleAvatar(
                      backgroundColor: AppTheme.cream,
                      child: Icon(Icons.route, color: AppTheme.green),
                    ),
                    title: Text(
                      '${route['title'] ?? 'Маршрут'}',
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    subtitle: Text(
                      '${route['short_description'] ?? ''}\n${route['objects_count'] ?? 0} точек',
                    ),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => AudioRouteDetailScreen(
                          slug: '${route['slug']}',
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class AudioRouteDetailScreen extends StatefulWidget {
  const AudioRouteDetailScreen({super.key, required this.slug});

  final String slug;

  @override
  State<AudioRouteDetailScreen> createState() => _AudioRouteDetailScreenState();
}

class _AudioRouteDetailScreenState extends State<AudioRouteDetailScreen> {
  late Future<Map<String, dynamic>> _future = _load();

  Future<Map<String, dynamic>> _load() async {
    final routeResponse = await ApiClient.instance.dio.get(
      '/mobile/routes/${widget.slug}',
    );
    Map<String, dynamic>? guide;
    try {
      final guideResponse = await ApiClient.instance.dio.get(
        '/mobile/audio-guides/routes/${widget.slug}',
      );
      guide = _asMap(guideResponse.data['data']);
    } on DioException catch (error) {
      if (error.response?.statusCode != 404) rethrow;
    }

    return {
      'route': _asMap(routeResponse.data['data']),
      'guide': guide,
    };
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Паломнический маршрут')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _ErrorPane(
              error: snapshot.error!,
              onRetry: () => setState(() => _future = _load()),
            );
          }
          final payload = snapshot.data!;
          final route = _asMap(payload['route']);
          final guide = _asMap(payload['guide']);
          final objects = _mapList(route['objects']);
          final cover = route['cover_url']?.toString();

          return ListView(
            padding: const EdgeInsets.only(bottom: 32),
            children: [
              if (cover != null && cover.isNotEmpty)
                Image.network(
                  cover,
                  height: 250,
                  width: double.infinity,
                  fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => _routePlaceholder(),
                )
              else
                _routePlaceholder(),
              Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${route['title'] ?? ''}',
                      style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                            color: AppTheme.green,
                            fontWeight: FontWeight.w700,
                          ),
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        if (route['duration_days'] != null)
                          Chip(label: Text('${route['duration_days']} дн.')),
                        if (route['duration_minutes'] != null)
                          Chip(label: Text('${route['duration_minutes']} мин.')),
                        if (route['difficulty'] != null)
                          Chip(label: Text('${route['difficulty']}')),
                      ],
                    ),
                    if (guide['url'] != null) ...[
                      const SizedBox(height: 20),
                      AudioGuidePlayer(
                        url: '${guide['url']}',
                        title: '${guide['title'] ?? route['title'] ?? 'Аудиогид'}',
                        transcript: guide['transcript']?.toString(),
                      ),
                    ],
                    if (_text(route['description']) != null)
                      _TextSection(
                        title: 'Описание маршрута',
                        text: '${route['description']}',
                      ),
                    if (_text(route['program']) != null)
                      _TextSection(
                        title: 'Программа',
                        text: '${route['program']}',
                      ),
                    if (objects.isNotEmpty) ...[
                      const _Heading('Точки маршрута'),
                      ...objects.asMap().entries.map(
                            (entry) => Card(
                              margin: const EdgeInsets.only(bottom: 10),
                              child: ListTile(
                                leading: CircleAvatar(
                                  backgroundColor: AppTheme.green,
                                  child: Text(
                                    '${entry.key + 1}',
                                    style: const TextStyle(color: Colors.white),
                                  ),
                                ),
                                title: Text('${entry.value['name'] ?? ''}'),
                                subtitle: Text(
                                  '${entry.value['address'] ?? ''}',
                                ),
                                trailing: const Icon(Icons.chevron_right),
                                onTap: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => AudioObjectDetailScreen(
                                      slug: '${entry.value['slug']}',
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ),
                    ],
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class AudioGuidesScreen extends StatefulWidget {
  const AudioGuidesScreen({super.key});

  @override
  State<AudioGuidesScreen> createState() => _AudioGuidesScreenState();
}

class _AudioGuidesScreenState extends State<AudioGuidesScreen> {
  late Future<List<Map<String, dynamic>>> _future = _load();

  Future<List<Map<String, dynamic>>> _load({bool refresh = false}) async {
    final payload = await CachedApi.instance.get(
      '/mobile/audio-guides',
      forceRefresh: refresh,
    ) as Map;
    return _mapList(payload['data']);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Аудиогиды')),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _ErrorPane(
              error: snapshot.error!,
              onRetry: () => setState(() => _future = _load(refresh: true)),
            );
          }
          final items = snapshot.data ?? const [];
          if (items.isEmpty) {
            return const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Text(
                  'Опубликованных аудиогидов пока нет.',
                  textAlign: TextAlign.center,
                ),
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: () async {
              final future = _load(refresh: true);
              setState(() => _future = future);
              await future;
            },
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: items.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                final item = items[index];
                final guide = _asMap(item['audio_guide']);
                return Card(
                  child: ListTile(
                    contentPadding: const EdgeInsets.all(16),
                    leading: const CircleAvatar(
                      backgroundColor: AppTheme.green,
                      child: Icon(Icons.headphones, color: Colors.white),
                    ),
                    title: Text(
                      '${item['title'] ?? guide['title'] ?? 'Аудиогид'}',
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    subtitle: Text('${item['subtitle'] ?? ''}'),
                    trailing: const Icon(Icons.play_circle_outline),
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => item['kind'] == 'route'
                            ? AudioRouteDetailScreen(slug: '${item['slug']}')
                            : AudioObjectDetailScreen(slug: '${item['slug']}'),
                      ),
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class _TextSection extends StatelessWidget {
  const _TextSection({required this.title, required this.text});

  final String title;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  color: AppTheme.green,
                  fontWeight: FontWeight.w700,
                ),
          ),
          const SizedBox(height: 10),
          SelectableText(text, style: const TextStyle(height: 1.55)),
        ],
      ),
    );
  }
}

class _Heading extends StatelessWidget {
  const _Heading(this.title);

  final String title;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 24, bottom: 10),
      child: Text(
        title,
        style: Theme.of(context).textTheme.titleLarge?.copyWith(
              color: AppTheme.green,
              fontWeight: FontWeight.w700,
            ),
      ),
    );
  }
}

class _ErrorPane extends StatelessWidget {
  const _ErrorPane({required this.error, required this.onRetry});

  final Object error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, size: 54, color: Colors.red),
            const SizedBox(height: 12),
            Text(
              ApiClient.instance.messageFrom(error),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 14),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: const Text('Повторить'),
            ),
          ],
        ),
      ),
    );
  }
}

String? _coverUrl(Map<String, dynamic> item) {
  final raw = item['cover_url'] ?? item['cover'];
  if (raw is Map) return raw['url']?.toString();
  final value = raw?.toString();
  return value == null || value.isEmpty ? null : value;
}

Map<String, dynamic> _asMap(dynamic value) => value is Map
    ? Map<String, dynamic>.from(value)
    : <String, dynamic>{};

List<Map<String, dynamic>> _mapList(dynamic value) => value is List
    ? value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList()
    : <Map<String, dynamic>>[];

String? _text(dynamic value) {
  final text = value?.toString().trim();
  return text == null || text.isEmpty ? null : text;
}

Widget _objectPlaceholder({double height = 150}) => SizedBox(
      height: height,
      width: double.infinity,
      child: const ColoredBox(
        color: AppTheme.cream,
        child: Center(
          child: Icon(Icons.church, size: 54, color: AppTheme.gold),
        ),
      ),
    );

Widget _routePlaceholder() => const SizedBox(
      height: 250,
      width: double.infinity,
      child: ColoredBox(
        color: AppTheme.cream,
        child: Center(
          child: Icon(Icons.route, size: 58, color: AppTheme.gold),
        ),
      ),
    );
