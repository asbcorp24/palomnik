import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../data/cached_api.dart';
import 'audio_guides.dart';

class SortedObjectCatalogTab extends StatefulWidget {
  const SortedObjectCatalogTab({super.key});

  @override
  State<SortedObjectCatalogTab> createState() =>
      _SortedObjectCatalogTabState();
}

class _SortedObjectCatalogTabState extends State<SortedObjectCatalogTab> {
  final TextEditingController _search = TextEditingController();
  String _sort = 'none';
  String? _type;
  String? _vicariate;
  String? _deanery;
  String? _sanctity;
  late final Future<Map<String, List<Map<String, dynamic>>>> _directories = _loadDirectories();
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
        'sort': _sort,
        if (_type != null) 'type': _type,
        if (_vicariate != null) 'vicariate': _vicariate,
        if (_deanery != null) 'deanery': _deanery,
        if (_sanctity != null) 'sanctity': _sanctity,
        if (_search.text.trim().isNotEmpty) 'q': _search.text.trim(),
      },
      forceRefresh: refresh,
    ) as Map;

    return _mapList(payload['data']);
  }

  Future<Map<String, List<Map<String, dynamic>>>> _loadDirectories() async {
    final results = await Future.wait([
      ApiClient.instance.dio.get('/directories/object-types'),
      ApiClient.instance.dio.get('/directories/vicariates'),
      ApiClient.instance.dio.get('/directories/deaneries'),
      ApiClient.instance.dio.get('/directories/sanctities'),
    ]);
    return {
      'types': _mapList(results[0].data['data']),
      'vicariates': _mapList(results[1].data['data']),
      'deaneries': _mapList(results[2].data['data']),
      'sanctities': _mapList(results[3].data['data']),
    };
  }

  void _resetFilters() {
    setState(() {
      _search.clear();
      _sort = 'none';
      _type = null;
      _vicariate = null;
      _deanery = null;
      _sanctity = null;
      _future = _load(refresh: true);
    });
  }

  void _reload() {
    setState(() => _future = _load(refresh: true));
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
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _reload(),
              decoration: InputDecoration(
                hintText: 'Название, адрес или святыня',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: IconButton(
                  tooltip: 'Найти',
                  onPressed: _reload,
                  icon: const Icon(Icons.arrow_forward),
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
            child: FutureBuilder<Map<String, List<Map<String, dynamic>>>>(
              future: _directories,
              builder: (context, snapshot) {
                final directories = snapshot.data ?? const {};
                final deaneries = (directories['deaneries'] ?? const [])
                    .where((item) => _vicariate == null || (item['vicariate'] is Map && item['vicariate']['slug'] == _vicariate))
                    .toList();
                return ExpansionTile(
                  tilePadding: EdgeInsets.zero,
                  leading: const Icon(Icons.tune),
                  title: const Text('Фильтры каталога'),
                  subtitle: Text([_type, _vicariate, _deanery, _sanctity].whereType<String>().isEmpty ? 'Все объекты' : 'Фильтры применены'),
                  children: [
                    _filterDropdown('Тип объекта', _type, directories['types'] ?? const [], (value) => setState(() => _type = value)),
                    _filterDropdown('Викариатство', _vicariate, directories['vicariates'] ?? const [], (value) => setState(() { _vicariate = value; _deanery = null; })),
                    _filterDropdown('Благочиние', _deanery, deaneries, (value) => setState(() => _deanery = value)),
                    _filterDropdown('Святыня', _sanctity, directories['sanctities'] ?? const [], (value) => setState(() => _sanctity = value)),
                    DropdownButtonFormField<String>(
                      value: _sort,
                      isExpanded: true,
                      decoration: const InputDecoration(labelText: 'Сортировка', prefixIcon: Icon(Icons.sort)),
                      items: const [
                        DropdownMenuItem(value: 'none', child: Text('По названию')),
                        DropdownMenuItem(value: 'popular', child: Text('Популярные')),
                        DropdownMenuItem(value: 'reviews', child: Text('С отзывами')),
                        DropdownMenuItem(value: 'newest', child: Text('Новые')),
                      ],
                      onChanged: (value) => setState(() => _sort = value ?? 'none'),
                    ),
                    const SizedBox(height: 10),
                    Row(children: [
                      Expanded(child: OutlinedButton(onPressed: _resetFilters, child: const Text('Сбросить'))),
                      const SizedBox(width: 10),
                      Expanded(child: FilledButton(onPressed: _reload, child: const Text('Применить'))),
                    ]),
                    const SizedBox(height: 8),
                  ],
                );
              },
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
                  return _CatalogError(
                    error: snapshot.error!,
                    onRetry: _reload,
                  );
                }

                final items = snapshot.data ?? const [];
                if (items.isEmpty) {
                  return Center(
                    child: Padding(
                      padding: const EdgeInsets.all(28),
                      child: Text(
                        _sort == 'reviews'
                            ? 'Объектов с опубликованными отзывами пока нет.'
                            : 'Объекты не найдены.',
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

  Widget _filterDropdown(String label, String? value, List<Map<String, dynamic>> items, ValueChanged<String?> onChanged) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: DropdownButtonFormField<String>(
        value: items.any((item) => item['slug'] == value) ? value : null,
        isExpanded: true,
        decoration: InputDecoration(labelText: label),
        items: [
          const DropdownMenuItem<String>(value: null, child: Text('Все')),
          ...items.map((item) => DropdownMenuItem<String>(value: '${item['slug']}', child: Text('${item['name'] ?? ''}', overflow: TextOverflow.ellipsis))),
        ],
        onChanged: onChanged,
      ),
    );
  }
}

class _CatalogError extends StatelessWidget {
  const _CatalogError({required this.error, required this.onRetry});

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
            Text(
              ApiClient.instance.messageFrom(error),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 14),
            OutlinedButton.icon(
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

List<Map<String, dynamic>> _mapList(dynamic value) {
  final list = value is List ? value : const [];
  return list
      .whereType<Map>()
      .map((item) => Map<String, dynamic>.from(item))
      .toList();
}
