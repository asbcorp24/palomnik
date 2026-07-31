import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

IconData pilgrimageObjectIcon(Map<String, dynamic> object) {
  final type = object['type'];
  final typeMap = type is Map
      ? Map<String, dynamic>.from(type)
      : const <String, dynamic>{};
  final value =
      '${typeMap['name'] ?? typeMap['slug'] ?? type ?? object['type_slug'] ?? ''}'
          .toLowerCase();

  if (value.contains('монастыр') || value.contains('monastery')) {
    return Icons.account_balance;
  }
  if (value.contains('часовн') || value.contains('chapel')) {
    return Icons.church_outlined;
  }
  if (value.contains('источник') ||
      value.contains('купель') ||
      value.contains('spring')) {
    return Icons.water_drop;
  }
  if (value.contains('храм') ||
      value.contains('церков') ||
      value.contains('собор') ||
      value.contains('church') ||
      value.contains('cathedral')) {
    return Icons.church;
  }

  return Icons.location_city;
}

IconData pointOfInterestIcon(Map<String, dynamic> point) {
  return switch ('${point['category'] ?? ''}') {
    'parking' => Icons.local_parking,
    'cafe' => Icons.local_cafe,
    'hotel' => Icons.hotel,
    'spring' => Icons.water_drop,
    'chapel' => Icons.church_outlined,
    _ => Icons.star,
  };
}

Color pilgrimageMarkerColor(
  Map<String, dynamic> item, {
  Color fallback = AppTheme.gold,
}) {
  final type = item['type'];
  final typeMap = type is Map
      ? Map<String, dynamic>.from(type)
      : const <String, dynamic>{};
  final value = typeMap['marker_color'] ?? item['marker_color'];
  final normalized = '$value'.replaceFirst('#', '').trim();
  final parsed = int.tryParse('FF$normalized', radix: 16);
  return normalized.length == 6 && parsed != null ? Color(parsed) : fallback;
}

class PilgrimMapMarker extends StatelessWidget {
  const PilgrimMapMarker({
    super.key,
    required this.color,
    required this.icon,
    required this.onTap,
    this.primary = false,
  });

  final Color color;
  final IconData icon;
  final VoidCallback onTap;
  final bool primary;

  @override
  Widget build(BuildContext context) {
    final size = primary ? 46.0 : 36.0;
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          color: color,
          shape: BoxShape.circle,
          border: Border.all(color: Colors.white, width: primary ? 3 : 2.5),
          boxShadow: const [
            BoxShadow(
              color: Colors.black26,
              blurRadius: 8,
              offset: Offset(0, 3),
            ),
          ],
        ),
        child: Icon(icon, color: Colors.white, size: primary ? 23 : 18),
      ),
    );
  }
}
