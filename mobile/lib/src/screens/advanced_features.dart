import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:yandex_mapkit/yandex_mapkit.dart';

import '../core/api_client.dart';
import '../core/session_controller.dart';
import '../data/cached_api.dart';
import '../data/offline_store.dart';
import '../theme/app_theme.dart';
import 'user_features.dart';

class NativeMapTab extends StatefulWidget {
  const NativeMapTab({super.key});

  @override
  State<NativeMapTab> createState() => _NativeMapTabState();
}

class _NativeMapTabState extends State<NativeMapTab> {
  final _search = TextEditingController();
  YandexMapController? _controller;
  List<Map<String, dynamic>> _objects = const [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    _controller?.dispose();
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
        queryParameters: {'per_page': 50, if (_search.text.trim().isNotEmpty) 'q': _search.text.trim()},
        forceRefresh: refresh,
      ) as Map;
      final items = (payload['data'] as List? ?? [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .where((item) {
            final location = _asMap(item['location']);
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

  List<MapObject> get _placemarks => _objects.map((item) {
        final location = _asMap(item['location']);
        return PlacemarkMapObject(
          mapId: MapObjectId('object_${item['id']}'),
          point: Point(
            latitude: (location['latitude'] as num).toDouble(),
            longitude: (location['longitude'] as num).toDouble(),
          ),
          opacity: 1,
          consumeTapEvents: true,
          onTap: (_, __) => _showObject(item),
        );
      }).toList();

  Future<void> _showObject(Map<String, dynamic> item) async {
    final location = _asMap(item['location']);
    if (!mounted) return;
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('${item['name'] ?? ''}', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 8),
            Text('${location['address'] ?? ''}', style: const TextStyle(color: Colors.black54)),
            const SizedBox(height: 16),
            Row(children: [
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
                  onPressed: () => launchUrl(
                    Uri.parse('https://yandex.ru/maps/?rtext=~${location['latitude']},${location['longitude']}&rtt=auto'),
                    mode: LaunchMode.externalApplication,
                  ),
                  icon: const Icon(Icons.directions),
                  label: const Text('Маршрут'),
                ),
              ),
            ]),
            const SizedBox(height: 8),
            Row(children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () async {
                    final detail = await CachedApi.instance.get('/objects/${item['slug']}', forceRefresh: true) as Map;
                    await OfflineStore.instance.saveObject(Map<String, dynamic>.from(detail['data'] as Map));
                    if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Карточка сохранена для работы без сети.')));
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
            ]),
          ]),
        ),
      ),
    );
  }

  Future<void> _myLocation() async {
    final permission = await _locationPermission();
    if (!permission || _controller == null) return;
    final position = await Geolocator.getCurrentPosition();
    await _controller!.toggleUserLayer(visible: true, autoZoomEnabled: false);
    await _controller!.moveCamera(
      CameraUpdate.newCameraPosition(CameraPosition(
        target: Point(latitude: position.latitude, longitude: position.longitude),
        zoom: 14,
      )),
      animation: const MapAnimation(type: MapAnimationType.smooth, duration: 1),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: const Text('Интерактивная карта'),
          actions: [
            IconButton(onPressed: _myLocation, tooltip: 'Моё местоположение', icon: const Icon(Icons.my_location)),
            IconButton(onPressed: () => _load(refresh: true), tooltip: 'Обновить', icon: const Icon(Icons.refresh)),
          ],
        ),
        body: Stack(children: [
          YandexMap(
            mapObjects: _placemarks,
            onMapCreated: (controller) async {
              _controller = controller;
              await controller.moveCamera(
                CameraUpdate.newCameraPosition(const CameraPosition(target: Point(latitude: 55.751244, longitude: 37.618423), zoom: 9)),
              );
            },
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
          if (_loading) const Positioned(top: 86, left: 16, right: 16, child: LinearProgressIndicator()),
          if (_error != null)
            Positioned(
              left: 16,
              right: 16,
              bottom: 24,
              child: Material(
                color: Colors.red.shade50,
                borderRadius: BorderRadius.circular(16),
                child: Padding(padding: const EdgeInsets.all(14), child: Text(_error!, style: TextStyle(color: Colors.red.shade900))),
              ),
            ),
        ]),
      );
}

class GeoVisitScreen extends StatefulWidget {
  const GeoVisitScreen({super.key, this.initialObject});
  final Map<String, dynamic>? initialObject;

  @override
  State<GeoVisitScreen> createState() => _GeoVisitScreenState();
}

class _GeoVisitScreenState extends State<GeoVisitScreen> {
  List<Map<String, dynamic>> _objects = const [];
  int? _objectId;
  bool _busy = false;
  String? _status;

  @override
  void initState() {
    super.initState();
    _objectId = widget.initialObject?['id'] as int?;
    _loadObjects();
  }

  Future<void> _loadObjects() async {
    final payload = await CachedApi.instance.get('/objects', queryParameters: {'per_page': 50}) as Map;
    if (mounted) {
      setState(() => _objects = (payload['data'] as List? ?? []).map((item) => Map<String, dynamic>.from(item as Map)).toList());
    }
  }

  Future<void> _submit() async {
    if (_objectId == null) return;
    setState(() {
      _busy = true;
      _status = 'Определяем местоположение…';
    });
    try {
      final allowed = await _locationPermission();
      if (!allowed) throw Exception('Разрешение на геолокацию не предоставлено.');
      final position = await Geolocator.getCurrentPosition(desiredAccuracy: LocationAccuracy.high);
      await ApiClient.instance.dio.post('/mobile/visits', data: {
        'pilgrimage_object_id': _objectId,
        'latitude': position.latitude,
        'longitude': position.longitude,
      });
      if (mounted) setState(() => _status = 'Посещение отправлено на проверку с координатами.');
    } catch (error) {
      if (mounted) setState(() => _status = ApiClient.instance.messageFrom(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Отметить посещение')),
        body: ListView(padding: const EdgeInsets.all(18), children: [
          const Icon(Icons.where_to_vote, size: 72, color: AppTheme.gold),
          const SizedBox(height: 18),
          const Text('Для подтверждения посещения приложение передаст текущие координаты. Геолокация не публикуется в профиле.', textAlign: TextAlign.center),
          const SizedBox(height: 24),
          DropdownButtonFormField<int>(
            value: _objectId,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Храм или святыня'),
            items: _objects.map((item) => DropdownMenuItem(value: item['id'] as int, child: Text('${item['name']}'))).toList(),
            onChanged: (value) => setState(() => _objectId = value),
          ),
          const SizedBox(height: 18),
          FilledButton.icon(onPressed: _busy || _objectId == null ? null : _submit, icon: const Icon(Icons.my_location), label: Text(_busy ? 'Проверяем…' : 'Подтвердить посещение')),
          if (_status != null) Padding(padding: const EdgeInsets.only(top: 16), child: Text(_status!, textAlign: TextAlign.center)),
        ]),
      );
}

class OfflineObjectsScreen extends StatefulWidget {
  const OfflineObjectsScreen({super.key});

  @override
  State<OfflineObjectsScreen> createState() => _OfflineObjectsScreenState();
}

class _OfflineObjectsScreenState extends State<OfflineObjectsScreen> {
  Future<List<Map<String, dynamic>>> _load() => OfflineStore.instance.savedObjects();

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: const Text('Сохранено офлайн'),
          actions: [IconButton(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const OfflineDownloadScreen())).then((_) => setState(() {})), icon: const Icon(Icons.add))],
        ),
        body: FutureBuilder<List<Map<String, dynamic>>>(
          future: _load(),
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
            final items = snapshot.data ?? const [];
            if (items.isEmpty) return const Center(child: Text('Сохранённых карточек пока нет.'));
            return ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: items.length,
              itemBuilder: (context, index) {
                final item = items[index];
                return Card(
                  child: ListTile(
                    leading: const Icon(Icons.offline_pin, color: AppTheme.green),
                    title: Text('${item['name'] ?? ''}'),
                    subtitle: Text('${_asMap(item['location'])['address'] ?? item['address'] ?? ''}'),
                    trailing: IconButton(onPressed: () async {
                      await OfflineStore.instance.removeObject(item['id'] as int);
                      setState(() {});
                    }, icon: const Icon(Icons.delete_outline)),
                    onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => OfflineObjectDetail(item: item))),
                  ),
                );
              },
            );
          },
        ),
      );
}

class OfflineDownloadScreen extends StatefulWidget {
  const OfflineDownloadScreen({super.key});

  @override
  State<OfflineDownloadScreen> createState() => _OfflineDownloadScreenState();
}

class _OfflineDownloadScreenState extends State<OfflineDownloadScreen> {
  late Future<List<Map<String, dynamic>>> _future = _load();
  final Set<int> _busy = {};

  Future<List<Map<String, dynamic>>> _load() async {
    final payload = await CachedApi.instance.get('/objects', queryParameters: {'per_page': 50}, forceRefresh: true) as Map;
    return (payload['data'] as List? ?? []).map((item) => Map<String, dynamic>.from(item as Map)).toList();
  }

  Future<void> _download(Map<String, dynamic> item) async {
    final id = item['id'] as int;
    setState(() => _busy.add(id));
    try {
      final payload = await CachedApi.instance.get('/objects/${item['slug']}', forceRefresh: true) as Map;
      await OfflineStore.instance.saveObject(Map<String, dynamic>.from(payload['data'] as Map));
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Карточка сохранена.')));
    } finally {
      if (mounted) setState(() => _busy.remove(id));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Скачать карточки')),
        body: FutureBuilder<List<Map<String, dynamic>>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
            if (snapshot.hasError) return Center(child: Text(ApiClient.instance.messageFrom(snapshot.error!)));
            return ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: snapshot.data!.length,
              itemBuilder: (context, index) {
                final item = snapshot.data![index];
                return Card(child: ListTile(
                  title: Text('${item['name']}'),
                  subtitle: Text('${item['address'] ?? _asMap(item['location'])['address'] ?? ''}'),
                  trailing: IconButton(onPressed: _busy.contains(item['id']) ? null : () => _download(item), icon: _busy.contains(item['id']) ? const CircularProgressIndicator() : const Icon(Icons.download_for_offline)),
                ));
              },
            );
          },
        ),
      );
}

class OfflineObjectDetail extends StatelessWidget {
  const OfflineObjectDetail({super.key, required this.item});
  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    final location = _asMap(item['location']);
    final cover = _asMap(item['cover']);
    final media = (item['media'] as List? ?? []).map((value) => _asMap(value)).toList();
    return Scaffold(
      appBar: AppBar(title: const Text('Офлайн-карточка')),
      body: ListView(padding: const EdgeInsets.only(bottom: 32), children: [
        if (cover['url'] != null) Image.network('${cover['url']}', height: 230, fit: BoxFit.cover),
        Padding(padding: const EdgeInsets.all(20), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('${item['name'] ?? ''}', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700, color: AppTheme.green)),
          const SizedBox(height: 10),
          Text('${location['address'] ?? ''}'),
          if ('${item['description'] ?? ''}'.trim().isNotEmpty) _Section(title: 'Описание', body: '${item['description']}'),
          if ('${item['history'] ?? ''}'.trim().isNotEmpty) _Section(title: 'История', body: '${item['history']}'),
          if ('${item['schedule'] ?? ''}'.trim().isNotEmpty) _Section(title: 'Расписание', body: '${item['schedule']}'),
          if (media.isNotEmpty) ...[
            const SizedBox(height: 20),
            const Text('Медиаматериалы', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 20)),
            ...media.map((value) => ListTile(leading: Icon(value['type'] == 'video' ? Icons.play_circle_outline : Icons.image_outlined), title: Text('${value['title'] ?? value['type']}'))),
          ],
        ])),
      ]),
    );
  }
}

class ProfileEditorScreen extends StatefulWidget {
  const ProfileEditorScreen({super.key, required this.session});
  final SessionController session;

  @override
  State<ProfileEditorScreen> createState() => _ProfileEditorScreenState();
}

class _ProfileEditorScreenState extends State<ProfileEditorScreen> {
  late final TextEditingController _name;
  late final TextEditingController _email;
  late final TextEditingController _phone;
  DateTime? _birthDate;
  XFile? _avatar;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final user = widget.session.user ?? const {};
    _name = TextEditingController(text: '${user['name'] ?? ''}');
    _email = TextEditingController(text: '${user['email'] ?? ''}');
    _phone = TextEditingController(text: '${user['phone'] ?? ''}');
    _birthDate = DateTime.tryParse('${user['birth_date'] ?? ''}');
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _pickAvatar() async {
    final file = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 85, maxWidth: 1600);
    if (file != null) setState(() => _avatar = file);
  }

  Future<void> _save() async {
    setState(() => _busy = true);
    try {
      final preferences = _asMap(widget.session.user?['preferences']);
      final form = FormData.fromMap({
        'name': _name.text.trim(),
        'email': _email.text.trim(),
        'phone': _phone.text.trim().isEmpty ? null : _phone.text.trim(),
        'birth_date': _birthDate == null ? null : DateFormat('yyyy-MM-dd').format(_birthDate!),
        'notifications': preferences['notifications'] == false ? 0 : 1,
        'privacy': preferences['privacy'] ?? 'private',
        'theme': preferences['theme'] ?? 'system',
        'font_size': preferences['font_size'] ?? 'normal',
        'interests': preferences['interests'] ?? <String>[],
        if (_avatar != null) 'avatar': await MultipartFile.fromFile(_avatar!.path, filename: _avatar!.name),
      });
      await ApiClient.instance.dio.post('/mobile/profile', data: form);
      await widget.session.refreshProfile();
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Профиль обновлён.')));
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.instance.messageFrom(error)), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Редактирование профиля')),
        body: ListView(padding: const EdgeInsets.all(18), children: [
          Center(child: Stack(children: [
            CircleAvatar(
              radius: 58,
              backgroundColor: AppTheme.cream,
              backgroundImage: _avatar != null
                  ? FileImage(File(_avatar!.path))
                  : (widget.session.user?['avatar_url'] != null ? NetworkImage('${widget.session.user!['avatar_url']}') : null) as ImageProvider?,
              child: _avatar == null && widget.session.user?['avatar_url'] == null ? const Icon(Icons.person, size: 50) : null,
            ),
            Positioned(right: 0, bottom: 0, child: IconButton.filled(onPressed: _pickAvatar, icon: const Icon(Icons.camera_alt))),
          ])),
          const SizedBox(height: 24),
          TextField(controller: _name, decoration: const InputDecoration(labelText: 'Имя')),
          const SizedBox(height: 12),
          TextField(controller: _email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email')),
          const SizedBox(height: 12),
          TextField(controller: _phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Телефон')),
          const SizedBox(height: 12),
          ListTile(
            tileColor: AppTheme.cream,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            leading: const Icon(Icons.cake_outlined),
            title: Text(_birthDate == null ? 'Дата рождения' : DateFormat('dd.MM.yyyy').format(_birthDate!)),
            onTap: () async {
              final value = await showDatePicker(context: context, firstDate: DateTime(1900), lastDate: DateTime.now(), initialDate: _birthDate ?? DateTime(1990));
              if (value != null) setState(() => _birthDate = value);
            },
          ),
          const SizedBox(height: 22),
          FilledButton(onPressed: _busy ? null : _save, child: Text(_busy ? 'Сохранение…' : 'Сохранить профиль')),
        ]),
      );
}

class MediaManagerScreen extends StatefulWidget {
  const MediaManagerScreen({super.key});

  @override
  State<MediaManagerScreen> createState() => _MediaManagerScreenState();
}

class _MediaManagerScreenState extends State<MediaManagerScreen> {
  late Future<List<Map<String, dynamic>>> _future = _load();

  Future<List<Map<String, dynamic>>> _load() async {
    final response = await ApiClient.instance.dio.get('/mobile/media');
    return (response.data['data'] as List? ?? []).map((item) => Map<String, dynamic>.from(item as Map)).toList();
  }

  Future<void> _upload(bool video) async {
    final picker = ImagePicker();
    final XFile? file = video
        ? await picker.pickVideo(source: ImageSource.gallery)
        : await picker.pickImage(source: ImageSource.gallery, imageQuality: 90);
    if (file == null || !mounted) return;

    final title = TextEditingController();
    final description = TextEditingController();
    final accepted = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(video ? 'Загрузить видео' : 'Загрузить фотографию'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: title, decoration: const InputDecoration(labelText: 'Название')),
          const SizedBox(height: 10),
          TextField(controller: description, maxLines: 4, decoration: const InputDecoration(labelText: 'Описание')),
        ]),
        actions: [TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Отмена')), FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Загрузить'))],
      ),
    );
    if (accepted != true) return;

    Position? position;
    try {
      if (await _locationPermission()) position = await Geolocator.getCurrentPosition();
    } catch (_) {}

    try {
      await ApiClient.instance.dio.post('/mobile/media', data: FormData.fromMap({
        'file': await MultipartFile.fromFile(file.path, filename: file.name),
        'title': title.text.trim().isEmpty ? null : title.text.trim(),
        'description': description.text.trim().isEmpty ? null : description.text.trim(),
        'latitude': position?.latitude,
        'longitude': position?.longitude,
      }));
      if (mounted) setState(() => _future = _load());
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.instance.messageFrom(error)), backgroundColor: Colors.red));
    } finally {
      title.dispose();
      description.dispose();
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: const Text('Мои фото и видео'),
          actions: [
            PopupMenuButton<String>(
              onSelected: (value) => _upload(value == 'video'),
              itemBuilder: (_) => const [PopupMenuItem(value: 'image', child: Text('Добавить фото')), PopupMenuItem(value: 'video', child: Text('Добавить видео'))],
              icon: const Icon(Icons.add_a_photo_outlined),
            ),
          ],
        ),
        body: FutureBuilder<List<Map<String, dynamic>>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
            if (snapshot.hasError) return Center(child: Text(ApiClient.instance.messageFrom(snapshot.error!)));
            final items = snapshot.data!;
            if (items.isEmpty) return const Center(child: Text('Вы ещё не загружали фото или видео.'));
            return GridView.builder(
              padding: const EdgeInsets.all(12),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 2, childAspectRatio: .78, crossAxisSpacing: 10, mainAxisSpacing: 10),
              itemCount: items.length,
              itemBuilder: (context, index) {
                final item = items[index];
                return Card(clipBehavior: Clip.antiAlias, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Expanded(
                    child: item['type'] == 'image'
                        ? Image.network('${item['url']}', width: double.infinity, fit: BoxFit.cover)
                        : const ColoredBox(color: AppTheme.cream, child: Center(child: Icon(Icons.play_circle, size: 58, color: AppTheme.gold))),
                  ),
                  Padding(padding: const EdgeInsets.all(10), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('${item['title'] ?? (item['type'] == 'video' ? 'Видео' : 'Фотография')}', maxLines: 1, overflow: TextOverflow.ellipsis),
                    Text('${item['status'] ?? ''}', style: const TextStyle(fontSize: 12, color: Colors.black54)),
                    Align(alignment: Alignment.centerRight, child: IconButton(onPressed: () async {
                      await ApiClient.instance.dio.delete('/mobile/media/${item['id']}');
                      setState(() => _future = _load());
                    }, icon: const Icon(Icons.delete_outline, color: Colors.red))),
                  ])),
                ]));
              },
            );
          },
        ),
      );
}

class RouteBuilderScreen extends StatefulWidget {
  const RouteBuilderScreen({super.key, this.plan});
  final Map<String, dynamic>? plan;

  @override
  State<RouteBuilderScreen> createState() => _RouteBuilderScreenState();
}

class _RouteBuilderScreenState extends State<RouteBuilderScreen> {
  final _name = TextEditingController();
  final _notes = TextEditingController();
  String _transport = 'pedestrian';
  List<Map<String, dynamic>> _available = const [];
  final List<Map<String, dynamic>> _selected = [];
  bool _loading = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final plan = widget.plan;
    if (plan != null) {
      _name.text = '${plan['name'] ?? ''}';
      _notes.text = '${plan['notes'] ?? ''}';
      _transport = '${plan['transport_mode'] ?? 'pedestrian'}';
      _selected.addAll((plan['objects'] as List? ?? []).map((item) => {...Map<String, dynamic>.from(item as Map), 'stay_minutes': item['stay_minutes'] ?? 30}));
    }
    _load();
  }

  @override
  void dispose() {
    _name.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final payload = await CachedApi.instance.get('/objects', queryParameters: {'per_page': 50}) as Map;
    if (mounted) setState(() {
      _available = (payload['data'] as List? ?? []).map((item) => Map<String, dynamic>.from(item as Map)).toList();
      _loading = false;
    });
  }

  Future<void> _selectObjects() async {
    final selectedIds = _selected.map((item) => item['id'] as int).toSet();
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setSheetState) => SizedBox(
          height: MediaQuery.of(context).size.height * .82,
          child: Column(children: [
            const Padding(padding: EdgeInsets.all(12), child: Text('Выберите точки маршрута', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700))),
            Expanded(child: ListView.builder(itemCount: _available.length, itemBuilder: (context, index) {
              final item = _available[index];
              final id = item['id'] as int;
              return CheckboxListTile(
                value: selectedIds.contains(id),
                title: Text('${item['name']}'),
                subtitle: Text('${item['address'] ?? _asMap(item['location'])['address'] ?? ''}'),
                onChanged: (value) => setSheetState(() {
                  if (value == true) {
                    selectedIds.add(id);
                  } else {
                    selectedIds.remove(id);
                  }
                }),
              );
            })),
            SafeArea(child: Padding(padding: const EdgeInsets.all(12), child: FilledButton(onPressed: () {
              setState(() {
                final existing = {for (final item in _selected) item['id']: item};
                _selected
                  ..clear()
                  ..addAll(_available.where((item) => selectedIds.contains(item['id'])).map((item) => {...item, 'stay_minutes': existing[item['id']]?['stay_minutes'] ?? 30}));
              });
              Navigator.pop(context);
            }, child: Text('Добавить ${selectedIds.length} точек')))),
          ]),
        ),
      ),
    );
  }

  Future<void> _save() async {
    if (_name.text.trim().isEmpty || _selected.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Укажите название и выберите хотя бы одну точку.')));
      return;
    }
    setState(() => _saving = true);
    try {
      final data = {
        'name': _name.text.trim(),
        'transport_mode': _transport,
        'notes': _notes.text.trim().isEmpty ? null : _notes.text.trim(),
        'objects': _selected.map((item) => {'id': item['id'], 'stay_minutes': item['stay_minutes'] ?? 30}).toList(),
      };
      if (widget.plan == null) {
        final response = await ApiClient.instance.dio.post('/mobile/route-plans', data: {
          'name': data['name'],
          'transport_mode': data['transport_mode'],
          'notes': data['notes'],
          'object_ids': _selected.map((item) => item['id']).toList(),
        });
        final id = response.data['data']['id'];
        await ApiClient.instance.dio.put('/mobile/route-plans/$id', data: data);
      } else {
        await ApiClient.instance.dio.put('/mobile/route-plans/${widget.plan!['id']}', data: data);
      }
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.instance.messageFrom(error)), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(widget.plan == null ? 'Новый маршрут' : 'Редактирование маршрута')),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : Column(children: [
                Expanded(
                  child: ListView(padding: const EdgeInsets.all(16), children: [
                    TextField(controller: _name, decoration: const InputDecoration(labelText: 'Название маршрута')),
                    const SizedBox(height: 12),
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
                    const SizedBox(height: 12),
                    TextField(controller: _notes, minLines: 3, maxLines: 6, decoration: const InputDecoration(labelText: 'Заметки')),
                    const SizedBox(height: 18),
                    Row(children: [Expanded(child: Text('Точки маршрута: ${_selected.length}', style: Theme.of(context).textTheme.titleMedium)), OutlinedButton.icon(onPressed: _selectObjects, icon: const Icon(Icons.add_location_alt_outlined), label: const Text('Выбрать'))]),
                    const SizedBox(height: 8),
                    if (_selected.isEmpty) const Card(child: Padding(padding: EdgeInsets.all(22), child: Text('Добавьте храмы и святыни.'))),
                    ReorderableListView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: _selected.length,
                      onReorder: (oldIndex, newIndex) => setState(() {
                        if (newIndex > oldIndex) newIndex--;
                        final item = _selected.removeAt(oldIndex);
                        _selected.insert(newIndex, item);
                      }),
                      itemBuilder: (context, index) {
                        final item = _selected[index];
                        return Card(
                          key: ValueKey(item['id']),
                          child: ListTile(
                            leading: CircleAvatar(child: Text('${index + 1}')),
                            title: Text('${item['name']}'),
                            subtitle: Text('${item['stay_minutes'] ?? 30} мин. на посещение'),
                            trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                              IconButton(onPressed: () => setState(() => item['stay_minutes'] = ((item['stay_minutes'] ?? 30) as int).clamp(10, 470) - 5), icon: const Icon(Icons.remove_circle_outline)),
                              IconButton(onPressed: () => setState(() => item['stay_minutes'] = ((item['stay_minutes'] ?? 30) as int).clamp(5, 475) + 5), icon: const Icon(Icons.add_circle_outline)),
                              const Icon(Icons.drag_handle),
                            ]),
                          ),
                        );
                      },
                    ),
                  ]),
                ),
                SafeArea(top: false, child: Padding(padding: const EdgeInsets.all(16), child: FilledButton(onPressed: _saving ? null : _save, child: Text(_saving ? 'Сохранение…' : 'Сохранить маршрут')))),
              ]),
      );
}

class MyTogetherManagerScreen extends StatefulWidget {
  const MyTogetherManagerScreen({super.key});

  @override
  State<MyTogetherManagerScreen> createState() => _MyTogetherManagerScreenState();
}

class _MyTogetherManagerScreenState extends State<MyTogetherManagerScreen> {
  late Future<Map<String, dynamic>> _future = _load();

  Future<Map<String, dynamic>> _load() async {
    final response = await ApiClient.instance.dio.get('/mobile/together/my');
    return Map<String, dynamic>.from(response.data as Map);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Мои совместные поездки')),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
            if (snapshot.hasError) return Center(child: Text(ApiClient.instance.messageFrom(snapshot.error!)));
            final organized = (snapshot.data!['organized'] as List? ?? []).map((item) => Map<String, dynamic>.from(item as Map)).toList();
            final memberships = (snapshot.data!['memberships'] as List? ?? []).map((item) => Map<String, dynamic>.from(item as Map)).toList();
            return RefreshIndicator(
              onRefresh: () async => setState(() => _future = _load()),
              child: ListView(padding: const EdgeInsets.all(16), children: [
                Text('Я организатор', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 10),
                if (organized.isEmpty) const Card(child: Padding(padding: EdgeInsets.all(18), child: Text('Созданных поездок пока нет.'))),
                ...organized.map((item) => Card(child: ListTile(
                  leading: const Icon(Icons.groups, color: AppTheme.green),
                  title: Text('${item['title']}'),
                  subtitle: Text('${formatAdvancedDate(item['starts_at'])} · ${item['status']}'),
                  trailing: const Icon(Icons.manage_accounts_outlined),
                  onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => JointOrganizerScreen(slug: '${item['slug']}'))).then((_) => setState(() => _future = _load())),
                ))),
                const SizedBox(height: 24),
                Text('Я участник', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 10),
                if (memberships.isEmpty) const Card(child: Padding(padding: EdgeInsets.all(18), child: Text('Заявок и групп пока нет.'))),
                ...memberships.map((membership) {
                  final joint = _asMap(membership['joint']);
                  return Card(child: ListTile(
                    leading: const Icon(Icons.group_outlined),
                    title: Text('${joint['title'] ?? ''}'),
                    subtitle: Text('${membership['status']} · ${formatAdvancedDate(joint['starts_at'])}'),
                    onTap: joint['slug'] == null ? null : () => Navigator.push(context, MaterialPageRoute(builder: (_) => TogetherDetailScreen(slug: '${joint['slug']}'))),
                  ));
                }),
              ]),
            );
          },
        ),
      );
}

class JointOrganizerScreen extends StatefulWidget {
  const JointOrganizerScreen({super.key, required this.slug});
  final String slug;

  @override
  State<JointOrganizerScreen> createState() => _JointOrganizerScreenState();
}

class _JointOrganizerScreenState extends State<JointOrganizerScreen> {
  late Future<Map<String, dynamic>> _future = _load();

  Future<Map<String, dynamic>> _load() async {
    final response = await ApiClient.instance.dio.get('/mobile/together/${widget.slug}');
    return Map<String, dynamic>.from(response.data['data'] as Map);
  }

  Future<void> _decision(Map<String, dynamic> member, String status) async {
    await ApiClient.instance.dio.put('/mobile/together/${widget.slug}/members/${member['id']}', data: {'status': status});
    if (mounted) setState(() => _future = _load());
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Управление группой')),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
            if (snapshot.hasError) return Center(child: Text(ApiClient.instance.messageFrom(snapshot.error!)));
            final item = snapshot.data!;
            final members = (item['members'] as List? ?? []).map((value) => Map<String, dynamic>.from(value as Map)).toList();
            return ListView(padding: const EdgeInsets.all(16), children: [
              Text('${item['title']}', style: Theme.of(context).textTheme.headlineSmall?.copyWith(color: AppTheme.green, fontWeight: FontWeight.w700)),
              const SizedBox(height: 8),
              Text('${item['meeting_place']} · ${formatAdvancedDate(item['starts_at'])}'),
              const SizedBox(height: 20),
              FilledButton.icon(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TogetherDetailScreen(slug: widget.slug))), icon: const Icon(Icons.chat_outlined), label: const Text('Открыть чат группы')),
              const SizedBox(height: 24),
              Text('Заявки и участники', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 10),
              if (members.isEmpty) const Card(child: Padding(padding: EdgeInsets.all(18), child: Text('Заявок пока нет.'))),
              ...members.map((member) {
                final user = _asMap(member['user']);
                return Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('${user['name'] ?? 'Паломник'}', style: const TextStyle(fontWeight: FontWeight.w700)),
                  Text('Статус: ${member['status']}'),
                  if (member['status'] == 'pending') Padding(
                    padding: const EdgeInsets.only(top: 10),
                    child: Row(children: [
                      Expanded(child: FilledButton(onPressed: () => _decision(member, 'approved'), child: const Text('Принять'))),
                      const SizedBox(width: 8),
                      Expanded(child: OutlinedButton(onPressed: () => _decision(member, 'rejected'), child: const Text('Отклонить'))),
                    ]),
                  ),
                ])));
              }),
            ]);
          },
        ),
      );
}

Future<bool> _locationPermission() async {
  if (!await Geolocator.isLocationServiceEnabled()) return false;
  var permission = await Geolocator.checkPermission();
  if (permission == LocationPermission.denied) permission = await Geolocator.requestPermission();
  return permission == LocationPermission.always || permission == LocationPermission.whileInUse;
}

Map<String, dynamic> _asMap(dynamic value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return Map<String, dynamic>.from(value);
  return <String, dynamic>{};
}

String formatAdvancedDate(dynamic value) {
  if (value == null) return 'Дата уточняется';
  final date = value is DateTime ? value : DateTime.tryParse('$value')?.toLocal();
  return date == null ? '$value' : DateFormat('dd.MM.yyyy HH:mm', 'ru').format(date);
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.body});
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 22),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700, color: AppTheme.green)),
          const SizedBox(height: 8),
          Text(body, style: const TextStyle(height: 1.55)),
        ]),
      );
}
