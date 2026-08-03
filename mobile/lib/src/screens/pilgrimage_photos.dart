import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../core/api_client.dart';
import '../theme/app_theme.dart';

class PilgrimagePhotosScreen extends StatefulWidget {
  const PilgrimagePhotosScreen({super.key});

  @override
  State<PilgrimagePhotosScreen> createState() => _PilgrimagePhotosScreenState();
}

class _PilgrimagePhotosScreenState extends State<PilgrimagePhotosScreen> {
  final ImagePicker _picker = ImagePicker();
  bool _uploading = false;
  List<Map<String, dynamic>> _routes = const [];

  @override
  void initState() {
    super.initState();
    _loadRoutes();
  }

  Future<List<Map<String, dynamic>>> _loadPhotos() async {
    final response = await ApiClient.instance.dio.get('/mobile/media');
    return _items(response.data['data']);
  }

  Future<void> _loadRoutes() async {
    try {
      final response = await ApiClient.instance.dio.get(
        '/mobile/routes',
        queryParameters: {'per_page': 100},
      );
      if (mounted) {
        setState(() => _routes = _items(response.data['data']));
      }
    } catch (_) {
      // The gallery remains usable privately even if route loading failed.
    }
  }

  Future<void> _pickAndUpload(ImageSource source) async {
    final file = await _picker.pickImage(
      source: source,
      imageQuality: 92,
      maxWidth: 3000,
    );
    if (file == null || !mounted) return;

    final title = TextEditingController(
      text: file.name.replaceAll(RegExp(r'\.[^.]+$'), ''),
    );
    final description = TextEditingController();
    int? routeId;
    bool requestPublication = false;

    final accepted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Паломническая фотография'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: title,
                  decoration: const InputDecoration(labelText: 'Название'),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: description,
                  minLines: 2,
                  maxLines: 5,
                  decoration: const InputDecoration(labelText: 'Описание'),
                ),
                const SizedBox(height: 10),
                DropdownButtonFormField<int>(
                  value: routeId,
                  isExpanded: true,
                  decoration: const InputDecoration(
                    labelText: 'Паломнический маршрут',
                  ),
                  items: _routes
                      .where((route) => route['id'] is int)
                      .map(
                        (route) => DropdownMenuItem<int>(
                          value: route['id'] as int,
                          child: Text(
                            '${route['title'] ?? 'Маршрут'}',
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      )
                      .toList(),
                  onChanged: (value) => setDialogState(() => routeId = value),
                ),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  value: requestPublication,
                  title: const Text('Опубликовать на общем сайте'),
                  subtitle: const Text('После проверки модератором'),
                  onChanged: (value) =>
                      setDialogState(() => requestPublication = value),
                ),
                if (requestPublication && routeId == null)
                  const Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'Для публикации выберите маршрут.',
                      style: TextStyle(color: Colors.red, fontSize: 12),
                    ),
                  ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Отмена'),
            ),
            FilledButton(
              onPressed: requestPublication && routeId == null
                  ? null
                  : () => Navigator.pop(dialogContext, true),
              child: const Text('Загрузить'),
            ),
          ],
        ),
      ),
    );

    if (accepted != true) {
      title.dispose();
      description.dispose();
      return;
    }

    setState(() => _uploading = true);
    try {
      final form = FormData.fromMap({
        'file': await MultipartFile.fromFile(file.path, filename: file.name),
        'title': title.text.trim().isEmpty ? null : title.text.trim(),
        'description': description.text.trim().isEmpty
            ? null
            : description.text.trim(),
        'pilgrimage_route_id': routeId,
        'request_publication': requestPublication ? '1' : '0',
      });

      await ApiClient.instance.dio.post('/mobile/media', data: form);

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              requestPublication
                  ? 'Фотография отправлена на модерацию.'
                  : 'Фотография сохранена только для вас.',
            ),
          ),
        );
        setState(() {});
      }
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      title.dispose();
      description.dispose();
      if (mounted) setState(() => _uploading = false);
    }
  }

  Future<void> _requestPublication(Map<String, dynamic> photo) async {
    final currentRoute = photo['route'] is Map
        ? Map<String, dynamic>.from(photo['route'] as Map)
        : const <String, dynamic>{};
    int? routeId = currentRoute['id'] as int?;

    final accepted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Отправить на публикацию'),
          content: DropdownButtonFormField<int>(
            value: routeId,
            isExpanded: true,
            decoration: const InputDecoration(
              labelText: 'Паломнический маршрут',
            ),
            items: _routes
                .where((route) => route['id'] is int)
                .map(
                  (route) => DropdownMenuItem<int>(
                    value: route['id'] as int,
                    child: Text(
                      '${route['title'] ?? 'Маршрут'}',
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                )
                .toList(),
            onChanged: (value) => setDialogState(() => routeId = value),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Отмена'),
            ),
            FilledButton(
              onPressed: routeId == null
                  ? null
                  : () => Navigator.pop(dialogContext, true),
              child: const Text('На модерацию'),
            ),
          ],
        ),
      ),
    );

    if (accepted != true || routeId == null) return;

    try {
      await ApiClient.instance.dio.post(
        '/mobile/media/${photo['id']}/publication',
        data: {'pilgrimage_route_id': routeId},
      );
      if (mounted) setState(() {});
    } catch (error) {
      if (mounted) _showError(error);
    }
  }

  Future<void> _withdrawPublication(Map<String, dynamic> photo) async {
    try {
      await ApiClient.instance.dio.delete(
        '/mobile/media/${photo['id']}/publication',
      );
      if (mounted) setState(() {});
    } catch (error) {
      if (mounted) _showError(error);
    }
  }

  Future<void> _deletePhoto(Map<String, dynamic> photo) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Удалить фотографию?'),
        content: const Text('Восстановить удалённый файл будет невозможно.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Отмена'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Удалить'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await ApiClient.instance.dio.delete('/mobile/media/${photo['id']}');
      if (mounted) setState(() {});
    } catch (error) {
      if (mounted) _showError(error);
    }
  }

  void _showError(Object error) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(ApiClient.instance.messageFrom(error)),
        backgroundColor: Colors.red,
      ),
    );
  }

  String _statusLabel(String status) {
    switch (status) {
      case 'private':
        return 'Только мне';
      case 'pending':
        return 'На модерации';
      case 'published':
        return 'Опубликовано';
      case 'rejected':
        return 'Отклонено';
      default:
        return status;
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Паломнические фото')),
        floatingActionButton: FloatingActionButton.extended(
          onPressed: _uploading
              ? null
              : () => showModalBottomSheet<void>(
                    context: context,
                    builder: (context) => SafeArea(
                      child: Wrap(
                        children: [
                          ListTile(
                            leading: const Icon(Icons.photo_camera),
                            title: const Text('Сделать фотографию'),
                            onTap: () {
                              Navigator.pop(context);
                              _pickAndUpload(ImageSource.camera);
                            },
                          ),
                          ListTile(
                            leading: const Icon(Icons.photo_library),
                            title: const Text('Выбрать из галереи'),
                            onTap: () {
                              Navigator.pop(context);
                              _pickAndUpload(ImageSource.gallery);
                            },
                          ),
                        ],
                      ),
                    ),
                  ),
          icon: _uploading
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.add_a_photo),
          label: Text(_uploading ? 'Загрузка' : 'Добавить'),
        ),
        body: FutureBuilder<List<Map<String, dynamic>>>(
          future: _loadPhotos(),
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        ApiClient.instance.messageFrom(snapshot.error!),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 12),
                      OutlinedButton(
                        onPressed: () => setState(() {}),
                        child: const Text('Повторить'),
                      ),
                    ],
                  ),
                ),
              );
            }

            final photos = snapshot.data ?? const [];
            if (photos.isEmpty) {
              return const Center(
                child: Padding(
                  padding: EdgeInsets.all(28),
                  child: Text(
                    'Паломнических фотографий пока нет. Сделайте первый снимок или выберите его из галереи.',
                    textAlign: TextAlign.center,
                  ),
                ),
              );
            }

            return RefreshIndicator(
              onRefresh: () async => setState(() {}),
              child: GridView.builder(
                padding: const EdgeInsets.fromLTRB(12, 12, 12, 90),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  mainAxisSpacing: 10,
                  crossAxisSpacing: 10,
                  childAspectRatio: .68,
                ),
                itemCount: photos.length,
                itemBuilder: (context, index) {
                  final photo = photos[index];
                  final route = photo['route'] is Map
                      ? Map<String, dynamic>.from(photo['route'] as Map)
                      : const <String, dynamic>{};
                  final status = '${photo['status'] ?? 'private'}';

                  return Card(
                    clipBehavior: Clip.antiAlias,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Expanded(
                          child: Image.network(
                            '${photo['url'] ?? ''}',
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) => const Center(
                              child: Icon(Icons.broken_image, size: 42),
                            ),
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.all(9),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${photo['title'] ?? 'Паломническое фото'}',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(fontWeight: FontWeight.w600),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                _statusLabel(status),
                                style: const TextStyle(
                                  fontSize: 12,
                                  color: Colors.black54,
                                ),
                              ),
                              if (route['title'] != null)
                                Text(
                                  '${route['title']}',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontSize: 11,
                                    color: AppTheme.green,
                                  ),
                                ),
                              if (photo['moderation_notes'] != null)
                                Text(
                                  '${photo['moderation_notes']}',
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontSize: 11,
                                    color: Colors.red,
                                  ),
                                ),
                              Align(
                                alignment: Alignment.centerRight,
                                child: PopupMenuButton<String>(
                                  onSelected: (value) {
                                    if (value == 'publish') {
                                      _requestPublication(photo);
                                    } else if (value == 'withdraw') {
                                      _withdrawPublication(photo);
                                    } else if (value == 'delete') {
                                      _deletePhoto(photo);
                                    }
                                  },
                                  itemBuilder: (_) => [
                                    if (status == 'private' || status == 'rejected')
                                      const PopupMenuItem(
                                        value: 'publish',
                                        child: Text('Опубликовать'),
                                      ),
                                    if (status == 'pending' || status == 'published')
                                      const PopupMenuItem(
                                        value: 'withdraw',
                                        child: Text('Снять с публикации'),
                                      ),
                                    const PopupMenuItem(
                                      value: 'delete',
                                      child: Text('Удалить'),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  );
                },
              ),
            );
          },
        ),
      );

  List<Map<String, dynamic>> _items(dynamic value) {
    final list = value is List ? value : const [];
    return list
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }
}
