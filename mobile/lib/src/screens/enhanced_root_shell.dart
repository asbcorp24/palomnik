import 'package:flutter/material.dart';

import '../core/session_controller.dart';
import '../theme/app_theme.dart';
import 'content_features.dart';
import 'root_shell.dart';
import 'user_features.dart';

class EnhancedRootShell extends StatefulWidget {
  const EnhancedRootShell({super.key, required this.session});

  final SessionController session;

  @override
  State<EnhancedRootShell> createState() => _EnhancedRootShellState();
}

class _EnhancedRootShellState extends State<EnhancedRootShell> {
  int _index = 0;

  late final List<Widget> _pages = [
    HomeTab(session: widget.session),
    const CatalogTab(),
    const MapTab(),
    const CalendarTab(),
    EnhancedProfileTab(session: widget.session),
  ];

  @override
  Widget build(BuildContext context) => Scaffold(
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

class EnhancedProfileTab extends StatelessWidget {
  const EnhancedProfileTab({super.key, required this.session});

  final SessionController session;

  @override
  Widget build(BuildContext context) {
    final user = session.user ?? const <String, dynamic>{};
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
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('${user['name'] ?? 'Паломник'}', style: Theme.of(context).textTheme.titleLarge),
                        const SizedBox(height: 4),
                        Text('${user['email'] ?? ''}', style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
                        if (user['is_verified_organizer'] == true)
                          const Padding(
                            padding: EdgeInsets.only(top: 5),
                            child: Text('Проверенный организатор ✓', style: TextStyle(color: AppTheme.green, fontWeight: FontWeight.w600)),
                          ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          _section(context, 'Паломничество'),
          ProfileAction(icon: Icons.favorite_outline, title: 'Избранное', onTap: () => _open(context, const FavoritesScreen())),
          ProfileAction(icon: Icons.confirmation_number_outlined, title: 'Бронирования и QR-билеты', onTap: () => _open(context, const BookingsScreen())),
          ProfileAction(icon: Icons.emoji_events_outlined, title: 'Достижения и статистика', onTap: () => _open(context, const ProfileStatsScreen())),
          ProfileAction(icon: Icons.route_outlined, title: 'Мои маршруты', onTap: () => _open(context, const RoutePlansScreen())),
          ProfileAction(icon: Icons.add_road, title: 'Создать персональный маршрут', onTap: () => _open(context, const RouteBuilderScreen())),
          ProfileAction(icon: Icons.groups_outlined, title: 'Совместные паломничества', onTap: () => _open(context, const TogetherScreen())),
          _section(context, 'Мои публикации'),
          ProfileAction(icon: Icons.edit_note, title: 'Путевые заметки', onTap: () => _open(context, const MyPostsScreen())),
          ProfileAction(icon: Icons.photo_library_outlined, title: 'Фото и видео', onTap: () => _open(context, const MediaScreen())),
          _section(context, 'Аккаунт'),
          ProfileAction(icon: Icons.notifications_none, title: 'Уведомления', onTap: () => _open(context, const NotificationsScreen())),
          ProfileAction(icon: Icons.settings_outlined, title: 'Настройки профиля', onTap: () => _open(context, ProfileSettingsScreen(session: session))),
          ProfileAction(icon: Icons.logout, title: 'Выйти', color: Colors.red, onTap: session.logout),
        ],
      ),
    );
  }

  Widget _section(BuildContext context, String title) => Padding(
        padding: const EdgeInsets.fromLTRB(4, 16, 4, 8),
        child: Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(color: AppTheme.green, fontWeight: FontWeight.w700)),
      );

  void _open(BuildContext context, Widget page) {
    Navigator.push(context, MaterialPageRoute(builder: (_) => page));
  }
}
