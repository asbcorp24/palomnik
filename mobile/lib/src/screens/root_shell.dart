import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../core/api_client.dart';
import '../core/session_controller.dart';
import '../theme/app_theme.dart';

class RootShell extends StatefulWidget {
  const RootShell({super.key, required this.session});

  final SessionController session;

  @override
  State<RootShell> createState() => _RootShellState();
}

class _RootShellState extends State<RootShell> {
  int _index = 0;

  late final List<Widget> _pages = [
    HomeTab(session: widget.session),
    const CatalogTab(),
    const MapTab(),
    const CalendarTab(),
    ProfileTab(session: widget.session),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(index: _index, children: _pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (value) => setState(() => _index = value),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'Главная'),
          NavigationDestination(icon: Icon(Icons.church_outlined), selectedIcon: Icon(Icons.church), label: 'Святыни'),
          NavigationDestination(icon: Icon(Icons.map_outlined), selectedIcon: Icon(Icons.map), label: 'Карта'),
          NavigationDestination(icon: Icon(Icons.calendar_month_outlined), selectedIcon: Icon(Icons.calendar_month), label: 'Календарь'),
          NavigationDestination(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person), label: 'Профиль'),
        ],
      ),
    );
  }
}

class HomeTab extends StatefulWidget {
  const HomeTab({super.key, required this.session});
  final SessionController session;

  @override
  State<HomeTab> createState() => _HomeTabState();
}

class _HomeTabState extends State<HomeTab> {
  Future<Map<String, dynamic>>? _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final response = await ApiClient.instance.dio.get('/mobile/home');
    return Map<String, dynamic>.from(response.data as Map);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Московский паломник'),
        actions: [
          IconButton(
            tooltip: 'Сообщество',
            onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const CommunityScreen())),
            icon: const Icon(Icons.groups_outlined),
          ),
          IconButton(
            tooltip: 'Уведомления',
            onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const NotificationsScreen())),
            icon: const Icon(Icons.notifications_none),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async => setState(() => _future = _load()),
        child: FutureBuilder<Map<String, dynamic>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const ListView(children: [SizedBox(height: 300), Center(child: CircularProgressIndicator())]);
            }
            if (snapshot.hasError) return ErrorPane(error: snapshot.error!, onRetry: () => setState(() => _future = _load()));

            final data = snapshot.data!;
            final objects = List<Map<String, dynamic>>.from((data['objects'] as List? ?? []).map((item) => Map<String, dynamic>.from(item as Map)));
            final routes = List<Map<String, dynamic>>.from((data['routes'] as List? ?? []).map((item) => Map<String, dynamic>.from(item as Map)));
            final events = List<Map<String, dynamic>>.from((data['events'] as List? ?? []).map((item) => Map<String, dynamic>.from(item as Map)));

            return ListView(
              padding: const EdgeInsets.fromLTRB(18, 12, 18, 32),
              children: [
                Container(
                  padding: const EdgeInsets.all(22),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(26),
                    gradient: const LinearGradient(colors: [AppTheme.green, Color(0xFF18322A)]),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Святые места становятся ближе', style: TextStyle(color: Colors.white, fontSize: 25, fontWeight: FontWeight.w700)),
                      const SizedBox(height: 10),
                      const Text('Храмы, святыни, маршруты, события и совместные паломничества в одном приложении.', style: TextStyle(color: Colors.white70, height: 1.5)),
                      const SizedBox(height: 18),
                      FilledButton.icon(
                        style: FilledButton.styleFrom(backgroundColor: AppTheme.gold),
                        onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const TogetherScreen())),
                        icon: const Icon(Icons.groups),
                        label: const Text('Паломничество вместе'),
                      ),
                    ],
                  ),
                ),
                const SectionTitle(title: 'Рекомендуемые святыни'),
                SizedBox(
                  height: 225,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: objects.length,
                    separatorBuilder: (_, __) => const SizedBox(width: 12),
                    itemBuilder: (context, index) => SizedBox(width: 270, child: ObjectCard(item: objects[index])),
                  ),
                ),
                const SectionTitle(title: 'Ближайшие события'),
                ...events.take(4).map((event) => EventTile(item: event)),
                const SectionTitle(title: 'Паломнические маршруты'),
                ...routes.take(4).map((route) => BasicCard(
                      title: '${route['title'] ?? 'Маршрут'}',
                      subtitle: '${route['short_description'] ?? route['description'] ?? ''}',
                      icon: Icons.signpost_outlined,
                    )),
              ],
            );
          },
        ),
      ),
    );
  }
}

class CatalogTab extends StatefulWidget {
  const CatalogTab({super.key});

  @override
  State<CatalogTab> createState() => _CatalogTabState();
}

class _CatalogTabState extends State<CatalogTab> {
  final _search = TextEditingController();
  Future<List<Map<String, dynamic>>>? _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<Map<String, dynamic>>> _load() async {
    final response = await ApiClient.instance.dio.get('/objects', queryParameters: {
      if (_search.text.trim().isNotEmpty) 'q': _search.text.trim(),
      'per_page': 50,
    });
    final payload = response.data as Map;
    final data = payload['data'] as List? ?? [];
    return data.map((item) => Map<String, dynamic>.from(item as Map)).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Храмы и святыни')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => setState(() => _future = _load()),
              decoration: InputDecoration(
                hintText: 'Название, адрес или святыня',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: IconButton(onPressed: () => setState(() => _future = _load()), icon: const Icon(Icons.arrow_forward)),
              ),
            ),
          ),
          Expanded(
            child: FutureBuilder<List<Map<String, dynamic>>>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
                if (snapshot.hasError) return ErrorPane(error: snapshot.error!, onRetry: () => setState(() => _future = _load()));
                final items = snapshot.data!;
                if (items.isEmpty) return const Center(child: Text('Объекты не найдены'));
                return RefreshIndicator(
                  onRefresh: () async => setState(() => _future = _load()),
                  child: ListView.builder(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                    itemCount: items.length,
                    itemBuilder: (context, index) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: ObjectCard(item: items[index]),
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

class MapTab extends StatefulWidget {
  const MapTab({super.key});

  @override
  State<MapTab> createState() => _MapTabState();
}

class _MapTabState extends State<MapTab> {
  late final WebViewController _controller;
  int _progress = 0;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(NavigationDelegate(onProgress: (value) => setState(() => _progress = value)))
      ..loadRequest(Uri.parse('${ApiClient.siteBaseUrl}/map'));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Интерактивная карта')),
      body: Column(
        children: [
          if (_progress < 100) LinearProgressIndicator(value: _progress / 100),
          Expanded(child: WebViewWidget(controller: _controller)),
        ],
      ),
    );
  }
}

class CalendarTab extends StatefulWidget {
  const CalendarTab({super.key});

  @override
  State<CalendarTab> createState() => _CalendarTabState();
}

class _CalendarTabState extends State<CalendarTab> {
  Future<List<Map<String, dynamic>>>? _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<Map<String, dynamic>>> _load() async {
    final response = await ApiClient.instance.dio.get('/mobile/calendar');
    return (response.data['data'] as List).map((item) => Map<String, dynamic>.from(item as Map)).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Календарь событий')),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
          if (snapshot.hasError) return ErrorPane(error: snapshot.error!, onRetry: () => setState(() => _future = _load()));
          return RefreshIndicator(
            onRefresh: () async => setState(() => _future = _load()),
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: snapshot.data!.length,
              itemBuilder: (context, index) => EventTile(item: snapshot.data![index]),
            ),
          );
        },
      ),
    );
  }
}

class ProfileTab extends StatelessWidget {
  const ProfileTab({super.key, required this.session});
  final SessionController session;

  @override
  Widget build(BuildContext context) {
    final user = session.user ?? const {};
    return Scaffold(
      appBar: AppBar(title: const Text('Личный кабинет')),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 34,
                    backgroundColor: AppTheme.cream,
                    backgroundImage: user['avatar_url'] != null ? NetworkImage('${user['avatar_url']}') : null,
                    child: user['avatar_url'] == null ? const Icon(Icons.person, size: 34, color: AppTheme.green) : null,
                  ),
                  const SizedBox(width: 16),
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('${user['name'] ?? 'Паломник'}', style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 4),
                    Text('${user['email'] ?? ''}', style: const TextStyle(color: Colors.black54)),
                  ])),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          ProfileAction(icon: Icons.favorite_outline, title: 'Избранное', onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const FavoritesScreen()))),
          ProfileAction(icon: Icons.confirmation_number_outlined, title: 'Бронирования и QR-билеты', onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const BookingsScreen()))),
          ProfileAction(icon: Icons.emoji_events_outlined, title: 'Достижения и статистика', onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ProfileStatsScreen()))),
          ProfileAction(icon: Icons.route_outlined, title: 'Мои маршруты', onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const RoutePlansScreen()))),
          ProfileAction(icon: Icons.groups_outlined, title: 'Совместные паломничества', onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const TogetherScreen()))),
          ProfileAction(icon: Icons.notifications_none, title: 'Уведомления', onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const NotificationsScreen()))),
          ProfileAction(icon: Icons.logout, title: 'Выйти', color: Colors.red, onTap: session.logout),
        ],
      ),
    );
  }
}

class CommunityScreen extends StatelessWidget {
  const CommunityScreen({super.key});

  @override
  Widget build(BuildContext context) => ApiListScreen(
        title: 'Сообщество',
        endpoint: '/mobile/community',
        icon: Icons.article_outlined,
        itemTitle: (item) => '${item['title'] ?? 'Путевая заметка'}',
        itemSubtitle: (item) => '${item['excerpt'] ?? item['body'] ?? ''}',
        header: FilledButton.icon(
          onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const TogetherScreen())),
          icon: const Icon(Icons.groups),
          label: const Text('Паломничество вместе'),
        ),
      );
}

class TogetherScreen extends StatelessWidget {
  const TogetherScreen({super.key});

  @override
  Widget build(BuildContext context) => ApiListScreen(
        title: 'Паломничество вместе',
        endpoint: '/mobile/together',
        icon: Icons.groups_outlined,
        itemTitle: (item) => '${item['title'] ?? 'Совместное паломничество'}',
        itemSubtitle: (item) => '${item['meeting_place'] ?? ''}\n${formatDate(item['starts_at'])}',
      );
}

class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context) => ApiListScreen(
        title: 'Уведомления',
        endpoint: '/mobile/notifications',
        icon: Icons.notifications_outlined,
        itemTitle: (item) {
          final data = item['data'];
          if (data is Map) return '${data['title'] ?? data['message'] ?? 'Уведомление'}';
          return 'Уведомление';
        },
        itemSubtitle: (item) => formatDate(item['created_at']),
      );
}

class FavoritesScreen extends StatelessWidget {
  const FavoritesScreen({super.key});

  @override
  Widget build(BuildContext context) => FutureListPage(
        title: 'Избранное',
        loader: () async {
          final response = await ApiClient.instance.dio.get('/mobile/favorites');
          final lists = response.data['data'] as List? ?? [];
          return lists.expand((list) => (list['objects'] as List? ?? [])).map((item) => Map<String, dynamic>.from(item as Map)).toList();
        },
        builder: (item) => ObjectCard(item: item),
      );
}

class BookingsScreen extends StatelessWidget {
  const BookingsScreen({super.key});

  @override
  Widget build(BuildContext context) => FutureListPage(
        title: 'Бронирования и билеты',
        loader: () async {
          final response = await ApiClient.instance.dio.get('/mobile/bookings');
          return (response.data['data'] as List? ?? []).map((item) => Map<String, dynamic>.from(item as Map)).toList();
        },
        builder: (item) => Card(
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${item['route_title'] ?? item['trip_title'] ?? 'Паломническая поездка'}', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              Text(formatDate(item['starts_at'])),
              Text('${item['meeting_point'] ?? ''}', style: const TextStyle(color: Colors.black54)),
              const SizedBox(height: 12),
              Wrap(spacing: 8, children: [
                Chip(label: Text('${item['status'] ?? ''}')),
                Chip(label: Text('${item['payment_status'] ?? ''}')),
              ]),
              if (item['ticket_token'] != null) ...[
                const SizedBox(height: 12),
                FilledButton.icon(
                  onPressed: () => showDialog<void>(
                    context: context,
                    builder: (_) => AlertDialog(
                      title: Text('Билет ${item['ticket_code'] ?? ''}'),
                      content: SizedBox(
                        width: 280,
                        child: Column(mainAxisSize: MainAxisSize.min, children: [
                          QrImageView(data: 'MP-TICKET:${item['ticket_token']}', size: 240),
                          const SizedBox(height: 12),
                          Text('${item['participants_count'] ?? 1} участник(а)'),
                        ]),
                      ),
                    ),
                  ),
                  icon: const Icon(Icons.qr_code_2),
                  label: const Text('Показать QR-билет'),
                ),
              ],
            ]),
          ),
        ),
      );
}

class ProfileStatsScreen extends StatelessWidget {
  const ProfileStatsScreen({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Достижения и статистика')),
        body: FutureBuilder<dynamic>(
          future: ApiClient.instance.dio.get('/mobile/profile'),
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
            if (snapshot.hasError) return ErrorPane(error: snapshot.error!, onRetry: () {});
            final data = snapshot.data.data as Map;
            final stats = Map<String, dynamic>.from(data['stats'] as Map? ?? {});
            final achievements = data['achievements'] as List? ?? [];
            return ListView(padding: const EdgeInsets.all(18), children: [
              Wrap(spacing: 10, runSpacing: 10, children: stats.entries.map((entry) => Chip(label: Text('${entry.key}: ${entry.value}'))).toList()),
              const SectionTitle(title: 'Полученные достижения'),
              ...achievements.map((item) => BasicCard(title: '${item['title'] ?? 'Достижение'}', subtitle: '${item['description'] ?? ''}', icon: Icons.emoji_events_outlined)),
            ]);
          },
        ),
      );
}

class RoutePlansScreen extends StatelessWidget {
  const RoutePlansScreen({super.key});

  @override
  Widget build(BuildContext context) => ApiListScreen(
        title: 'Мои маршруты',
        endpoint: '/mobile/route-plans',
        icon: Icons.route_outlined,
        itemTitle: (item) => '${item['name'] ?? item['title'] ?? 'Маршрут'}',
        itemSubtitle: (item) => '${item['description'] ?? ''}',
      );
}

class ApiListScreen extends StatelessWidget {
  const ApiListScreen({
    super.key,
    required this.title,
    required this.endpoint,
    required this.icon,
    required this.itemTitle,
    required this.itemSubtitle,
    this.header,
  });

  final String title;
  final String endpoint;
  final IconData icon;
  final String Function(Map<String, dynamic>) itemTitle;
  final String Function(Map<String, dynamic>) itemSubtitle;
  final Widget? header;

  @override
  Widget build(BuildContext context) => FutureListPage(
        title: title,
        loader: () async {
          final response = await ApiClient.instance.dio.get(endpoint);
          final payload = response.data as Map;
          return (payload['data'] as List? ?? []).map((item) => Map<String, dynamic>.from(item as Map)).toList();
        },
        header: header,
        builder: (item) => BasicCard(title: itemTitle(item), subtitle: itemSubtitle(item), icon: icon),
      );
}

class FutureListPage extends StatefulWidget {
  const FutureListPage({super.key, required this.title, required this.loader, required this.builder, this.header});
  final String title;
  final Future<List<Map<String, dynamic>>> Function() loader;
  final Widget Function(Map<String, dynamic>) builder;
  final Widget? header;

  @override
  State<FutureListPage> createState() => _FutureListPageState();
}

class _FutureListPageState extends State<FutureListPage> {
  late Future<List<Map<String, dynamic>>> _future = widget.loader();

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(widget.title)),
        body: FutureBuilder<List<Map<String, dynamic>>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
            if (snapshot.hasError) return ErrorPane(error: snapshot.error!, onRetry: () => setState(() => _future = widget.loader()));
            final items = snapshot.data!;
            return RefreshIndicator(
              onRefresh: () async => setState(() => _future = widget.loader()),
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (widget.header != null) ...[widget.header!, const SizedBox(height: 16)],
                  if (items.isEmpty) const Padding(padding: EdgeInsets.only(top: 100), child: Center(child: Text('Пока нет данных'))),
                  ...items.map((item) => Padding(padding: const EdgeInsets.only(bottom: 12), child: widget.builder(item))),
                ],
              ),
            );
          },
        ),
      );
}

class ObjectCard extends StatelessWidget {
  const ObjectCard({super.key, required this.item});
  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    final cover = item['cover_url'] ?? item['cover'];
    final address = item['address'] ?? (item['location'] is Map ? item['location']['address'] : null);
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (cover != null)
          Image.network('$cover', height: 120, width: double.infinity, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const SizedBox(height: 120, child: Center(child: Icon(Icons.church, size: 46))))
        else
          const SizedBox(height: 120, child: Center(child: Icon(Icons.church, size: 46, color: AppTheme.gold))),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('${item['name'] ?? 'Паломнический объект'}', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 7),
            Text('${address ?? ''}', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.black54)),
            const SizedBox(height: 12),
            Row(children: [
              TextButton.icon(
                onPressed: () async {
                  final lat = item['latitude'] ?? (item['location'] is Map ? item['location']['latitude'] : null);
                  final lon = item['longitude'] ?? (item['location'] is Map ? item['location']['longitude'] : null);
                  if (lat != null && lon != null) {
                    await launchUrl(Uri.parse('https://yandex.ru/maps/?pt=$lon,$lat&z=15&l=map'), mode: LaunchMode.externalApplication);
                  }
                },
                icon: const Icon(Icons.directions),
                label: const Text('Маршрут'),
              ),
            ]),
          ]),
        ),
      ]),
    );
  }
}

class EventTile extends StatelessWidget {
  const EventTile({super.key, required this.item});
  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) => BasicCard(
        title: '${item['title'] ?? 'Событие'}',
        subtitle: '${formatDate(item['starts_at'])}\n${item['location'] ?? item['address'] ?? ''}',
        icon: Icons.event_outlined,
      );
}

class BasicCard extends StatelessWidget {
  const BasicCard({super.key, required this.title, required this.subtitle, required this.icon});
  final String title;
  final String subtitle;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Card(
        child: ListTile(
          contentPadding: const EdgeInsets.all(16),
          leading: CircleAvatar(backgroundColor: AppTheme.cream, child: Icon(icon, color: AppTheme.green)),
          title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
          subtitle: Padding(padding: const EdgeInsets.only(top: 7), child: Text(subtitle, maxLines: 4, overflow: TextOverflow.ellipsis)),
        ),
      );
}

class ProfileAction extends StatelessWidget {
  const ProfileAction({super.key, required this.icon, required this.title, required this.onTap, this.color});
  final IconData icon;
  final String title;
  final VoidCallback onTap;
  final Color? color;

  @override
  Widget build(BuildContext context) => Card(
        child: ListTile(
          leading: Icon(icon, color: color ?? AppTheme.green),
          title: Text(title, style: TextStyle(color: color)),
          trailing: const Icon(Icons.chevron_right),
          onTap: onTap,
        ),
      );
}

class SectionTitle extends StatelessWidget {
  const SectionTitle({super.key, required this.title});
  final String title;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 26, bottom: 12),
        child: Text(title, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
      );
}

class ErrorPane extends StatelessWidget {
  const ErrorPane({super.key, required this.error, required this.onRetry});
  final Object error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off, size: 52, color: Colors.black45),
            const SizedBox(height: 14),
            Text(ApiClient.instance.messageFrom(error), textAlign: TextAlign.center),
            const SizedBox(height: 14),
            OutlinedButton(onPressed: onRetry, child: const Text('Повторить')),
          ]),
        ),
      );
}

String formatDate(dynamic value) {
  if (value == null) return '';
  final date = DateTime.tryParse('$value')?.toLocal();
  if (date == null) return '$value';
  return DateFormat('dd.MM.yyyy HH:mm', 'ru').format(date);
}
