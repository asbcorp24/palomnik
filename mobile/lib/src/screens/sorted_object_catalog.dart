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
        if (_search.text.trim().isNotEmpty) 'q': _search.text.trim(),
      },
      forceRefresh: refresh,
    ) as Map;

    return _mapList(payload['data']);
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
            child: DropdownButtonFormField<String>(
              value: _sort,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Сортировка',
                prefixIcon: Icon(Icons.sort),
              ),
              items: const [
                DropdownMenuItem(
                  value: 'none',
                  child: Text('Без сортировки'),
                ),
                DropdownMenuItem(
                  value: 'popular',
                  child: Text('Популярные'),
                ),
                DropdownMenuItem(
                  value: 'reviews',
                  child: Text('С отзывами'),
                ),
              ],
              onChanged: (value) {
                if (value == null || value == _sort) return;
                _sort = value;
                _reload();
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
