import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api_client.dart';
import '../core/session_controller.dart';
import '../theme/app_theme.dart';

class ObjectDetailScreen extends StatefulWidget {
  const ObjectDetailScreen({super.key, required this.slug});

  final String slug;

  @override
  State<ObjectDetailScreen> createState() => _ObjectDetailScreenState();
}

class _ObjectDetailScreenState extends State<ObjectDetailScreen> {
  late Future<Map<String, dynamic>> _future = _load();
  bool _busy = false;

  Future<Map<String, dynamic>> _load() async {
    final response = await ApiClient.instance.dio.get('/objects/${widget.slug}');
    return Map<String, dynamic>.from(response.data['data'] as Map);
  }

  Future<void> _favorite(Map<String, dynamic> item) async {
    setState(() => _busy = true);
    try {
      final response = await ApiClient.instance.dio.post('/mobile/favorites/${item['id']}');
      if (!mounted) return;
      _snack(response.data['is_favorite'] == true ? 'Добавлено в избранное' : 'Удалено из избранного');
    } catch (error) {
      if (mounted) _snack(ApiClient.instance.messageFrom(error), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _visit(Map<String, dynamic> item) async {
    setState(() => _busy = true);
    try {
      await ApiClient.instance.dio.post('/mobile/visits', data: {'pilgrimage_object_id': item['id']});
      if (mounted) _snack('Посещение отправлено на подтверждение.');
    } catch (error) {
      if (mounted) _snack(ApiClient.instance.messageFrom(error), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _review(Map<String, dynamic> item) async {
    final body = TextEditingController();
    var rating = 5;
    final submit = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Отзыв о посещении'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Wrap(
                children: List.generate(
                  5,
                  (index) => IconButton(
                    onPressed: () => setDialogState(() => rating = index + 1),
                    icon: Icon(index < rating ? Icons.star : Icons.star_border),
                    color: AppTheme.gold,
                  ),
                ),
              ),
              TextField(
                controller: body,
                minLines: 3,
                maxLines: 6,
                decoration: const InputDecoration(hintText: 'Расскажите о храме или святыне'),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Отмена')),
            FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('Отправить')),
          ],
        ),
      ),
    );

    if (submit != true) {
      body.dispose();
      return;
    }

    setState(() => _busy = true);
    try {
      await ApiClient.instance.dio.post('/mobile/reviews', data: {
        'pilgrimage_object_id': item['id'],
        'rating': rating,
        'body': body.text.trim(),
      });
      if (mounted) _snack('Отзыв отправлен на модерацию.');
    } catch (error) {
      if (mounted) _snack(ApiClient.instance.messageFrom(error), error: true);
    } finally {
      body.dispose();
      if (mounted) setState(() => _busy = false);
    }
  }

  void _snack(String message, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: error ? Colors.red : AppTheme.green),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Карточка объекта')),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
            if (snapshot.hasError) return FeatureError(error: snapshot.error!, onRetry: () => setState(() => _future = _load()));
            final item = snapshot.data!;
            final location = _map(item['location']);
            final contacts = _map(item['contacts']);
            final amenities = _map(item['amenities']);
            final type = _map(item['type']);
            final cover = _map(item['cover']);
            final media = _list(item['media']);
            final sanctities = _list(item['sanctities']);
            final lat = location['latitude'];
            final lon = location['longitude'];

            return ListView(
              padding: const EdgeInsets.only(bottom: 32),
              children: [
                if (cover['url'] != null)
                  Image.network('${cover['url']}', height: 260, width: double.infinity, fit: BoxFit.cover, errorBuilder: (_, __, ___) => _placeholder())
                else
                  _placeholder(),
                Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (type['name'] != null) Chip(label: Text('${type['name']}')),
                      const SizedBox(height: 10),
                      Text('${item['name'] ?? ''}', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700, color: AppTheme.green)),
                      const SizedBox(height: 10),
                      Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        const Icon(Icons.location_on_outlined, color: AppTheme.gold),
                        const SizedBox(width: 8),
                        Expanded(child: Text('${location['address'] ?? 'Адрес уточняется'}')),
                      ]),
                      const SizedBox(height: 18),
                      Wrap(spacing: 8, runSpacing: 8, children: [
                        FilledButton.icon(
                          onPressed: lat != null && lon != null
                              ? () => launchUrl(Uri.parse('https://yandex.ru/maps/?rtext=~$lat,$lon&rtt=auto'), mode: LaunchMode.externalApplication)
                              : null,
                          icon: const Icon(Icons.directions),
                          label: const Text('Маршрут'),
                        ),
                        OutlinedButton.icon(onPressed: _busy ? null : () => _favorite(item), icon: const Icon(Icons.favorite_border), label: const Text('Избранное')),
                        OutlinedButton.icon(onPressed: _busy ? null : () => _visit(item), icon: const Icon(Icons.where_to_vote_outlined), label: const Text('Я посетил')),
                        OutlinedButton.icon(onPressed: _busy ? null : () => _review(item), icon: const Icon(Icons.rate_review_outlined), label: const Text('Отзыв')),
                      ]),
                      if (_text(item['short_description']) != null) ...[
                        const SizedBox(height: 24),
                        Text('${item['short_description']}', style: Theme.of(context).textTheme.titleMedium),
                      ],
                      if (_text(item['description']) != null) FeatureSection(title: 'Описание', text: '${item['description']}'),
                      if (_text(item['history']) != null) FeatureSection(title: 'История', text: '${item['history']}'),
                      if (sanctities.isNotEmpty) ...[
                        const FeatureHeading('Святыни'),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: sanctities.map((value) {
                            final data = _map(value);
                            return Chip(label: Text('${data['name'] ?? ''}'));
                          }).toList(),
                        ),
                      ],
                      if (_text(item['schedule']) != null) FeatureSection(title: 'Расписание богослужений', text: '${item['schedule']}', icon: Icons.schedule),
                      if (_text(amenities['parking']) != null) FeatureSection(title: 'Парковка', text: '${amenities['parking']}', icon: Icons.local_parking),
                      if (_text(amenities['accessibility']) != null) FeatureSection(title: 'Доступность', text: '${amenities['accessibility']}', icon: Icons.accessible),
                      if (contacts.values.any((value) => _text(value) != null)) ...[
                        const FeatureHeading('Контакты'),
                        Card(
                          child: Column(children: [
                            if (_text(contacts['phone']) != null) ListTile(leading: const Icon(Icons.phone), title: Text('${contacts['phone']}'), onTap: () => launchUrl(Uri.parse('tel:${contacts['phone']}'))),
                            if (_text(contacts['email']) != null) ListTile(leading: const Icon(Icons.email), title: Text('${contacts['email']}'), onTap: () => launchUrl(Uri.parse('mailto:${contacts['email']}'))),
                            if (_text(contacts['website']) != null) ListTile(leading: const Icon(Icons.language), title: Text('${contacts['website']}'), onTap: () => launchUrl(Uri.parse('${contacts['website']}'), mode: LaunchMode.externalApplication)),
                          ]),
                        ),
                      ],
                      if (media.where((value) => _map(value)['type'] == 'image' && _map(value)['url'] != null).isNotEmpty) ...[
                        const FeatureHeading('Фотографии'),
                        SizedBox(
                          height: 180,
                          child: ListView.separated(
                            scrollDirection: Axis.horizontal,
                            itemCount: media.where((value) => _map(value)['type'] == 'image' && _map(value)['url'] != null).length,
                            separatorBuilder: (_, __) => const SizedBox(width: 10),
                            itemBuilder: (context, index) {
                              final image = _map(media.where((value) => _map(value)['type'] == 'image' && _map(value)['url'] != null).elementAt(index));
                              return ClipRRect(borderRadius: BorderRadius.circular(18), child: Image.network('${image['url']}', width: 250, fit: BoxFit.cover));
                            },
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

  Widget _placeholder() => const SizedBox(
        height: 240,
        child: ColoredBox(color: AppTheme.cream, child: Center(child: Icon(Icons.church, size: 70, color: AppTheme.gold))),
      );
}

class RouteDetailScreen extends StatefulWidget {
  const RouteDetailScreen({super.key, required this.slug});
  final String slug;

  @override
  State<RouteDetailScreen> createState() => _RouteDetailScreenState();
}

class _RouteDetailScreenState extends State<RouteDetailScreen> {
  late Future<Map<String, dynamic>> _future = _load();

  Future<Map<String, dynamic>> _load() async {
    final response = await ApiClient.instance.dio.get('/mobile/routes/${widget.slug}');
    return Map<String, dynamic>.from(response.data['data'] as Map);
  }

  Future<void> _book(Map<String, dynamic> trip) async {
    final name = TextEditingController();
    final email = TextEditingController();
    final phone = TextEditingController();
    final notes = TextEditingController();
    var count = 1;
    final submit = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Бронирование поездки'),
          content: SingleChildScrollView(
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              TextField(controller: name, decoration: const InputDecoration(labelText: 'ФИО')),
              const SizedBox(height: 10),
              TextField(controller: email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email')),
              const SizedBox(height: 10),
              TextField(controller: phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Телефон')),
              const SizedBox(height: 10),
              DropdownButtonFormField<int>(
                value: count,
                decoration: const InputDecoration(labelText: 'Количество участников'),
                items: List.generate(10, (index) => DropdownMenuItem(value: index + 1, child: Text('${index + 1}'))),
                onChanged: (value) => setDialogState(() => count = value ?? 1),
              ),
              const SizedBox(height: 10),
              TextField(controller: notes, maxLines: 3, decoration: const InputDecoration(labelText: 'Комментарий')),
            ]),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Отмена')),
            FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('Забронировать')),
          ],
        ),
      ),
    );

    if (submit != true) {
      name.dispose();
      email.dispose();
      phone.dispose();
      notes.dispose();
      return;
    }

    try {
      final response = await ApiClient.instance.dio.post('/mobile/trips/${trip['id']}/bookings', data: {
        'participants_count': count,
        'contact_name': name.text.trim(),
        'email': email.text.trim(),
        'phone': phone.text.trim(),
        'notes': notes.text.trim().isEmpty ? null : notes.text.trim(),
      });
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('${response.data['message'] ?? 'Бронирование создано.'} Код: ${response.data['ticket_code'] ?? ''}')));
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.instance.messageFrom(error)), backgroundColor: Colors.red));
    } finally {
      name.dispose();
      email.dispose();
      phone.dispose();
      notes.dispose();
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Паломнический маршрут')),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
            if (snapshot.hasError) return FeatureError(error: snapshot.error!, onRetry: () => setState(() => _future = _load()));
            final item = snapshot.data!;
            final objects = _list(item['objects']);
            final trips = _list(item['trips']);
            return ListView(
              padding: const EdgeInsets.all(18),
              children: [
                if (item['cover_url'] != null) ClipRRect(borderRadius: BorderRadius.circular(24), child: Image.network('${item['cover_url']}', height: 230, fit: BoxFit.cover)),
                const SizedBox(height: 18),
                Text('${item['title'] ?? ''}', style: Theme.of(context).textTheme.headlineSmall?.copyWith(color: AppTheme.green, fontWeight: FontWeight.w700)),
                const SizedBox(height: 10),
                Wrap(spacing: 8, runSpacing: 8, children: [
                  Chip(label: Text('${item['category'] ?? 'Маршрут'}')),
                  Chip(label: Text('${item['difficulty'] ?? 'Сложность не указана'}')),
                  Chip(label: Text('${item['duration_days'] ?? 1} дн.')),
                ]),
                if (_text(item['description']) != null) FeatureSection(title: 'Описание', text: '${item['description']}'),
                if (_text(item['program']) != null) FeatureSection(title: 'Программа', text: '${item['program']}'),
                if (objects.isNotEmpty) ...[
                  const FeatureHeading('Точки маршрута'),
                  ...objects.asMap().entries.map((entry) {
                    final object = _map(entry.value);
                    return Card(
                      child: ListTile(
                        leading: CircleAvatar(child: Text('${entry.key + 1}')),
                        title: Text('${object['name'] ?? ''}'),
                        subtitle: Text('${object['address'] ?? ''}'),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ObjectDetailScreen(slug: '${object['slug']}'))),
                      ),
                    );
                  }),
                ],
                if (trips.isNotEmpty) ...[
                  const FeatureHeading('Даты поездок'),
                  ...trips.map((value) {
                    final trip = _map(value);
                    final isOpen = trip['status'] == 'open';
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(formatFeatureDate(trip['starts_at']), style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                          const SizedBox(height: 6),
                          Text('${trip['meeting_point'] ?? 'Место встречи уточняется'}'),
                          const SizedBox(height: 8),
                          Text('Цена: ${trip['price'] ?? item['base_price'] ?? 0} ₽ · занято ${trip['booked_count'] ?? 0}/${trip['capacity'] ?? '∞'}'),
                          const SizedBox(height: 12),
                          FilledButton.icon(onPressed: isOpen ? () => _book(trip) : null, icon: const Icon(Icons.confirmation_number_outlined), label: Text(isOpen ? 'Забронировать' : 'Запись закрыта')),
                        ]),
                      ),
                    );
                  }),
                ],
              ],
            );
          },
        ),
      );
}

class EventDetailScreen extends StatefulWidget {
  const EventDetailScreen({super.key, required this.slug});
  final String slug;

  @override
  State<EventDetailScreen> createState() => _EventDetailScreenState();
}

class _EventDetailScreenState extends State<EventDetailScreen> {
  late Future<Map<String, dynamic>> _future = _load();

  Future<Map<String, dynamic>> _load() async {
    final response = await ApiClient.instance.dio.get('/mobile/calendar/${widget.slug}');
    return Map<String, dynamic>.from(response.data['data'] as Map);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Событие')),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
            if (snapshot.hasError) return FeatureError(error: snapshot.error!, onRetry: () => setState(() => _future = _load()));
            final item = snapshot.data!;
            return ListView(padding: const EdgeInsets.all(20), children: [
              Chip(label: Text('${item['type_label'] ?? 'Событие'}')),
              const SizedBox(height: 12),
              Text('${item['title'] ?? ''}', style: Theme.of(context).textTheme.headlineSmall?.copyWith(color: AppTheme.green, fontWeight: FontWeight.w700)),
              const SizedBox(height: 16),
              FeatureInfo(icon: Icons.calendar_month, text: formatFeatureDate(item['starts_at'])),
              if (_text(item['location'] ?? item['address']) != null) FeatureInfo(icon: Icons.location_on_outlined, text: '${item['location'] ?? item['address']}'),
              if (_text(item['description']) != null) FeatureSection(title: 'Описание', text: '${item['description']}'),
              const SizedBox(height: 20),
              Wrap(spacing: 10, runSpacing: 10, children: [
                if (item['ics_url'] != null) FilledButton.icon(onPressed: () => launchUrl(Uri.parse('${item['ics_url']}'), mode: LaunchMode.externalApplication), icon: const Icon(Icons.event_available), label: const Text('В календарь')),
                if (item['registration_url'] != null) OutlinedButton.icon(onPressed: () => launchUrl(Uri.parse('${item['registration_url']}'), mode: LaunchMode.externalApplication), icon: const Icon(Icons.app_registration), label: const Text('Регистрация')),
              ]),
            ]);
          },
        ),
      );
}

class PostDetailScreen extends StatefulWidget {
  const PostDetailScreen({super.key, required this.slug});
  final String slug;

  @override
  State<PostDetailScreen> createState() => _PostDetailScreenState();
}

class _PostDetailScreenState extends State<PostDetailScreen> {
  late Future<Map<String, dynamic>> _future = _load();

  Future<Map<String, dynamic>> _load() async {
    final response = await ApiClient.instance.dio.get('/mobile/community/${widget.slug}');
    return Map<String, dynamic>.from(response.data['data'] as Map);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Путевая заметка')),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
            if (snapshot.hasError) return FeatureError(error: snapshot.error!, onRetry: () => setState(() => _future = _load()));
            final item = snapshot.data!;
            final author = _map(item['author']);
            final media = _list(item['media']);
            return ListView(padding: const EdgeInsets.all(20), children: [
              Text('${item['title'] ?? ''}', style: Theme.of(context).textTheme.headlineSmall?.copyWith(color: AppTheme.green, fontWeight: FontWeight.w700)),
              const SizedBox(height: 10),
              Text('${author['name'] ?? 'Паломник'} · ${formatFeatureDate(item['published_at'])}', style: const TextStyle(color: Colors.black54)),
              if (media.isNotEmpty) ...[
                const SizedBox(height: 18),
                ...media.where((value) => _map(value)['type'] == 'image').map((value) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: ClipRRect(borderRadius: BorderRadius.circular(20), child: Image.network('${_map(value)['url']}', fit: BoxFit.cover)),
                    )),
              ],
              const SizedBox(height: 18),
              Text('${item['body'] ?? item['excerpt'] ?? ''}', style: const TextStyle(height: 1.65, fontSize: 16)),
            ]);
          },
        ),
      );
}

class TogetherDetailScreen extends StatefulWidget {
  const TogetherDetailScreen({super.key, required this.slug});
  final String slug;

  @override
  State<TogetherDetailScreen> createState() => _TogetherDetailScreenState();
}

class _TogetherDetailScreenState extends State<TogetherDetailScreen> {
  late Future<Map<String, dynamic>> _future = _load();
  final _message = TextEditingController();
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 10), (_) {
      if (mounted) setState(() => _future = _load());
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _message.dispose();
    super.dispose();
  }

  Future<Map<String, dynamic>> _load() async {
    final response = await ApiClient.instance.dio.get('/mobile/together/${widget.slug}');
    return Map<String, dynamic>.from(response.data['data'] as Map);
  }

  Future<void> _join() async {
    try {
      final response = await ApiClient.instance.dio.post('/mobile/together/${widget.slug}/join');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Статус заявки: ${response.data['status']}')));
        setState(() => _future = _load());
      }
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.instance.messageFrom(error)), backgroundColor: Colors.red));
    }
  }

  Future<void> _leave() async {
    try {
      await ApiClient.instance.dio.delete('/mobile/together/${widget.slug}/leave');
      if (mounted) setState(() => _future = _load());
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.instance.messageFrom(error)), backgroundColor: Colors.red));
    }
  }

  Future<void> _send() async {
    final body = _message.text.trim();
    if (body.length < 2) return;
    try {
      await ApiClient.instance.dio.post('/mobile/together/${widget.slug}/messages', data: {'body': body});
      _message.clear();
      if (mounted) setState(() => _future = _load());
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.instance.messageFrom(error)), backgroundColor: Colors.red));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Паломничество вместе')),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done && !snapshot.hasData) return const Center(child: CircularProgressIndicator());
            if (snapshot.hasError && !snapshot.hasData) return FeatureError(error: snapshot.error!, onRetry: () => setState(() => _future = _load()));
            final item = snapshot.data!;
            final organizer = _map(item['organizer']);
            final messages = _list(item['messages']);
            final membership = _text(item['membership_status']);
            final canDiscuss = item['can_discuss'] == true;
            return Column(children: [
              Expanded(
                child: ListView(padding: const EdgeInsets.all(18), children: [
                  Text('${item['title'] ?? ''}', style: Theme.of(context).textTheme.headlineSmall?.copyWith(color: AppTheme.green, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 10),
                  Text('Организатор: ${organizer['name'] ?? ''}${organizer['is_verified_organizer'] == true ? ' ✓' : ''}'),
                  const SizedBox(height: 10),
                  FeatureInfo(icon: Icons.calendar_month, text: formatFeatureDate(item['starts_at'])),
                  FeatureInfo(icon: Icons.location_on_outlined, text: '${item['meeting_place'] ?? ''}'),
                  FeatureInfo(icon: Icons.groups, text: '${item['participants_count'] ?? 1}/${item['max_participants'] ?? '∞'} участников'),
                  FeatureSection(title: 'План поездки', text: '${item['description'] ?? ''}'),
                  if (item['can_manage'] != true) ...[
                    const SizedBox(height: 12),
                    if (membership == null || membership == 'left' || membership == 'rejected')
                      FilledButton.icon(onPressed: _join, icon: const Icon(Icons.group_add), label: const Text('Присоединиться'))
                    else
                      OutlinedButton.icon(onPressed: _leave, icon: const Icon(Icons.exit_to_app), label: Text(membership == 'pending' ? 'Отозвать заявку' : 'Выйти из группы')),
                  ],
                  if (canDiscuss) ...[
                    const FeatureHeading('Обсуждение'),
                    if (messages.isEmpty) const Text('Сообщений пока нет.'),
                    ...messages.map((value) {
                      final message = _map(value);
                      final user = _map(message['user']);
                      return Card(
                        color: message['is_system'] == true ? AppTheme.cream : null,
                        child: Padding(
                          padding: const EdgeInsets.all(14),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            if (message['is_system'] != true) Text('${user['name'] ?? 'Паломник'}', style: const TextStyle(fontWeight: FontWeight.w700)),
                            const SizedBox(height: 4),
                            Text('${message['body'] ?? ''}'),
                            const SizedBox(height: 5),
                            Text(formatFeatureDate(message['created_at']), style: const TextStyle(fontSize: 11, color: Colors.black45)),
                          ]),
                        ),
                      );
                    }),
                  ],
                ]),
              ),
              if (canDiscuss)
                SafeArea(
                  top: false,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
                    child: Row(children: [
                      Expanded(child: TextField(controller: _message, minLines: 1, maxLines: 4, decoration: const InputDecoration(hintText: 'Сообщение участникам'))),
                      const SizedBox(width: 8),
                      IconButton.filled(onPressed: _send, icon: const Icon(Icons.send)),
                    ]),
                  ),
                ),
            ]);
          },
        ),
      );
}

class CreateTogetherScreen extends StatefulWidget {
  const CreateTogetherScreen({super.key});

  @override
  State<CreateTogetherScreen> createState() => _CreateTogetherScreenState();
}

class _CreateTogetherScreenState extends State<CreateTogetherScreen> {
  final _title = TextEditingController();
  final _description = TextEditingController();
  final _meeting = TextEditingController();
  DateTime? _startsAt;
  String _transport = 'public';
  String _joinMode = 'approval';
  bool _busy = false;

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    _meeting.dispose();
    super.dispose();
  }

  Future<void> _selectDate() async {
    final day = await showDatePicker(context: context, firstDate: DateTime.now(), lastDate: DateTime.now().add(const Duration(days: 730)), initialDate: DateTime.now().add(const Duration(days: 7)));
    if (day == null || !mounted) return;
    final time = await showTimePicker(context: context, initialTime: const TimeOfDay(hour: 9, minute: 0));
    if (time == null) return;
    setState(() => _startsAt = DateTime(day.year, day.month, day.day, time.hour, time.minute));
  }

  Future<void> _save() async {
    if (_startsAt == null || _title.text.trim().isEmpty || _description.text.trim().length < 20 || _meeting.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Заполните название, описание, дату и место встречи.')));
      return;
    }
    setState(() => _busy = true);
    try {
      await ApiClient.instance.dio.post('/mobile/together', data: {
        'title': _title.text.trim(),
        'description': _description.text.trim(),
        'starts_at': _startsAt!.toIso8601String(),
        'meeting_place': _meeting.text.trim(),
        'transport_mode': _transport,
        'join_mode': _joinMode,
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
        appBar: AppBar(title: const Text('Предложить поездку')),
        body: ListView(padding: const EdgeInsets.all(18), children: [
          TextField(controller: _title, decoration: const InputDecoration(labelText: 'Название')),
          const SizedBox(height: 12),
          TextField(controller: _description, minLines: 5, maxLines: 10, decoration: const InputDecoration(labelText: 'Описание и план')),
          const SizedBox(height: 12),
          TextField(controller: _meeting, decoration: const InputDecoration(labelText: 'Место встречи')),
          const SizedBox(height: 12),
          ListTile(
            tileColor: AppTheme.cream,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            leading: const Icon(Icons.calendar_month),
            title: Text(_startsAt == null ? 'Выберите дату и время' : formatFeatureDate(_startsAt)),
            onTap: _selectDate,
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: _transport,
            decoration: const InputDecoration(labelText: 'Транспорт'),
            items: const [
              DropdownMenuItem(value: 'walk', child: Text('Пешком')),
              DropdownMenuItem(value: 'public', child: Text('Общественный транспорт')),
              DropdownMenuItem(value: 'car', child: Text('Автомобиль')),
              DropdownMenuItem(value: 'bus', child: Text('Заказной автобус')),
              DropdownMenuItem(value: 'mixed', child: Text('Смешанный вариант')),
            ],
            onChanged: (value) => setState(() => _transport = value ?? 'public'),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: _joinMode,
            decoration: const InputDecoration(labelText: 'Вступление'),
            items: const [
              DropdownMenuItem(value: 'approval', child: Text('По заявке организатору')),
              DropdownMenuItem(value: 'auto', child: Text('Свободное присоединение')),
            ],
            onChanged: (value) => setState(() => _joinMode = value ?? 'approval'),
          ),
          const SizedBox(height: 20),
          FilledButton(onPressed: _busy ? null : _save, child: Text(_busy ? 'Сохранение...' : 'Отправить на модерацию')),
        ]),
      );
}

class ProfileSettingsScreen extends StatefulWidget {
  const ProfileSettingsScreen({super.key, required this.session});
  final SessionController session;

  @override
  State<ProfileSettingsScreen> createState() => _ProfileSettingsScreenState();
}

class _ProfileSettingsScreenState extends State<ProfileSettingsScreen> {
  late final TextEditingController _name;
  late final TextEditingController _email;
  late final TextEditingController _phone;
  String _privacy = 'private';
  String _theme = 'system';
  String _fontSize = 'normal';
  bool _notifications = true;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final user = widget.session.user ?? const {};
    final preferences = _map(user['preferences']);
    _name = TextEditingController(text: '${user['name'] ?? ''}');
    _email = TextEditingController(text: '${user['email'] ?? ''}');
    _phone = TextEditingController(text: '${user['phone'] ?? ''}');
    _privacy = '${preferences['privacy'] ?? 'private'}';
    _theme = '${preferences['theme'] ?? 'system'}';
    _fontSize = '${preferences['font_size'] ?? 'normal'}';
    _notifications = preferences['notifications'] != false;
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _busy = true);
    try {
      await ApiClient.instance.dio.post('/mobile/profile', data: FormData.fromMap({
        'name': _name.text.trim(),
        'email': _email.text.trim(),
        'phone': _phone.text.trim().isEmpty ? null : _phone.text.trim(),
        'notifications': _notifications ? 1 : 0,
        'privacy': _privacy,
        'theme': _theme,
        'font_size': _fontSize,
        'interests': <String>[],
      }));
      await widget.session.refreshProfile();
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Настройки сохранены.')));
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.instance.messageFrom(error)), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Настройки профиля')),
        body: ListView(padding: const EdgeInsets.all(18), children: [
          TextField(controller: _name, decoration: const InputDecoration(labelText: 'Имя')),
          const SizedBox(height: 12),
          TextField(controller: _email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email')),
          const SizedBox(height: 12),
          TextField(controller: _phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Телефон')),
          const FeatureHeading('Приватность и интерфейс'),
          SwitchListTile(value: _notifications, onChanged: (value) => setState(() => _notifications = value), title: const Text('Уведомления')),
          DropdownButtonFormField<String>(
            value: _privacy,
            decoration: const InputDecoration(labelText: 'Видимость профиля'),
            items: const [
              DropdownMenuItem(value: 'private', child: Text('Только я')),
              DropdownMenuItem(value: 'registered', child: Text('Пользователи сервиса')),
              DropdownMenuItem(value: 'public', child: Text('Публичный профиль')),
            ],
            onChanged: (value) => setState(() => _privacy = value ?? 'private'),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: _theme,
            decoration: const InputDecoration(labelText: 'Тема'),
            items: const [
              DropdownMenuItem(value: 'system', child: Text('Как в системе')),
              DropdownMenuItem(value: 'light', child: Text('Светлая')),
              DropdownMenuItem(value: 'dark', child: Text('Тёмная')),
            ],
            onChanged: (value) => setState(() => _theme = value ?? 'system'),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: _fontSize,
            decoration: const InputDecoration(labelText: 'Размер текста'),
            items: const [
              DropdownMenuItem(value: 'normal', child: Text('Обычный')),
              DropdownMenuItem(value: 'large', child: Text('Крупный')),
              DropdownMenuItem(value: 'extra_large', child: Text('Очень крупный')),
            ],
            onChanged: (value) => setState(() => _fontSize = value ?? 'normal'),
          ),
          const SizedBox(height: 22),
          FilledButton(onPressed: _busy ? null : _save, child: Text(_busy ? 'Сохранение...' : 'Сохранить')),
        ]),
      );
}

class FeatureHeading extends StatelessWidget {
  const FeatureHeading(this.title, {super.key});
  final String title;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 26, bottom: 12),
        child: Text(title, style: Theme.of(context).textTheme.titleLarge?.copyWith(color: AppTheme.green, fontWeight: FontWeight.w700)),
      );
}

class FeatureSection extends StatelessWidget {
  const FeatureSection({super.key, required this.title, required this.text, this.icon});
  final String title;
  final String text;
  final IconData? icon;

  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        FeatureHeading(title),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              if (icon != null) ...[Icon(icon, color: AppTheme.gold), const SizedBox(width: 12)],
              Expanded(child: Text(text, style: const TextStyle(height: 1.55))),
            ]),
          ),
        ),
      ]);
}

class FeatureInfo extends StatelessWidget {
  const FeatureInfo({super.key, required this.icon, required this.text});
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(icon, color: AppTheme.gold),
          const SizedBox(width: 10),
          Expanded(child: Text(text)),
        ]),
      );
}

class FeatureError extends StatelessWidget {
  const FeatureError({super.key, required this.error, required this.onRetry});
  final Object error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off, size: 50),
            const SizedBox(height: 12),
            Text(ApiClient.instance.messageFrom(error), textAlign: TextAlign.center),
            const SizedBox(height: 12),
            OutlinedButton(onPressed: onRetry, child: const Text('Повторить')),
          ]),
        ),
      );
}

Map<String, dynamic> _map(dynamic value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return Map<String, dynamic>.from(value);
  return <String, dynamic>{};
}

List<dynamic> _list(dynamic value) => value is List ? value : const [];

String? _text(dynamic value) {
  final text = value?.toString().trim();
  return text == null || text.isEmpty ? null : text;
}

String formatFeatureDate(dynamic value) {
  if (value == null) return 'Дата уточняется';
  final date = value is DateTime ? value : DateTime.tryParse('$value')?.toLocal();
  return date == null ? '$value' : DateFormat('dd.MM.yyyy HH:mm', 'ru').format(date);
}
