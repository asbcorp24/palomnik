import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../core/api_client.dart';
import '../theme/app_theme.dart';
import 'user_features.dart';

class MyPostsScreen extends StatefulWidget {
  const MyPostsScreen({super.key});

  @override
  State<MyPostsScreen> createState() => _MyPostsScreenState();
}

class _MyPostsScreenState extends State<MyPostsScreen> {
  Future<List<Map<String, dynamic>>> _load() async {
    final response = await ApiClient.instance.dio.get('/mobile/posts');
    return _items(response.data['data']);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: const Text('Мои путевые заметки'),
          actions: [
            IconButton(
              onPressed: () async {
                final created = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => const CreatePostScreen()));
                if (created == true) setState(() {});
              },
              icon: const Icon(Icons.add),
            ),
          ],
        ),
        body: FutureBuilder<List<Map<String, dynamic>>>(
          future: _load(),
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
            if (snapshot.hasError) return FeatureError(error: snapshot.error!, onRetry: () => setState(() {}));
            final posts = snapshot.data!;
            if (posts.isEmpty) {
              return Center(
                child: FilledButton.icon(
                  onPressed: () async {
                    final created = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => const CreatePostScreen()));
                    if (created == true) setState(() {});
                  },
                  icon: const Icon(Icons.edit_note),
                  label: const Text('Написать первую заметку'),
                ),
              );
            }
            return RefreshIndicator(
              onRefresh: () async => setState(() {}),
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: posts.length,
                itemBuilder: (context, index) {
                  final post = posts[index];
                  return Card(
                    child: ListTile(
                      contentPadding: const EdgeInsets.all(16),
                      leading: const CircleAvatar(child: Icon(Icons.article_outlined)),
                      title: Text('${post['title'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w700)),
                      subtitle: Text('${post['status'] ?? 'draft'}\n${post['excerpt'] ?? ''}', maxLines: 4, overflow: TextOverflow.ellipsis),
                      trailing: PopupMenuButton<String>(
                        onSelected: (value) async {
                          if (value == 'edit') {
                            final changed = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => CreatePostScreen(post: post)));
                            if (changed == true) setState(() {});
                          } else if (value == 'delete') {
                            await ApiClient.instance.dio.delete('/mobile/posts/${post['id']}');
                            setState(() {});
                          }
                        },
                        itemBuilder: (_) => const [
                          PopupMenuItem(value: 'edit', child: Text('Редактировать')),
                          PopupMenuItem(value: 'delete', child: Text('Удалить')),
                        ],
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

class CreatePostScreen extends StatefulWidget {
  const CreatePostScreen({super.key, this.post});
  final Map<String, dynamic>? post;

  @override
  State<CreatePostScreen> createState() => _CreatePostScreenState();
}

class _CreatePostScreenState extends State<CreatePostScreen> {
  late final TextEditingController _title = TextEditingController(text: '${widget.post?['title'] ?? ''}');
  late final TextEditingController _excerpt = TextEditingController(text: '${widget.post?['excerpt'] ?? ''}');
  late final TextEditingController _body = TextEditingController(text: '${widget.post?['body'] ?? ''}');
  bool _busy = false;

  @override
  void dispose() {
    _title.dispose();
    _excerpt.dispose();
    _body.dispose();
    super.dispose();
  }

  Future<void> _save(bool publish) async {
    if (_title.text.trim().isEmpty || _body.text.trim().length < 50) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Введите название и текст не короче 50 символов.')));
      return;
    }
    setState(() => _busy = true);
    try {
      final data = {
        'title': _title.text.trim(),
        'excerpt': _excerpt.text.trim().isEmpty ? null : _excerpt.text.trim(),
        'body': _body.text.trim(),
        'publish': publish,
      };
      if (widget.post == null) {
        await ApiClient.instance.dio.post('/mobile/posts', data: data);
      } else {
        await ApiClient.instance.dio.put('/mobile/posts/${widget.post!['id']}', data: data);
      }
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.instance.messageFrom(error)), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(widget.post == null ? 'Новая заметка' : 'Редактирование заметки')),
        body: ListView(padding: const EdgeInsets.all(18), children: [
          TextField(controller: _title, decoration: const InputDecoration(labelText: 'Название')),
          const SizedBox(height: 12),
          TextField(controller: _excerpt, minLines: 2, maxLines: 4, decoration: const InputDecoration(labelText: 'Краткое описание')),
          const SizedBox(height: 12),
          TextField(controller: _body, minLines: 12, maxLines: 30, decoration: const InputDecoration(labelText: 'Текст путевой заметки')),
          const SizedBox(height: 20),
          Row(children: [
            Expanded(child: OutlinedButton(onPressed: _busy ? null : () => _save(false), child: const Text('Сохранить черновик'))),
            const SizedBox(width: 10),
            Expanded(child: FilledButton(onPressed: _busy ? null : () => _save(true), child: const Text('На модерацию'))),
          ]),
        ]),
      );
}

class MediaScreen extends StatefulWidget {
  const MediaScreen({super.key});

  @override
  State<MediaScreen> createState() => _MediaScreenState();
}

class _MediaScreenState extends State<MediaScreen> {
  final _picker = ImagePicker();
  bool _uploading = false;

  Future<List<Map<String, dynamic>>> _load() async {
    final response = await ApiClient.instance.dio.get('/mobile/media');
    return _items(response.data['data']);
  }

  Future<void> _upload(ImageSource source) async {
    final file = await _picker.pickImage(source: source, imageQuality: 90, maxWidth: 2400);
    if (file == null) return;
    setState(() => _uploading = true);
    try {
      final form = FormData.fromMap({
        'file': await MultipartFile.fromFile(file.path, filename: file.name),
        'title': file.name,
      });
      await ApiClient.instance.dio.post('/mobile/media', data: form);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Фотография отправлена на модерацию.')));
        setState(() {});
      }
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.instance.messageFrom(error)), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Мои фото и видео')),
        floatingActionButton: FloatingActionButton.extended(
          onPressed: _uploading
              ? null
              : () => showModalBottomSheet<void>(
                    context: context,
                    builder: (context) => SafeArea(
                      child: Wrap(children: [
                        ListTile(leading: const Icon(Icons.photo_library), title: const Text('Выбрать из галереи'), onTap: () { Navigator.pop(context); _upload(ImageSource.gallery); }),
                        ListTile(leading: const Icon(Icons.photo_camera), title: const Text('Сделать фотографию'), onTap: () { Navigator.pop(context); _upload(ImageSource.camera); }),
                      ]),
                    ),
                  ),
          icon: _uploading ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.add_a_photo),
          label: Text(_uploading ? 'Загрузка' : 'Добавить'),
        ),
        body: FutureBuilder<List<Map<String, dynamic>>>(
          future: _load(),
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
            if (snapshot.hasError) return FeatureError(error: snapshot.error!, onRetry: () => setState(() {}));
            final media = snapshot.data!;
            if (media.isEmpty) return const Center(child: Text('Загруженных материалов пока нет.'));
            return GridView.builder(
              padding: const EdgeInsets.all(12),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 2, mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: .85),
              itemCount: media.length,
              itemBuilder: (context, index) {
                final item = media[index];
                return Card(
                  clipBehavior: Clip.antiAlias,
                  child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                    Expanded(
                      child: item['type'] == 'image' && item['url'] != null
                          ? Image.network('${item['url']}', fit: BoxFit.cover, errorBuilder: (_, __, ___) => const Icon(Icons.broken_image))
                          : const ColoredBox(color: AppTheme.cream, child: Icon(Icons.videocam, size: 46)),
                    ),
                    Padding(
                      padding: const EdgeInsets.all(9),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('${item['title'] ?? 'Материал'}', maxLines: 1, overflow: TextOverflow.ellipsis),
                        Text('${item['status'] ?? ''}', style: const TextStyle(fontSize: 12, color: Colors.black54)),
                      ]),
                    ),
                  ]),
                );
              },
            );
          },
        ),
      );
}

class RouteBuilderScreen extends StatefulWidget {
  const RouteBuilderScreen({super.key});

  @override
  State<RouteBuilderScreen> createState() => _RouteBuilderScreenState();
}

class _RouteBuilderScreenState extends State<RouteBuilderScreen> {
  final _name = TextEditingController();
  final Set<int> _selected = {};
  String _transport = 'pedestrian';
  bool _busy = false;
  late Future<List<Map<String, dynamic>>> _future = _load();

  Future<List<Map<String, dynamic>>> _load() async {
    final response = await ApiClient.instance.dio.get('/objects', queryParameters: {'per_page': 50});
    return _items(response.data['data']);
  }

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_name.text.trim().isEmpty || _selected.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Введите название и выберите хотя бы один объект.')));
      return;
    }
    setState(() => _busy = true);
    try {
      await ApiClient.instance.dio.post('/mobile/route-plans', data: {
        'name': _name.text.trim(),
        'transport_mode': _transport,
        'object_ids': _selected.toList(),
      });
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.instance.messageFrom(error)), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Конструктор маршрута')),
        body: FutureBuilder<List<Map<String, dynamic>>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
            if (snapshot.hasError) return FeatureError(error: snapshot.error!, onRetry: () => setState(() => _future = _load()));
            final objects = snapshot.data!;
            return Column(children: [
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(children: [
                  TextField(controller: _name, decoration: const InputDecoration(labelText: 'Название маршрута')),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    value: _transport,
                    decoration: const InputDecoration(labelText: 'Способ передвижения'),
                    items: const [
                      DropdownMenuItem(value: 'pedestrian', child: Text('Пешком')),
                      DropdownMenuItem(value: 'auto', child: Text('Автомобиль')),
                      DropdownMenuItem(value: 'masstransit', child: Text('Общественный транспорт')),
                    ],
                    onChanged: (value) => setState(() => _transport = value ?? 'pedestrian'),
                  ),
                ]),
              ),
              Expanded(
                child: ListView.builder(
                  itemCount: objects.length,
                  itemBuilder: (context, index) {
                    final object = objects[index];
                    final id = int.tryParse('${object['id']}') ?? 0;
                    return CheckboxListTile(
                      value: _selected.contains(id),
                      onChanged: (value) => setState(() => value == true ? _selected.add(id) : _selected.remove(id)),
                      title: Text('${object['name'] ?? ''}'),
                      subtitle: Text('${object['address'] ?? (object['location'] is Map ? object['location']['address'] : '')}'),
                    );
                  },
                ),
              ),
              SafeArea(top: false, child: Padding(padding: const EdgeInsets.all(16), child: SizedBox(width: double.infinity, child: FilledButton(onPressed: _busy ? null : _save, child: Text(_busy ? 'Сохранение...' : 'Создать маршрут'))))),
            ]);
          },
        ),
      );
}

List<Map<String, dynamic>> _items(dynamic data) {
  final list = data is List ? data : const [];
  return list.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList();
}
