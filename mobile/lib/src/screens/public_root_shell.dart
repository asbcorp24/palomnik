import 'package:flutter/material.dart';

import '../core/session_controller.dart';
import '../data/cached_api.dart';
import '../theme/app_theme.dart';
import 'app_sections.dart';
import 'audio_guides.dart';
import 'auth_screen.dart';
import 'community_hub.dart';
import 'enhanced_root_shell.dart';
import 'maplibre_map.dart';
import 'sorted_object_catalog.dart';

class PublicRootShell extends StatefulWidget {
  const PublicRootShell({super.key, required this.session});

  final SessionController session;

  @override
  State<PublicRootShell> createState() => _PublicRootShellState();
}

class _PublicRootShellState extends State<PublicRootShell> {
  int _index = 0;

  List<Widget> get _pages => [
        PublicHomeTab(
          onOpenAudioGuides: () => _select(2),
          onOpenCommunity: () => _select(5),
        ),
        const SortedObjectCatalogTab(),
        const AudioGuidesScreen(),
        const MapLibreMapTab(),
        const CalendarTab(),
        CommunityHubTab(
          session: widget.session,
          onOpenProfile: () => _select(6),
        ),
        widget.session.isAuthenticated
            ? EnhancedProfileTab(session: widget.session)
            : AuthScreen(session: widget.session),
      ];

  void _select(int value) {
    if (_index == value) return;
    setState(() => _index = value);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(index: _index, children: _pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        labelBehavior: NavigationDestinationLabelBehavior.onlyShowSelected,
        onDestinationSelected: _select,
        destinations: [
          const NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home),
            label: 'Главная',
          ),
          const NavigationDestination(
            icon: Icon(Icons.church_outlined),
            selectedIcon: Icon(Icons.church),
            label: 'Святыни',
          ),
          const NavigationDestination(
            icon: Icon(Icons.headphones_outlined),
            selectedIcon: Icon(Icons.headphones),
            label: 'Аудиогиды',
          ),
          const NavigationDestination(
            icon: Icon(Icons.map_outlined),
            selectedIcon: Icon(Icons.map),
            label: 'Карта',
          ),
          const NavigationDestination(
            icon: Icon(Icons.calendar_month_outlined),
            selectedIcon: Icon(Icons.calendar_month),
            label: 'События',
          ),
          const NavigationDestination(
            icon: Icon(Icons.groups_outlined),
            selectedIcon: Icon(Icons.groups),
            label: 'Сообщество',
          ),
          NavigationDestination(
            icon: Icon(
              widget.session.isAuthenticated
                  ? Icons.person_outline
                  : Icons.login_outlined,
            ),
            selectedIcon: Icon(
              widget.session.isAuthenticated ? Icons.person : Icons.login,
            ),
            label: widget.session.isAuthenticated ? 'Профиль' : 'Войти',
          ),
        ],
      ),
    );
  }
}

class PublicHomeTab extends StatefulWidget {
  const PublicHomeTab({
    super.key,
    required this.onOpenAudioGuides,
    required this.onOpenCommunity,
  });

  final VoidCallback onOpenAudioGuides;
  final VoidCallback onOpenCommunity;

  @override
  State<PublicHomeTab> createState() => _PublicHomeTabState();
}

class _PublicHomeTabState extends State<PublicHomeTab> {
  late Future<Map<String, dynamic>> _future = _load();

  Future<Map<String, dynamic>> _load({bool refresh = false}) async {
    final payload = await CachedApi.instance.get(
      '/mobile/home',
      forceRefresh: refresh,
    );
    return Map<String, dynamic>.from(payload as Map);
  }

  void _openAudioGuides() => widget.onOpenAudioGuides();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Московский паломник'),
            Text(
              'Путеводитель доступен без регистрации',
              style: TextStyle(fontSize: 11, fontWeight: FontWeight.w400),
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Аудиогиды',
            onPressed: _openAudioGuides,
            icon: const Icon(Icons.headphones_outlined),
          ),
          IconButton(
            tooltip: 'Сообщество',
            onPressed: widget.onOpenCommunity,
            icon: const Icon(Icons.groups_outlined),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          final future = _load(refresh: true);
          setState(() => _future = future);
          await future;
        },
        child: FutureBuilder<Map<String, dynamic>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return ListView(
                children: const [
                  SizedBox(height: 320),
                  Center(child: CircularProgressIndicator()),
                ],
              );
            }
            if (snapshot.hasError) {
              return ListView(
                children: [
                  SizedBox(
                    height: 500,
                    child: ErrorPane(
                      error: snapshot.error!,
                      onRetry: () => setState(
                        () => _future = _load(refresh: true),
                      ),
                    ),
                  ),
                ],
              );
            }

            final data = snapshot.data!;
            final objects = mapList(data['objects']);
            final routes = mapList(data['routes']);
            final events = mapList(data['events']);

            return ListView(
              padding: const EdgeInsets.fromLTRB(18, 12, 18, 32),
              children: [
                Container(
                  padding: const EdgeInsets.all(22),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(26),
                    gradient: const LinearGradient(
                      colors: [AppTheme.green, Color(0xFF18322A)],
                    ),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(Icons.church, color: AppTheme.gold, size: 40),
                      const SizedBox(height: 16),
                      const Text(
                        'Святые места становятся ближе',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 25,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 10),
                      const Text(
                        'Храмы, монастыри, маршруты, события, аудиогиды и материалы сообщества доступны без обязательной регистрации.',
                        style: TextStyle(color: Colors.white70, height: 1.5),
                      ),
                      const SizedBox(height: 18),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          FilledButton.icon(
                            style: FilledButton.styleFrom(
                              backgroundColor: AppTheme.gold,
                            ),
                            onPressed: _openAudioGuides,
                            icon: const Icon(Icons.headphones),
                            label: const Text('Аудиогиды'),
                          ),
                          OutlinedButton.icon(
                            style: OutlinedButton.styleFrom(
                              foregroundColor: Colors.white,
                              side: const BorderSide(color: Colors.white54),
                            ),
                            onPressed: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const AudioRoutesScreen(),
                              ),
                            ),
                            icon: const Icon(Icons.route),
                            label: const Text('Маршруты'),
                          ),
                          OutlinedButton.icon(
                            style: OutlinedButton.styleFrom(
                              foregroundColor: Colors.white,
                              side: const BorderSide(color: Colors.white54),
                            ),
                            onPressed: widget.onOpenCommunity,
                            icon: const Icon(Icons.groups),
                            label: const Text('Сообщество'),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SectionTitle(title: 'Рекомендуемые святыни'),
                if (objects.isEmpty)
                  const EmptyCard(text: 'Объекты пока не опубликованы.')
                else
                  SizedBox(
                    height: 285,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: objects.length,
                      separatorBuilder: (_, __) => const SizedBox(width: 12),
                      itemBuilder: (context, index) => SizedBox(
                        width: 280,
                        child: AudioObjectPreviewCard(item: objects[index]),
                      ),
                    ),
                  ),
                const SectionTitle(title: 'Ближайшие события'),
                if (events.isEmpty)
                  const EmptyCard(text: 'События пока не опубликованы.'),
                ...events.take(4).map((event) => EventTile(item: event)),
                const SectionTitle(title: 'Паломнические маршруты'),
                if (routes.isEmpty)
                  const EmptyCard(text: 'Маршруты пока не опубликованы.'),
                ...routes.take(4).map(
                      (route) => BasicCard(
                        title: '${route['title'] ?? 'Маршрут'}',
                        subtitle: '${route['short_description'] ?? ''}',
                        icon: Icons.signpost_outlined,
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => AudioRouteDetailScreen(
                              slug: '${route['slug']}',
                            ),
                          ),
                        ),
                      ),
                    ),
                const SizedBox(height: 18),
                const Card(
                  child: Padding(
                    padding: EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Личный кабинет — по желанию',
                          style: TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 18,
                          ),
                        ),
                        SizedBox(height: 7),
                        Text(
                          'Регистрация нужна только для избранного, бронирований, публикаций, отметок посещения и участия в совместных поездках. Вход находится в нижнем разделе «Войти».',
                        ),
                      ],
                    ),
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
