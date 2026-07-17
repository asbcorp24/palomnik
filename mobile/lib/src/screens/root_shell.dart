import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../core/api_client.dart';
import '../core/session_controller.dart';
import '../theme/app_theme.dart';
import 'user_features.dart';

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
    final user = widget.session.user ?? const <String, dynamic>{};
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Московский паломник'),
            Text(
              user['name'] == null ? 'Путеводитель по святыням' : 'Здравствуйте, ${user['name']}',
              style: Theme.of(context).textTheme.labelSmall?.copyWith(color: Colors.black54),
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Сообщество',
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CommunityScreen())),
            icon: const Icon(Icons.groups_outlined),
          ),
          IconButton(
            tooltip: 'Уведомления',
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NotificationsScreen())),
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
              return const ListView(children: [SizedBox(height: 320), Center(child: CircularProgressIndicator())]);
            }
            if (snapshot.hasError) {
              return ListView(children: [SizedBox(height: 500, child: ErrorPane(error: snapshot.error!, onRetry: () => setState(() => _future = _load())))]);
            }

            final data = snapshot.data!;
            final objects = _mapList(data['objects']);
            final routes = _mapList(data['routes']);
            final events = _mapList(data['events']);

            return ListView(
              padding: const EdgeInsets.fromLTRB(18, 12, 18, 32),
              children: [
                Container(
                  padding: const EdgeInsets.all(22),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(26),
                    gradient: const LinearGradient(colors: [AppTheme.green, Color(0xFF18322A)]),
                  ),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    const Icon(Icons.church, color: AppTheme.gold, size: 40),
                    const SizedBox(height: 16),
                    const Text('Святые места становятся ближе', style: TextStyle(color: Colors.white, fontSize: 25, fontWeight: FontWeight.w700)),
                    const SizedBox(height: 10),
                    const Text('Храмы, святыни, маршруты, события, билеты и совместные паломничества в одном приложении.', style: TextStyle(color: Colors.white70, height: 1.5)),
                    const SizedBox(height: 18),
                    Wrap(spacing: 8, runSpacing: 8, children: [
                      FilledButton.icon(
                        style: FilledButton.styleFrom(backgroundColor: AppTheme.gold),
                        onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TogetherScreen())),
                        icon: const Icon(Icons.groups),
                        label: const Text('Паломничество вместе'),
                      ),
                      OutlinedButton.icon(
                        style: OutlinedButton.styleFrom(foregroundColor: Colors.white, side: const BorderSide(color: Colors.white54)),
                        onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const RoutesScreen())),
                        icon: const Icon(Icons.route),
                        label: const Text('Маршруты'),
                      ),
                    ]),
                  ]),
                ),
                const SectionTitle(title: 'Рекомендуемые святыни'),
                if (objects.isEmpty)
                  const EmptyCard(text: 'Добавьте объекты через административную панель.')
                else
                  SizedBox(
                    height: 255,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: objects.length,
                      separatorBuilder: (_, __) => const SizedBox(width: 12),
                      itemBuilder: (context, index) => SizedBox(width: 280, child: ObjectCard(item: objects[index])),
                    ),
                  ),
                const SectionTitle(title: 'Ближайшие события'),
                if (events.isEmpty) const EmptyCard(text: 'События пока не опубликованы.'),
                ...events.take(4).map((event) => EventTile(item: event)),
                const SectionTitle(title: 'Паломнические маршруты'),
                if (routes.isEmpty) const EmptyCard(text: 'Маршруты пока не опубликованы.'),
                ...routes.take(4).map(
                      (route) => BasicCard(
                        title: '${route['title'] ?? 'Маршрут'}',
                        subtitle: '${route['short_description'] ?? ''}',
                        icon: Icons.signpost_outlined,
                        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => RouteDetailScreen(slug: '${route['slug']}'))),
                      ),
                    ),
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

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<List<Map<String, dynamic>>> _load() async {
    final response = await ApiClient.instance.dio.get('/objects', queryParameters: {
      if (_search.text.trim().isNotEmpty) 'q': _search.text.trim(),
      'per_page': 50,
    });
    return _mapList((response.data as Map)['data']);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Храмы и святыни')),
      body: Column(children: [
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
      ]),
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
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: const Text('Интерактивная карта'),
          actions: [IconButton(onPressed: () => _controller.reload(), icon: const Icon(Icons.refresh))],
        ),
        body: Column(children: [if (_progress < 100) LinearProgressIndicator(value: _progress / 100), Expanded(child: WebViewWidget(controller: _controller))]),
      );
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
    return _mapList(response.data['data']);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Календарь событий')),
        body: FutureBuilder<List<Map<String, dynamic>>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
            if (snapshot.hasError) return ErrorPane(error: snapshot.error!, onRetry: () => setState(() => _future = _load()));
            final events = snapshot.data!;
            return RefreshIndicator(
              onRefresh: () async => setState(() => _future = _load()),
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (events.isEmpty) const Padding(padding: EdgeInsets.only(top: 120), child: Center(child: Text('События пока не опубликованы'))),
                  ...events.map((event) => EventTile(item: event)),
                ],
              ),
            );
          },
        ),
      );
}

class ProfileTab extends StatelessWidget {
  const ProfileTab({super.key, required this.session});
  final SessionController session;

  @override
  Widget build(BuildContext context) {
    final user = session.user ?? const <String, dynamic>{};
    return Scaffold(
      appBar: AppBar(title: const Text('Личный кабинет')),
      body: ListView(padding: const EdgeInsets.all(18), children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Row(children: [
              CircleAvatar(
                radius: 34,
                backgroundColor: AppTheme.cream,
                backgroundImage: user['avatar_url'] != null ? NetworkImage('${user['avatar_url']}') : null,
                child: user['avatar_url'] == null ? const Icon(Icons.person, size: 34, color: AppTheme.green) : null,
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('${user['name'] ?? 'Паломник'}', style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 4),
                  Text('${user['email'] ?? ''}', style: const TextStyle(color: Colors.black54)),
                  if (user['is_verified_organizer'] == true) const Padding(padding: EdgeInsets.only(top: 5), child: Text('Проверенный организатор ✓', style: TextStyle(color: AppTheme.green, fontWeight: FontWeight.w600))),
                ]),
              ),
            ]),
          ),
        ),
        const SizedBox(height: 16),
        ProfileAction(icon: Icons.favorite_outline, title: 'Избранное', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const FavoritesScreen()))),
        ProfileAction(icon: Icons.confirmation_number_outlined, title: 'Бронирования и QR-билеты', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const BookingsScreen()))),
        ProfileAction(icon: Icons.emoji_events_outlined, title: 'Достижения и статистика', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ProfileStatsScreen()))),
        ProfileAction(icon: Icons.route_outlined, title: 'Мои маршруты', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const RoutePlansScreen()))),
        ProfileAction(icon: Icons.groups_outlined, title: 'Совместные паломничества', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TogetherScreen()))),
        ProfileAction(icon: Icons.notifications_none, title: 'Уведомления', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NotificationsScreen()))),
        ProfileAction(icon: Icons.settings_outlined, title: 'Настройки профиля', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ProfileSettingsScreen(session: session)))),
        ProfileAction(icon: Icons.logout, title: 'Выйти', color: Colors.red, onTap: session.logout),
      ]),
    );
  }
}

class RoutesScreen extends StatelessWidget {
  const RoutesScreen({super.key});

  @override
  Widget build(BuildContext context) => ApiListScreen(
        title: 'Паломнические маршруты',
        endpoint: '/mobile/routes',
        icon: Icons.route_outlined,
        itemTitle: (item) => '${item['title'] ?? 'Маршрут'}',
        itemSubtitle: (item) => '${item['short_description'] ?? ''}\n${item['objects_count'] ?? 0} точек',
        onTap: (item) => Navigator.push(context, MaterialPageRoute(builder: (_) => RouteDetailScreen(slug: '${item['slug']}'))),
      );
}

class CommunityScreen extends StatelessWidget {
  const CommunityScreen({super.key});

  @override
  Widget build(BuildContext context) => ApiListScreen(
        title: 'Сообщество',
        endpoint: '/mobile/community',
        icon: Icons.article_outlined,
        itemTitle: (item) => '${item['title'] ?? 'Путевая заметка'}',
        itemSubtitle: (item) => '${item['excerpt'] ?? ''}',
        onTap: (item) => Navigator.push(context, MaterialPageRoute(builder: (_) => PostDetailScreen(slug: '${item['slug']}'))),
        header: FilledButton.icon(
          onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TogetherScreen())),
          icon: const Icon(Icons.groups),
          label: const Text('Паломничество вместе'),
        ),
      );
}

class TogetherScreen extends StatefulWidget {
  const TogetherScreen({super.key});

  @override
  State<TogetherScreen> createState() => _TogetherScreenState();
}

class _TogetherScreenState extends State<TogetherScreen> {
  Future<List<Map<String, dynamic>>> _load() async {
    final response = await ApiClient.instance.dio.get('/mobile/together');
    return _mapList(response.data['data']);
  }

  @override
  Widget build(BuildContext context) => FutureListPage(
        title: 'Паломничество вместе',
        loader: _load,
        header: FilledButton.icon(
          onPressed: () async {
            final created = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => const CreateTogetherScreen()));
            if (created == true) setState(() {});
          },
          icon: const Icon(Icons.add),
          label: const Text('Предложить поездку'),
        ),
        builder: (item) => BasicCard(
          title: '${item['title'] ?? 'Совместное паломничество'}',
          subtitle: '${item['meeting_place'] ?? ''}\n${formatDate(item['starts_at'])}\n${item['participants_count'] ?? 1}/${item['max_participants'] ?? '∞'} участников',
          icon: Icons.groups_outlined,
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TogetherDetailScreen(slug: '${item['slug']}'))),
        ),
      );
}

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  Future<List<Map<String, dynamic>>> _load() async {
    final response = await ApiClient.instance.dio.get('/mobile/notifications');
    return _mapList(response.data['data']);
  }

  Future<void> _read(Map<String, dynamic> item) async {
    if (item['read_at'] == null) {
      await ApiClient.instance.dio.put('/mobile/notifications/${item['id']}/read');
    }
    final data = item['data'];
    if (data is Map && data['url'] != null) {
      final uri = Uri.tryParse('${data['url']}');
      if (uri != null) await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) => FutureListPage(
        title: 'Уведомления',
        loader: _load,
        builder: (item) {
          final data = item['data'] is Map ? Map<String, dynamic>.from(item['data'] as Map) : <String, dynamic>{};
          return BasicCard(
            title: '${data['title'] ?? 'Уведомление'}',
            subtitle: '${data['message'] ?? data['body'] ?? ''}\n${formatDate(item['created_at'])}',
            icon: item['read_at'] == null ? Icons.notifications_active : Icons.notifications_none,
            onTap: () => _read(item),
          );
        },
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

class BookingsScreen extends StatefulWidget {
  const BookingsScreen({super.key});

  @override
  State<BookingsScreen> createState() => _BookingsScreenState();
}

class _BookingsScreenState extends State<BookingsScreen> {
  Future<List<Map<String, dynamic>>> _load() async {
    final response = await ApiClient.instance.dio.get('/mobile/bookings');
    return _mapList(response.data['data']);
  }

  Future<void> _cancel(Map<String, dynamic> item) async {
    try {
      await ApiClient.instance.dio.delete('/mobile/bookings/${item['id']}');
      if (mounted) setState(() {});
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.instance.messageFrom(error)), backgroundColor: Colors.red));
    }
  }

  @override
  Widget build(BuildContext context) => FutureListPage(
        title: 'Бронирования и QR-билеты',
        loader: _load,
        builder: (item) {
          final trip = item['trip'] is Map ? Map<String, dynamic>.from(item['trip'] as Map) : <String, dynamic>{};
          final route = trip['route'] is Map ? Map<String, dynamic>.from(trip['route'] as Map) : <String, dynamic>{};
          final qrPayload = item['qr_payload'];
          final closed = ['cancelled', 'completed', 'refunded'].contains(item['status']);
          return Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('${route['title'] ?? trip['title'] ?? 'Паломническая поездка'}', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 8),
                Text(formatDate(trip['starts_at'])),
                Text('${trip['meeting_point'] ?? ''}', style: const TextStyle(color: Colors.black54)),
                const SizedBox(height: 10),
                Text('Участников: ${item['participants_count'] ?? 1} · сумма: ${item['total_amount'] ?? 0} ₽'),
                const SizedBox(height: 10),
                Wrap(spacing: 8, runSpacing: 8, children: [
                  Chip(label: Text('${item['status'] ?? ''}')),
                  Chip(label: Text('${item['payment_status'] ?? ''}')),
                  if (item['checked_in_at'] != null) const Chip(label: Text('Посадка подтверждена')),
                ]),
                if (qrPayload != null) ...[
                  const SizedBox(height: 12),
                  FilledButton.icon(
                    onPressed: () => showDialog<void>(
                      context: context,
                      builder: (_) => AlertDialog(
                        title: Text('Билет ${item['ticket_code'] ?? ''}'),
                        content: SizedBox(
                          width: 280,
                          child: Column(mainAxisSize: MainAxisSize.min, children: [
                            QrImageView(data: '$qrPayload', size: 240),
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
                if (item['calendar_url'] != null)
                  TextButton.icon(onPressed: () => launchUrl(Uri.parse('${item['calendar_url']}'), mode: LaunchMode.externalApplication), icon: const Icon(Icons.event_available), label: const Text('Добавить в календарь')),
                if (!closed)
                  TextButton.icon(onPressed: () => _cancel(item), icon: const Icon(Icons.cancel_outlined), label: const Text('Отменить бронирование'), style: TextButton.styleFrom(foregroundColor: Colors.red)),
              ]),
            ),
          );
        },
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
              Wrap(spacing: 10, runSpacing: 10, children: stats.entries.map((entry) => Chip(label: Text('${_statLabel(entry.key)}: ${entry.value}'))).toList()),
              const SectionTitle(title: 'Полученные достижения'),
              if (achievements.isEmpty) const EmptyCard(text: 'Достижения появятся после подтверждённых посещений и поездок.'),
              ...achievements.map((item) => BasicCard(title: '${item['title'] ?? 'Достижение'}', subtitle: '${item['description'] ?? ''}\n${item['points'] ?? 0} баллов', icon: Icons.emoji_events_outlined)),
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
        itemTitle: (item) => '${item['name'] ?? 'Маршрут'}',
        itemSubtitle: (item) => '${item['transport_mode'] ?? ''}\n${(item['objects'] as List? ?? []).length} точек',
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
    this.onTap,
  });

  final String title;
  final String endpoint;
  final IconData icon;
  final String Function(Map<String, dynamic>) itemTitle;
  final String Function(Map<String, dynamic>) itemSubtitle;
  final Widget? header;
  final void Function(Map<String, dynamic>)? onTap;

  @override
  Widget build(BuildContext context) => FutureListPage(
        title: title,
        loader: () async {
          final response = await ApiClient.instance.dio.get(endpoint);
          return _mapList((response.data as Map)['data']);
        },
        header: header,
        builder: (item) => BasicCard(title: itemTitle(item), subtitle: itemSubtitle(item), icon: icon, onTap: onTap == null ? null : () => onTap!(item)),
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

  Future<void> _refresh() async {
    final future = widget.loader();
    setState(() => _future = future);
    await future;
  }

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
              onRefresh: _refresh,
              child: ListView(padding: const EdgeInsets.all(16), children: [
                if (widget.header != null) ...[widget.header!, const SizedBox(height: 16)],
                if (items.isEmpty) const Padding(padding: EdgeInsets.only(top: 100), child: Center(child: Text('Пока нет данных'))),
                ...items.map((item) => Padding(padding: const EdgeInsets.only(bottom: 12), child: widget.builder(item))),
              ]),
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
    final rawCover = item['cover_url'] ?? item['cover'];
    final cover = rawCover is Map ? rawCover['url'] : rawCover;
    final location = item['location'] is Map ? Map<String, dynamic>.from(item['location'] as Map) : <String, dynamic>{};
    final address = item['address'] ?? location['address'];
    final lat = item['latitude'] ?? location['latitude'];
    final lon = item['longitude'] ?? location['longitude'];

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: item['slug'] == null ? null : () => Navigator.push(context, MaterialPageRoute(builder: (_) => ObjectDetailScreen(slug: '${item['slug']}'))),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          if (cover != null)
            Image.network('$cover', height: 120, width: double.infinity, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const SizedBox(height: 120, child: Center(child: Icon(Icons.church, size: 46))))
          else
            const SizedBox(height: 120, child: Center(child: Icon(Icons.church, size: 46, color: AppTheme.gold))),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${item['name'] ?? 'Паломнический объект'}', maxLines: 2, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
              const SizedBox(height: 7),
              Text('${address ?? ''}', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.black54)),
              const SizedBox(height: 10),
              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                TextButton.icon(
                  onPressed: lat != null && lon != null ? () => launchUrl(Uri.parse('https://yandex.ru/maps/?pt=$lon,$lat&z=15&l=map'), mode: LaunchMode.externalApplication) : null,
                  icon: const Icon(Icons.directions),
                  label: const Text('Маршрут'),
                ),
                const Icon(Icons.chevron_right),
              ]),
            ]),
          ),
        ]),
      ),
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
        onTap: item['slug'] == null ? null : () => Navigator.push(context, MaterialPageRoute(builder: (_) => EventDetailScreen(slug: '${item['slug']}'))),
      );
}

class BasicCard extends StatelessWidget {
  const BasicCard({super.key, required this.title, required this.subtitle, required this.icon, this.onTap});
  final String title;
  final String subtitle;
  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Card(
        child: ListTile(
          contentPadding: const EdgeInsets.all(16),
          leading: CircleAvatar(backgroundColor: AppTheme.cream, child: Icon(icon, color: AppTheme.green)),
          title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
          subtitle: Padding(padding: const EdgeInsets.only(top: 7), child: Text(subtitle, maxLines: 5, overflow: TextOverflow.ellipsis)),
          trailing: onTap == null ? null : const Icon(Icons.chevron_right),
          onTap: onTap,
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
        child: Text(title, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700, color: AppTheme.green)),
      );
}

class EmptyCard extends StatelessWidget {
  const EmptyCard({super.key, required this.text});
  final String text;

  @override
  Widget build(BuildContext context) => Card(child: Padding(padding: const EdgeInsets.all(20), child: Text(text)));
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

List<Map<String, dynamic>> _mapList(dynamic value) {
  final list = value is List ? value : const [];
  return list.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList();
}

String formatDate(dynamic value) {
  if (value == null) return '';
  final date = DateTime.tryParse('$value')?.toLocal();
  if (date == null) return '$value';
  return DateFormat('dd.MM.yyyy HH:mm', 'ru').format(date);
}

String _statLabel(String key) => const {
      'bookings': 'Бронирования',
      'visits': 'Посещения',
      'reviews': 'Отзывы',
      'posts': 'Заметки',
      'media': 'Медиа',
      'favorite_lists': 'Списки',
      'achievements': 'Достижения',
    }[key] ?? key;
