import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api_client.dart';
import '../core/session_controller.dart';
import '../data/cached_api.dart';
import '../theme/app_theme.dart';
import 'advanced_features.dart';
import 'app_sections.dart';
import 'content_features.dart';
import 'pilgrimage_photos.dart';

class CommunityHubTab extends StatelessWidget {
  const CommunityHubTab({super.key, required this.session});

  final SessionController session;

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
                  'Читайте путевые заметки, смотрите фотографии и находите попутчиков. Публичные материалы доступны без регистрации.',
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
            subtitle: 'Открытые совместные поездки и поиск попутчиков',
            onTap: () => _open(
              context,
              session.isAuthenticated
                  ? const TogetherScreen()
                  : const PublicTogetherScreen(),
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
                      'Войдите в профиль, чтобы публиковать заметки и фотографии, создавать поездки, вступать в группы и писать сообщения.',
                    ),
                    const SizedBox(height: 14),
                    FilledButton.icon(
                      onPressed: () => DefaultTabController.of(context),
                      icon: const Icon(Icons.login),
                      label: const Text('Вход доступен в разделе «Профиль»'),
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
  Widget build(BuildContext context) {
    return FutureListPage(
      title: 'Путевые заметки',
      loader: _load,
      builder: (item) => BasicCard(
        title: '${item['title'] ?? 'Путевая заметка'}',
        subtitle: '${item['excerpt'] ?? ''}',
        icon: Icons.article_outlined,
        onTap: () => _openSite('/community/${item['slug']}'),
      ),
    );
  }
}

class PublicTogetherScreen extends StatelessWidget {
  const PublicTogetherScreen({super.key});

  Future<List<Map<String, dynamic>>> _load() async {
    final payload = await CachedApi.instance.get(
      '/mobile/together',
      forceRefresh: true,
    ) as Map;
    return mapList(payload['data']);
  }

  @override
  Widget build(BuildContext context) {
    return FutureListPage(
      title: 'Паломничество вместе',
      loader: _load,
      header: const Card(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: Text(
            'Список поездок доступен без регистрации. Для вступления, создания поездки и общения войдите в профиль.',
          ),
        ),
      ),
      builder: (item) => BasicCard(
        title: '${item['title'] ?? 'Совместное паломничество'}',
        subtitle:
            '${item['meeting_place'] ?? ''}\n${formatDate(item['starts_at'])}\n${item['participants_count'] ?? item['approved_members_count'] ?? 1}/${item['max_participants'] ?? '∞'} участников',
        icon: Icons.groups_outlined,
        onTap: () => _openSite('/community/together/${item['slug']}'),
      ),
    );
  }
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
            return const Center(
              child: Padding(
                padding: EdgeInsets.all(28),
                child: Text(
                  'Опубликованных фотографий пока нет.',
                  textAlign: TextAlign.center,
                ),
              ),
            );
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
                final author = photo['author'] is Map
                    ? Map<String, dynamic>.from(photo['author'] as Map)
                    : const <String, dynamic>{};
                final route = photo['route'] is Map
                    ? Map<String, dynamic>.from(photo['route'] as Map)
                    : const <String, dynamic>{};

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
                child: Image.network(
                  '${photo['url'] ?? ''}',
                  fit: BoxFit.contain,
                ),
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
  Widget build(BuildContext context) {
    return Card(
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
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading(this.title);

  final String title;

  @override
  Widget build(BuildContext context) {
    return Padding(
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
}

Future<void> _openSite(String path) async {
  final uri = Uri.parse('${ApiClient.siteBaseUrl}$path');
  await launchUrl(uri, mode: LaunchMode.externalApplication);
}
