import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:moscow_pilgrim/core/api_client.dart';
import 'package:moscow_pilgrim/core/app_theme.dart';
import 'package:moscow_pilgrim/models/models.dart';
import 'package:moscow_pilgrim/screens/object_detail_screen.dart';
import 'package:moscow_pilgrim/widgets/common.dart';

class ObjectsScreen extends StatefulWidget {
  const ObjectsScreen({super.key, this.initialQuery});

  final String? initialQuery;

  @override
  State<ObjectsScreen> createState() => _ObjectsScreenState();
}

class _ObjectsScreenState extends State<ObjectsScreen> {
  final _searchController = TextEditingController();
  List<PilgrimageObjectModel> _objects = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _searchController.text = widget.initialQuery ?? '';
    _load();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await ApiClient.instance.getJson(
        '/objects',
        query: {
          if (_searchController.text.trim().isNotEmpty) 'q': _searchController.text.trim(),
          'per_page': 50,
        },
      );
      final data = response['data'] as List? ?? const [];
      if (!mounted) return;
      setState(() {
        _objects = data
            .whereType<Map>()
            .map((item) => PilgrimageObjectModel.fromJson(Map<String, dynamic>.from(item)))
            .toList();
      });
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Храмы и святыни')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: SearchBar(
              controller: _searchController,
              hintText: 'Название, адрес или святыня',
              leading: const Icon(Icons.search),
              trailing: [
                if (_searchController.text.isNotEmpty)
                  IconButton(
                    onPressed: () {
                      _searchController.clear();
                      _load();
                    },
                    icon: const Icon(Icons.close),
                  ),
              ],
              onSubmitted: (_) => _load(),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _load,
              child: _buildContent(),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildContent() {
    if (_loading) return const LoadingView(label: 'Загружаем каталог...');
    if (_error != null) return ErrorView(message: _error!, onRetry: _load);
    if (_objects.isEmpty) {
      return ListView(
        children: const [
          SizedBox(height: 160),
          EmptyView(
            title: 'Объекты не найдены',
            message: 'Измените поисковый запрос или очистите фильтры.',
            icon: Icons.church_outlined,
          ),
        ],
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
      itemCount: _objects.length,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (context, index) {
        final object = _objects[index];
        return Card(
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: () => openPage(context, ObjectDetailScreen(slug: object.slug)),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(
                  width: 120,
                  height: 136,
                  child: object.coverUrl != null
                      ? CachedNetworkImage(
                          imageUrl: object.coverUrl!,
                          fit: BoxFit.cover,
                          errorWidget: (_, _, _) => _placeholder(),
                        )
                      : _placeholder(),
                ),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.all(15),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (object.typeName != null)
                          Text(
                            object.typeName!.toUpperCase(),
                            style: const TextStyle(
                              color: AppTheme.gold,
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.7,
                            ),
                          ),
                        const SizedBox(height: 5),
                        Text(
                          object.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          object.address ?? 'Адрес уточняется',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant, fontSize: 13),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _placeholder() {
    return Container(
      color: AppTheme.cream,
      alignment: Alignment.center,
      child: const Icon(Icons.church_rounded, size: 40, color: AppTheme.gold),
    );
  }
}
