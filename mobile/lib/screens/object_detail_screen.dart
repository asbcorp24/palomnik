import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:moscow_pilgrim/core/api_client.dart';
import 'package:moscow_pilgrim/core/app_theme.dart';
import 'package:moscow_pilgrim/core/session_controller.dart';
import 'package:moscow_pilgrim/models/models.dart';
import 'package:moscow_pilgrim/screens/auth_screen.dart';
import 'package:moscow_pilgrim/widgets/common.dart';
import 'package:url_launcher/url_launcher.dart';

class ObjectDetailScreen extends StatefulWidget {
  const ObjectDetailScreen({required this.slug, super.key});

  final String slug;

  @override
  State<ObjectDetailScreen> createState() => _ObjectDetailScreenState();
}

class _ObjectDetailScreenState extends State<ObjectDetailScreen> {
  PilgrimageObjectModel? _object;
  bool _loading = true;
  bool _actionBusy = false;
  String? _error;

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
      final response = await ApiClient.instance.getJson('/objects/${widget.slug}');
      final data = response['data'];
      if (data is! Map) throw const ApiException('Карточка объекта не найдена.');
      if (!mounted) return;
      setState(() => _object = PilgrimageObjectModel.fromJson(Map<String, dynamic>.from(data)));
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<bool> _ensureAuth() async {
    if (SessionController.instance.isAuthenticated) return true;
    await openPage(context, const AuthScreen());
    return SessionController.instance.isAuthenticated;
  }

  Future<void> _toggleFavorite() async {
    if (!await _ensureAuth() || _object == null) return;
    setState(() => _actionBusy = true);
    try {
      final response = await ApiClient.instance.postJson('/mobile/favorites/${_object!.id}');
      if (!mounted) return;
      showAppSnackBar(
        context,
        response['is_favorite'] == true ? 'Добавлено в избранное' : 'Удалено из избранного',
      );
    } on ApiException catch (error) {
      if (mounted) showAppSnackBar(context, error.message, error: true);
    } finally {
      if (mounted) setState(() => _actionBusy = false);
    }
  }

  Future<void> _markVisit() async {
    if (!await _ensureAuth() || _object == null) return;
    setState(() => _actionBusy = true);
    try {
      Position? position;
      final serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (serviceEnabled) {
        var permission = await Geolocator.checkPermission();
        if (permission == LocationPermission.denied) {
          permission = await Geolocator.requestPermission();
        }
        if (permission == LocationPermission.always || permission == LocationPermission.whileInUse) {
          position = await Geolocator.getCurrentPosition(
            locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
          );
        }
      }

      await ApiClient.instance.postJson(
        '/mobile/visits',
        data: {
          'pilgrimage_object_id': _object!.id,
          if (position != null) 'latitude': position.latitude,
          if (position != null) 'longitude': position.longitude,
        },
      );
      if (mounted) showAppSnackBar(context, 'Посещение отправлено на подтверждение.');
    } on ApiException catch (error) {
      if (mounted) showAppSnackBar(context, error.message, error: true);
    } finally {
      if (mounted) setState(() => _actionBusy = false);
    }
  }

  Future<void> _review() async {
    if (!await _ensureAuth() || _object == null || !mounted) return;
    var rating = 5;
    final controller = TextEditingController();
    final submitted = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Оставить отзыв'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Wrap(
                children: List.generate(
                  5,
                  (index) => IconButton(
                    onPressed: () => setDialogState(() => rating = index + 1),
                    icon: Icon(index < rating ? Icons.star_rounded : Icons.star_border_rounded),
                    color: AppTheme.gold,
                  ),
                ),
              ),
              TextField(
                controller: controller,
                maxLines: 5,
                minLines: 3,
                decoration: const InputDecoration(hintText: 'Расскажите о посещении'),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Отмена')),
            FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Отправить')),
          ],
        ),
      ),
    );

    if (submitted != true) {
      controller.dispose();
      return;
    }

    setState(() => _actionBusy = true);
    try {
      await ApiClient.instance.postJson(
        '/mobile/reviews',
        data: {
          'pilgrimage_object_id': _object!.id,
          'rating': rating,
          'body': controller.text.trim(),
        },
      );
      if (mounted) showAppSnackBar(context, 'Отзыв отправлен на модерацию.');
    } on ApiException catch (error) {
      if (mounted) showAppSnackBar(context, error.message, error: true);
    } finally {
      controller.dispose();
      if (mounted) setState(() => _actionBusy = false);
    }
  }

  Future<void> _openDirections() async {
    final object = _object;
    if (object?.latitude == null || object?.longitude == null) return;
    final uri = Uri.parse(
      'https://yandex.ru/maps/?rtext=~${object!.latitude},${object.longitude}&rtt=auto',
    );
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Карточка объекта'),
        actions: [
          IconButton(
            onPressed: _actionBusy ? null : _toggleFavorite,
            icon: const Icon(Icons.favorite_border),
            tooltip: 'Избранное',
          ),
        ],
      ),
      body: _loading
          ? const LoadingView()
          : _error != null
              ? ErrorView(message: _error!, onRetry: _load)
              : _buildObject(),
    );
  }

  Widget _buildObject() {
    final object = _object!;
    return ListView(
      padding: const EdgeInsets.only(bottom: 36),
      children: [
        AspectRatio(
          aspectRatio: 16 / 10,
          child: object.coverUrl != null
              ? CachedNetworkImage(
                  imageUrl: object.coverUrl!,
                  fit: BoxFit.cover,
                  errorWidget: (_, _, _) => _coverPlaceholder(),
                )
              : _coverPlaceholder(),
        ),
        Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (object.typeName != null) StatusChip(object.typeName!),
              const SizedBox(height: 14),
              Text(
                object.name,
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      color: AppTheme.green,
                      fontWeight: FontWeight.w800,
                    ),
              ),
              const SizedBox(height: 12),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.location_on_outlined, color: AppTheme.gold),
                  const SizedBox(width: 8),
                  Expanded(child: Text(object.address ?? 'Адрес уточняется')),
                ],
              ),
              const SizedBox(height: 20),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  FilledButton.icon(
                    onPressed: object.latitude != null && object.longitude != null ? _openDirections : null,
                    icon: const Icon(Icons.directions),
                    label: const Text('Маршрут'),
                  ),
                  OutlinedButton.icon(
                    onPressed: _actionBusy ? null : _markVisit,
                    icon: const Icon(Icons.where_to_vote_outlined),
                    label: const Text('Я посетил'),
                  ),
                  OutlinedButton.icon(
                    onPressed: _actionBusy ? null : _review,
                    icon: const Icon(Icons.rate_review_outlined),
                    label: const Text('Отзыв'),
                  ),
                ],
              ),
              if (object.shortDescription != null) ...[
                const SizedBox(height: 28),
                Text(object.shortDescription!, style: Theme.of(context).textTheme.titleMedium),
              ],
              if (object.description != null) ...[
                const SizedBox(height: 28),
                const PageSectionTitle(title: 'Описание'),
                const SizedBox(height: 12),
                Text(object.description!, style: const TextStyle(height: 1.55)),
              ],
              if (object.history != null) ...[
                const SizedBox(height: 28),
                const PageSectionTitle(title: 'История'),
                const SizedBox(height: 12),
                Text(object.history!, style: const TextStyle(height: 1.55)),
              ],
              if (object.sanctities.isNotEmpty) ...[
                const SizedBox(height: 28),
                const PageSectionTitle(title: 'Святыни'),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: object.sanctities.map(StatusChip.new).toList(),
                ),
              ],
              if (object.schedule != null) ...[
                const SizedBox(height: 28),
                const PageSectionTitle(title: 'Расписание богослужений'),
                const SizedBox(height: 12),
                _infoCard(Icons.schedule, object.schedule!),
              ],
              if (object.parking != null || object.accessibility != null) ...[
                const SizedBox(height: 28),
                const PageSectionTitle(title: 'Удобства и доступность'),
                const SizedBox(height: 12),
                if (object.parking != null) _infoCard(Icons.local_parking, object.parking!),
                if (object.accessibility != null) ...[
                  const SizedBox(height: 10),
                  _infoCard(Icons.accessible, object.accessibility!),
                ],
              ],
              if (object.phone != null || object.email != null || object.website != null) ...[
                const SizedBox(height: 28),
                const PageSectionTitle(title: 'Контакты'),
                const SizedBox(height: 12),
                Card(
                  child: Column(
                    children: [
                      if (object.phone != null)
                        ListTile(
                          leading: const Icon(Icons.phone_outlined),
                          title: Text(object.phone!),
                          onTap: () => launchUrl(Uri.parse('tel:${object.phone}')),
                        ),
                      if (object.email != null)
                        ListTile(
                          leading: const Icon(Icons.email_outlined),
                          title: Text(object.email!),
                          onTap: () => launchUrl(Uri.parse('mailto:${object.email}')),
                        ),
                      if (object.website != null)
                        ListTile(
                          leading: const Icon(Icons.language),
                          title: Text(object.website!),
                          onTap: () => launchUrl(Uri.parse(object.website!), mode: LaunchMode.externalApplication),
                        ),
                    ],
                  ),
                ),
              ],
              if (object.media.where((item) => item.type == 'image' && item.url != null).isNotEmpty) ...[
                const SizedBox(height: 28),
                const PageSectionTitle(title: 'Фотографии'),
                const SizedBox(height: 12),
                SizedBox(
                  height: 180,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: object.media.where((item) => item.type == 'image' && item.url != null).length,
                    separatorBuilder: (_, _) => const SizedBox(width: 10),
                    itemBuilder: (context, index) {
                      final image = object.media.where((item) => item.type == 'image' && item.url != null).elementAt(index);
                      return ClipRRect(
                        borderRadius: BorderRadius.circular(18),
                        child: CachedNetworkImage(imageUrl: image.url!, width: 250, fit: BoxFit.cover),
                      );
                    },
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }

  Widget _coverPlaceholder() => Container(
        color: AppTheme.cream,
        alignment: Alignment.center,
        child: const Icon(Icons.church_rounded, size: 70, color: AppTheme.gold),
      );

  Widget _infoCard(IconData icon, String text) => Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, color: AppTheme.gold),
              const SizedBox(width: 12),
              Expanded(child: Text(text, style: const TextStyle(height: 1.45))),
            ],
          ),
        ),
      );
}
