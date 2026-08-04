import 'package:flutter/material.dart';

import '../core/session_controller.dart';
import '../data/cached_api.dart';
import '../theme/app_theme.dart';
import 'advanced_features.dart';
import 'app_sections.dart';
import 'content_features.dart';
import 'pilgrimage_photos.dart';
import 'user_features.dart';

class CommunityHubTab extends StatelessWidget {
  const CommunityHubTab({
    super.key,
    required this.session,
    required this.onOpenProfile,
  });

  final SessionController session;
  final VoidCallback onOpenProfile;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Сообщество')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
        children: [
          Container(
            padding: const EdgeInsets.all(22),
            decoration: BoxDecoration(
              color: AppTheme.green,
              borderRadius: BorderRadius.circular(24),
            ),
            child: const Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.groups, color: AppTheme.gold, size: 42),
                SizedBox(height: 14),
                Text(
                  'Паломники рядом',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 24,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                SizedBox(height: 8),
                Text(
                  'Путевые заметки, фотографии и совместные поездки доступны для просмотра без регистрации.',
                  style: TextStyle(color: Colors.white70, height: 1.45),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          _CommunitySectionCard(
            icon: Icons.article_outlined,
            title: 'Публикации и путевые заметки',
            subtitle: 'Истории паломников и материалы сообщества',
            onTap: () => _open(context, const PublicCommunityPostsScreen()),
          ),
          _CommunitySectionCard(
            icon: Icons.photo_library_outlined,
            title: 'Фотографии паломников',
            subtitle: 'Опубликованные и проверенные фотографии поездок',
            onTap: () => _open(context, const PublicCommunityPhotosScreen()),
          ),
          _CommunitySectionCard(
            icon: Icons.diversity_3_outlined,
            title: 'Паломничество вместе',
            subtitle: 'Открытые поездки и поиск попутчиков',
            onTap: () => _open(
              context,
              session.isAuthenticated
                  ? const TogetherScreen()
                  : PublicTogetherScreen(onOpenProfile: onOpenProfile),
            ),
          ),
          if (session.isAuthenticated) ...[
            const _SectionHeading('Мои материалы'),
            _CommunitySectionCard(
              icon: Icons.edit_note,
              title: 'Мои путевые заметки',
              subtitle: 'Создание, редактирование и отправка на модерацию',
              onTap: () => _open(context, const MyPostsScreen()),
            ),
            _CommunitySectionCard(
              icon: Icons.add_a_photo_outlined,
              title: 'Мои паломнические фото',
              subtitle: 'Загрузка фотографий и управление публикацией',
              onTap: () => _open(context, const PilgrimagePhotosScreen()),
            ),
            _CommunitySectionCard(
              icon: Icons.manage_accounts_outlined,
              title: 'Мои совместные паломничества',
              subtitle: 'Участники, заявки и сообщения поездок',
              onTap: () => _open(context, const MyTogetherManagerScreen()),
            ),
          ] else ...[
            const SizedBox(height: 12),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Хотите участвовать?',
                      style: TextStyle(fontWeight: FontWeight.w700, fontSize: 18),
                    ),
                    const SizedBox(height: 7),
                    const Text(
                      'Войдите, чтобы публиковать материалы, вступать в поездки и общаться с участниками.',
                    ),
                    const SizedBox(height: 14),
                    FilledButton.icon(
                      onPressed: onOpenProfile,
                      icon: const Icon(Icons.login),
                      label: const Text('Войти или зарегистрироваться'),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  void _open(BuildContext context, Widget page) {
    Navigator.push(context, MaterialPageRoute(builder: (_) => page));
  }
}

class PublicCommunityPostsScreen extends StatelessWidget {
  const PublicCommunityPostsScreen({super.key});

  Future<List<Map<String, dynamic>>> _load() async {
    final payload = await CachedApi.instance.get(
      '/mobile/community',
      forceRefresh: true,
    ) as Map;
    return mapList(payload['data']);
  }

  @override
  Widget build(BuildContext context) => FutureListPage(
        title: 'Путевые заметки',
        loader: _load,
        builder: (item) => BasicCard(
          title: '${item['title'] ?? 'Путевая заметка'}',
          subtitle: '${item['excerpt'] ?? ''}',
          icon: Icons.article_outlined,
          onTap: item['slug'] == null
              ? null
              : () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => PostDetailScreen(slug: '${item['slug']}'),
                    ),
                  ),
        ),
      );
}

class PublicTogetherScreen extends StatelessWidget {
  const PublicTogetherScreen({
    super.key,
    required this.onOpenProfile,
  });

  final VoidCallback onOpenProfile;

  Future<List<Map<String, dynamic>>> _load() async {
    final payload = await CachedApi.instance.get(
      '/mobile/together',
      forceRefresh: true,
    ) as Map;
    return mapList(payload['data']);
  }

  @override
  Widget build(BuildContext context) => FutureListPage(
        title: 'Паломничество вместе',
        loader: _load,
        header: const Card(
          child: Padding(
            padding: EdgeInsets.all(16),
            child: Text(
              'Просмотр доступен без регистрации. Для вступления, создания поездки и общения войдите в профиль.',
            ),
          ),
        ),
        builder: (item) => BasicCard(
          title: '${item['title'] ?? 'Совместное паломничество'}',
          subtitle:
              '${item['meeting_place'] ?? ''}\n${formatDate(item['starts_at'])}\n${item['participants_count'] ?? item['approved_members_count'] ?? 1}/${item['max_participants'] ?? '∞'} участников',
          icon: Icons.groups_outlined,
          onTap: item['slug'] == null
              ? null
              : () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => PublicTogetherDetailScreen(
                        slug: '${item['slug']}',
                        onOpenProfile: onOpenProfile,
                      ),
                    ),
                  ),
        ),
      );
}

class PublicTogetherDetailScreen extends StatefulWidget {
  const PublicTogetherDetailScreen({
    super.key,
    required this.slug,
    required this.onOpenProfile,
  });

  final String slug;
  final VoidCallback onOpenProfile;

  @override
  State<PublicTogetherDetailScreen> createState() =>
      _PublicTogetherDetailScreenState();
}

class _PublicTogetherDetailScreenState
    extends State<PublicTogetherDetailScreen> {
  late Future<Map<String, dynamic>> _future = _load();

  Future<Map<String, dynamic>> _load({bool refresh = false}) async {
    final payload = await CachedApi.instance.get(
      '/mobile/together/${widget.slug}',
      forceRefresh: refresh,
    ) as Map;
    return Map<String, dynamic>.from(payload['data'] as Map);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Паломничество вместе')),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return ErrorPane(
                error: snapshot.error!,
                onRetry: () => setState(() => _future = _load(refresh: true)),
              );
            }

            final item = snapshot.data!;
            final organizer = _asMap(item['organizer']);
            final route = _asMap(item['route']);

            return RefreshIndicator(
              onRefresh: () async {
                final future = _load(refresh: true);
                setState(() => _future = future);
                await future;
              },
              child: ListView(
                padding: const EdgeInsets.all(18),
                children: [
                  Text(
                    '${item['title'] ?? 'Совместное паломничество'}',
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                          color: AppTheme.green,
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                  const SizedBox(height: 12),
                  if (organizer['name'] != null)
                    _InfoRow(
                      icon: Icons.person_outline,
                      text:
                          'Организатор: ${organizer['name']}${organizer['is_verified_organizer'] == true ? ' ✓' : ''}',
                    ),
                  _InfoRow(
                    icon: Icons.calendar_month_outlined,
                    text: formatDate(item['starts_at']),
                  ),
                  if ('${item['ends_at'] ?? ''}'.trim().isNotEmpty)
                    _InfoRow(
                      icon: Icons.event_available_outlined,
                      text: 'Окончание: ${formatDate(item['ends_at'])}',
                    ),
                  _InfoRow(
                    icon: Icons.location_on_outlined,
                    text: '${item['meeting_place'] ?? 'Место встречи уточняется'}',
                  ),
                  _InfoRow(
                    icon: Icons.groups_outlined,
                    text:
                        '${item['participants_count'] ?? 1}/${item['max_participants'] ?? '∞'} участников'
                        '${item['available_places'] != null ? ' · свободно ${item['available_places']}' : ''}',
                  ),
                  if ('${item['transport_mode'] ?? ''}'.trim().isNotEmpty)
                    _InfoRow(
                      icon: Icons.directions_bus_outlined,
                      text: _transportLabel('${item['transport_mode']}'),
                    ),
                  if ('${item['description'] ?? ''}'.trim().isNotEmpty) ...[
                    const _SectionHeading('План поездки'),
                    Text(
                      '${item['description']}',
                      style: const TextStyle(height: 1.6, fontSize: 16),
                    ),
                  ],
                  if (route.isNotEmpty) ...[
                    const _SectionHeading('Маршрут'),
                    Card(
                      child: ListTile(
                        leading: const Icon(Icons.route, color: AppTheme.green),
                        title: Text('${route['title'] ?? 'Паломнический маршрут'}'),
                        subtitle: Text('${route['short_description'] ?? ''}'),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: route['slug'] == null
                            ? null
                            : () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => RouteDetailScreen(
                                      slug: '${route['slug']}',
                                    ),
                                  ),
                                ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 24),
                  Card(
                    color: AppTheme.cream,
                    child: Padding(
                      padding: const EdgeInsets.all(18),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Хотите присоединиться?',
                            style: TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 8),
                          const Text(
                            'Войдите в приложение, чтобы отправить заявку, связаться с организатором и участвовать в обсуждении.',
                          ),
                          const SizedBox(height: 14),
                          FilledButton.icon(
                            onPressed: _openProfile,
                            icon: const Icon(Icons.login),
                            label: const Text('Войти или зарегистрироваться'),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      );

  void _openProfile() {
    Navigator.of(context).popUntil((route) => route.isFirst);
    widget.onOpenProfile();
  }

  String _transportLabel(String value) {
    switch (value) {
      case 'public':
      case 'masstransit':
      case 'multimodal':
        return 'Общественный транспорт';
      case 'auto':
      case 'car':
        return 'На автомобиле';
      case 'pedestrian':
      case 'walking':
        return 'Пешком';
      case 'bus':
        return 'Автобус';
      default:
        return value;
    }
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.icon,
    required this.text,
  });

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: AppTheme.gold),
            const SizedBox(width: 10),
            Expanded(child: Text(text)),
          ],
        ),
      );
}

class PublicCommunityPhotosScreen extends StatefulWidget {
  const PublicCommunityPhotosScreen({super.key});

  @override
  State<PublicCommunityPhotosScreen> createState() =>
      _PublicCommunityPhotosScreenState();
}

class _PublicCommunityPhotosScreenState
    extends State<PublicCommunityPhotosScreen> {
  late Future<List<Map<String, dynamic>>> _future = _load();

  Future<List<Map<String, dynamic>>> _load({bool refresh = false}) async {
    final payload = await CachedApi.instance.get(
      '/mobile/community/photos',
      forceRefresh: refresh,
    ) as Map;
    return mapList(payload['data']);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Фотографии паломников')),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return ErrorPane(
              error: snapshot.error!,
              onRetry: () => setState(() => _future = _load(refresh: true)),
            );
          }

          final photos = snapshot.data ?? const [];
          if (photos.isEmpty) {
            return const Center(child: Text('Опубликованных фотографий пока нет.'));
          }

          return RefreshIndicator(
            onRefresh: () async {
              final future = _load(refresh: true);
              setState(() => _future = future);
              await future;
            },
            child: GridView.builder(
              padding: const EdgeInsets.all(12),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                mainAxisSpacing: 10,
                crossAxisSpacing: 10,
                childAspectRatio: .72,
              ),
              itemCount: photos.length,
              itemBuilder: (context, index) {
                final photo = photos[index];
                final author = _asMap(photo['author']);
                final route = _asMap(photo['route']);
                return Card(
                  clipBehavior: Clip.antiAlias,
                  child: InkWell(
                    onTap: () => _showPhoto(context, photo),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Expanded(
                          child: Image.network(
                            '${photo['url'] ?? ''}',
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) => const Center(
                              child: Icon(Icons.broken_image_outlined, size: 42),
                            ),
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.all(10),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${photo['title'] ?? 'Паломническое фото'}',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(fontWeight: FontWeight.w700),
                              ),
                              if (author['name'] != null)
                                Text(
                                  '${author['name']}',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(fontSize: 12),
                                ),
                              if (route['title'] != null)
                                Text(
                                  '${route['title']}',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: AppTheme.green,
                                    fontSize: 11,
                                  ),
                                ),
                            ],
                          ),
                        ),
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

  void _showPhoto(BuildContext context, Map<String, dynamic> photo) {
    showDialog<void>(
      context: context,
      builder: (context) => Dialog(
        insetPadding: const EdgeInsets.all(12),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Flexible(
              child: InteractiveViewer(
                child: Image.network('${photo['url'] ?? ''}', fit: BoxFit.contain),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${photo['title'] ?? 'Паломническая фотография'}',
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    if ('${photo['description'] ?? ''}'.trim().isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text('${photo['description']}'),
                    ],
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CommunitySectionCard extends StatelessWidget {
  const _CommunitySectionCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
        margin: const EdgeInsets.only(bottom: 12),
        child: ListTile(
          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          leading: CircleAvatar(
            backgroundColor: AppTheme.cream,
            child: Icon(icon, color: AppTheme.green),
          ),
          title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
          subtitle: Padding(
            padding: const EdgeInsets.only(top: 5),
            child: Text(subtitle),
          ),
          trailing: const Icon(Icons.chevron_right),
          onTap: onTap,
        ),
      );
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading(this.title);

  final String title;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(4, 18, 4, 9),
        child: Text(
          title,
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                color: AppTheme.green,
                fontWeight: FontWeight.w700,
              ),
        ),
      );
}

Map<String, dynamic> _asMap(dynamic value) => value is Map
    ? Map<String, dynamic>.from(value)
    : const <String, dynamic>{};
