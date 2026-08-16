import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/session_controller.dart';
import '../core/user_access.dart';
import '../theme/app_theme.dart';
import 'advanced_features.dart';
import 'app_sections.dart';
import 'content_features.dart' hide RouteBuilderScreen;
import 'maplibre_map.dart';
import 'pilgrimage_photos.dart';
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
    const MapLibreMapTab(),
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
            NavigationDestination(
              icon: Icon(Icons.home_outlined),
              selectedIcon: Icon(Icons.home),
              label: 'Главная',
            ),
            NavigationDestination(
              icon: Icon(Icons.church_outlined),
              selectedIcon: Icon(Icons.church),
              label: 'Святыни',
            ),
            NavigationDestination(
              icon: Icon(Icons.map_outlined),
              selectedIcon: Icon(Icons.map),
              label: 'Карта',
            ),
            NavigationDestination(
              icon: Icon(Icons.calendar_month_outlined),
              selectedIcon: Icon(Icons.calendar_month),
              label: 'Календарь',
            ),
            NavigationDestination(
              icon: Icon(Icons.person_outline),
              selectedIcon: Icon(Icons.person),
              label: 'Профиль',
            ),
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
    final access = session.access;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Личный кабинет'),
        actions: [
          IconButton(
            tooltip: 'Обновить профиль и права',
            onPressed: () async {
              try {
                await session.refreshProfile();
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Профиль и права обновлены.')),
                  );
                }
              } catch (error) {
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(session.api.messageFrom(error)),
                      backgroundColor: Colors.red,
                    ),
                  );
                }
              }
            },
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          _ProfileHeader(user: user, access: access),
          if (access.workspaces.isNotEmpty) ...[
            _section(context, 'Рабочие разделы'),
            ...access.workspaces.map(
              (workspace) => _WorkspaceCard(
                workspace: workspace,
                onTap: () => _openWorkspace(context, workspace),
              ),
            ),
          ],
          _section(context, 'Паломничество'),
          ProfileAction(
            icon: Icons.edit_outlined,
            title: 'Редактировать профиль и аватар',
            onTap: () => _open(
              context,
              ProfileEditorScreen(session: session),
            ),
          ),
          ProfileAction(
            icon: Icons.favorite_outline,
            title: 'Избранное',
            onTap: () => _open(context, const FavoritesScreen()),
          ),
          ProfileAction(
            icon: Icons.offline_pin_outlined,
            title: 'Сохранено офлайн',
            onTap: () => _open(context, const OfflineObjectsScreen()),
          ),
          ProfileAction(
            icon: Icons.confirmation_number_outlined,
            title: 'Бронирования и QR-билеты',
            onTap: () => _open(context, const BookingsScreen()),
          ),
          ProfileAction(
            icon: Icons.where_to_vote_outlined,
            title: 'Отметить посещение',
            onTap: () => _open(context, const GeoVisitScreen()),
          ),
          ProfileAction(
            icon: Icons.history,
            title: 'Мои посещения и отзывы',
            onTap: () => _open(context, const ActivityHistoryScreen()),
          ),
          ProfileAction(
            icon: Icons.emoji_events_outlined,
            title: 'Достижения и статистика',
            onTap: () => _open(context, const ProfileStatsScreen()),
          ),
          ProfileAction(
            icon: Icons.route_outlined,
            title: 'Мои маршруты',
            onTap: () => _open(context, const RoutePlansScreen()),
          ),
          ProfileAction(
            icon: Icons.add_road,
            title: 'Создать персональный маршрут',
            onTap: () => _open(context, const RouteBuilderScreen()),
          ),
          ProfileAction(
            icon: Icons.groups_outlined,
            title: 'Совместные паломничества',
            onTap: () => _open(context, const TogetherScreen()),
          ),
          _section(context, 'Мои публикации'),
          ProfileAction(
            icon: Icons.edit_note,
            title: 'Путевые заметки',
            onTap: () => _open(context, const MyPostsScreen()),
          ),
          ProfileAction(
            icon: Icons.photo_library_outlined,
            title: 'Паломнические фото',
            onTap: () => _open(context, const PilgrimagePhotosScreen()),
          ),
          _section(context, 'Аккаунт'),
          ProfileAction(
            icon: Icons.notifications_none,
            title: 'Уведомления',
            onTap: () => _open(context, const NotificationsScreen()),
          ),
          ProfileAction(
            icon: Icons.settings_outlined,
            title: 'Настройки профиля',
            onTap: () => _open(
              context,
              ProfileSettingsScreen(session: session),
            ),
          ),
          ProfileAction(
            icon: Icons.logout,
            title: 'Выйти',
            color: Colors.red,
            onTap: session.logout,
          ),
        ],
      ),
    );
  }

  Widget _section(BuildContext context, String title) => Padding(
        padding: const EdgeInsets.fromLTRB(4, 16, 4, 8),
        child: Text(
          title,
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                color: AppTheme.green,
                fontWeight: FontWeight.w700,
              ),
        ),
      );

  void _open(BuildContext context, Widget page) {
    Navigator.push(context, MaterialPageRoute(builder: (_) => page));
  }

  Future<void> _openWorkspace(
    BuildContext context,
    UserWorkspace workspace,
  ) async {
    final uri = Uri.tryParse(workspace.url);
    if (uri == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Некорректный адрес рабочего раздела.')),
      );
      return;
    }

    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Не удалось открыть рабочий раздел.')),
      );
    }
  }
}

class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({required this.user, required this.access});

  final Map<String, dynamic> user;
  final UserAccess access;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  radius: 34,
                  backgroundColor: AppTheme.cream,
                  backgroundImage: user['avatar_url'] != null
                      ? NetworkImage('${user['avatar_url']}')
                      : null,
                  child: user['avatar_url'] == null
                      ? const Icon(
                          Icons.person,
                          size: 34,
                          color: AppTheme.green,
                        )
                      : null,
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${user['name'] ?? 'Паломник'}',
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '${user['email'] ?? ''}',
                        style: TextStyle(
                          color: colorScheme.onSurfaceVariant,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 8,
                        runSpacing: 6,
                        children: [
                          Chip(
                            avatar: Icon(
                              access.isStaff
                                  ? Icons.badge_outlined
                                  : Icons.person_outline,
                              size: 18,
                            ),
                            label: Text(access.roleLabel),
                          ),
                          Chip(
                            avatar: Icon(
                              access.emailVerified
                                  ? Icons.verified_outlined
                                  : Icons.mark_email_unread_outlined,
                              size: 18,
                            ),
                            label: Text(
                              access.emailVerified
                                  ? 'Email подтверждён'
                                  : 'Email не подтверждён',
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
            if (access.roleDescription.isNotEmpty) ...[
              const SizedBox(height: 14),
              Text(
                access.roleDescription,
                style: TextStyle(
                  color: colorScheme.onSurfaceVariant,
                  height: 1.4,
                ),
              ),
            ],
            if (user['is_verified_organizer'] == true) ...[
              const SizedBox(height: 10),
              const Row(
                children: [
                  Icon(Icons.verified, color: AppTheme.green, size: 19),
                  SizedBox(width: 7),
                  Text(
                    'Проверенный организатор',
                    style: TextStyle(
                      color: AppTheme.green,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _WorkspaceCard extends StatelessWidget {
  const _WorkspaceCard({required this.workspace, required this.onTap});

  final UserWorkspace workspace;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        leading: CircleAvatar(
          backgroundColor: AppTheme.cream,
          child: Icon(_workspaceIcon(workspace.icon), color: AppTheme.green),
        ),
        title: Text(
          workspace.label,
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
        subtitle: workspace.description.isEmpty
            ? null
            : Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(workspace.description),
              ),
        trailing: const Icon(Icons.open_in_new),
        onTap: onTap,
      ),
    );
  }

  IconData _workspaceIcon(String value) {
    return switch (value) {
      'church' => Icons.church_outlined,
      'verified_user' => Icons.verified_user_outlined,
      _ => Icons.dashboard_outlined,
    };
  }
}
