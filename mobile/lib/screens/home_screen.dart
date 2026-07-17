import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:moscow_pilgrim/core/api_client.dart';
import 'package:moscow_pilgrim/core/app_theme.dart';
import 'package:moscow_pilgrim/core/session_controller.dart';
import 'package:moscow_pilgrim/models/models.dart';
import 'package:moscow_pilgrim/screens/calendar_screen.dart';
import 'package:moscow_pilgrim/screens/notifications_screen.dart';
import 'package:moscow_pilgrim/screens/object_detail_screen.dart';
import 'package:moscow_pilgrim/screens/objects_screen.dart';
import 'package:moscow_pilgrim/screens/routes_screen.dart';
import 'package:moscow_pilgrim/screens/together_screen.dart';
import 'package:moscow_pilgrim/widgets/common.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  bool _loading = true;
  String? _error;
  List<PilgrimageObjectModel> _objects = [];
  List<RouteModel> _routes = [];
  List<EventModel> _events = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await ApiClient.instance.getJson('/mobile/home');
      if (!mounted) return;
      setState(() {
        _objects = (response['objects'] as List? ?? const [])
            .whereType<Map>()
            .map((item) => PilgrimageObjectModel.fromJson(Map<String, dynamic>.from(item)))
            .toList();
        _routes = (response['routes'] as List? ?? const [])
            .whereType<Map>()
            .map((item) => RouteModel.fromJson(Map<String, dynamic>.from(item)))
            .toList();
        _events = (response['events'] as List? ?? const [])
            .whereType<Map>()
            .map((item) => EventModel.fromJson(Map<String, dynamic>.from(item)))
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
    final user = SessionController.instance.user;
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Московский паломник'),
            Text(
              user == null ? 'Путеводитель по святыням' : 'Здравствуйте, ${user.name}',
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
            ),
          ],
        ),
        actions: [
          if (user != null)
            IconButton(
              onPressed: () => openPage(context, const NotificationsScreen()),
              icon: const Icon(Icons.notifications_none_rounded),
              tooltip: 'Уведомления',
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _loading
            ? const LoadingView(label: 'Готовим рекомендации...')
            : _error != null
                ? ListView(children: [SizedBox(height: 500, child: ErrorView(message: _error!, onRetry: _load))])
                : _content(),
      ),
    );
  }

  Widget _content() {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
      children: [
        Container(
          padding: const EdgeInsets.all(22),
          decoration: BoxDecoration(
            gradient: const LinearGradient(colors: [AppTheme.green, AppTheme.greenDark]),
            borderRadius: BorderRadius.circular(26),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(Icons.church_rounded, color: AppTheme.gold, size: 40),
              const SizedBox(height: 18),
              Text(
                'Святые места становятся ближе',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                    ),
              ),
              const SizedBox(height: 10),
              Text(
                'Найдите храм, изучите святыни, выберите маршрут или договоритесь о совместном паломничестве.',
                style: TextStyle(color: Colors.white.withValues(alpha: 0.78), height: 1.45),
              ),
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: () => openPage(context, const ObjectsScreen()),
                icon: const Icon(Icons.search),
                label: const Text('Найти храм или святыню'),
                style: FilledButton.styleFrom(backgroundColor: AppTheme.gold),
              ),
            ],
          ),
        ),
        const SizedBox(height: 24),
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: 1.55,
          children: [
            _quickAction(Icons.church_outlined, 'Объекты', () => openPage(context, const ObjectsScreen())),
            _quickAction(Icons.route_outlined, 'Маршруты', () => openPage(context, const RoutesScreen())),
            _quickAction(Icons.calendar_month_outlined, 'Календарь', () => openPage(context, const CalendarScreen())),
            _quickAction(Icons.groups_outlined, 'Паломничество вместе', () => openPage(context, const TogetherScreen())),
          ],
        ),
        const SizedBox(height: 30),
        PageSectionTitle(
          title: 'Ближайшие события',
          subtitle: 'Богослужения, праздники и паломнические встречи',
          action: TextButton(
            onPressed: () => openPage(context, const CalendarScreen()),
            child: const Text('Все'),
          ),
        ),
        const SizedBox(height: 14),
        if (_events.isEmpty)
          const Card(child: Padding(padding: EdgeInsets.all(20), child: Text('События пока не опубликованы.')))
        else
          SizedBox(
            height: 165,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: _events.length,
              separatorBuilder: (_, _) => const SizedBox(width: 12),
              itemBuilder: (context, index) {
                final event = _events[index];
                return SizedBox(
                  width: 275,
                  child: Card(
                    child: InkWell(
                      borderRadius: BorderRadius.circular(22),
                      onTap: () => openPage(context, EventDetailScreen(slug: event.slug)),
                      child: Padding(
                        padding: const EdgeInsets.all(17),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            StatusChip(event.typeLabel ?? 'Событие', color: AppTheme.gold),
                            const SizedBox(height: 10),
                            Text(
                              event.title,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
                            ),
                            const Spacer(),
                            Text(
                              event.startsAt == null
                                  ? 'Дата уточняется'
                                  : DateFormat('d MMMM, HH:mm', 'ru').format(event.startsAt!),
                              style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        const SizedBox(height: 30),
        PageSectionTitle(
          title: 'Популярные места',
          action: TextButton(
            onPressed: () => openPage(context, const ObjectsScreen()),
            child: const Text('Каталог'),
          ),
        ),
        const SizedBox(height: 14),
        ..._objects.take(4).map(
              (object) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Card(
                  clipBehavior: Clip.antiAlias,
                  child: InkWell(
                    onTap: () => openPage(context, ObjectDetailScreen(slug: object.slug)),
                    child: Row(
                      children: [
                        SizedBox(
                          width: 110,
                          height: 108,
                          child: object.coverUrl == null
                              ? const ColoredBox(
                                  color: AppTheme.cream,
                                  child: Icon(Icons.church_rounded, color: AppTheme.gold, size: 38),
                                )
                              : CachedNetworkImage(imageUrl: object.coverUrl!, fit: BoxFit.cover),
                        ),
                        Expanded(
                          child: Padding(
                            padding: const EdgeInsets.all(14),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(object.typeName ?? 'Паломнический объект', style: const TextStyle(color: AppTheme.gold, fontSize: 11, fontWeight: FontWeight.w800)),
                                const SizedBox(height: 5),
                                Text(object.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800)),
                                const SizedBox(height: 6),
                                Text(object.address ?? '', maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant, fontSize: 12)),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
        const SizedBox(height: 18),
        PageSectionTitle(
          title: 'Паломнические маршруты',
          action: TextButton(
            onPressed: () => openPage(context, const RoutesScreen()),
            child: const Text('Все'),
          ),
        ),
        const SizedBox(height: 14),
        ..._routes.take(3).map(
              (route) => Card(
                child: ListTile(
                  contentPadding: const EdgeInsets.all(16),
                  leading: const CircleAvatar(
                    backgroundColor: AppTheme.cream,
                    foregroundColor: AppTheme.gold,
                    child: Icon(Icons.route_outlined),
                  ),
                  title: Text(route.title, style: const TextStyle(fontWeight: FontWeight.w800)),
                  subtitle: Text('${route.objectsCount ?? 0} точек · ${route.durationDays ?? 1} дн.'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => openPage(context, RouteDetailScreen(slug: route.slug)),
                ),
              ),
            ),
      ],
    );
  }

  Widget _quickAction(IconData icon, String title, VoidCallback onTap) {
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(22),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              Icon(icon, color: AppTheme.gold, size: 28),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
