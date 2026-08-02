class UserWorkspace {
  const UserWorkspace({
    required this.code,
    required this.label,
    required this.description,
    required this.url,
    required this.icon,
  });

  final String code;
  final String label;
  final String description;
  final String url;
  final String icon;

  factory UserWorkspace.fromMap(Map<dynamic, dynamic> value) {
    return UserWorkspace(
      code: '${value['code'] ?? ''}',
      label: '${value['label'] ?? 'Рабочий раздел'}',
      description: '${value['description'] ?? ''}',
      url: '${value['url'] ?? ''}',
      icon: '${value['icon'] ?? 'dashboard'}',
    );
  }
}

class UserAccess {
  const UserAccess({
    required this.roleCode,
    required this.roleLabel,
    required this.roleDescription,
    required this.emailVerified,
    required this.capabilities,
    required this.workspaces,
  });

  final String roleCode;
  final String roleLabel;
  final String roleDescription;
  final bool emailVerified;
  final Map<String, bool> capabilities;
  final List<UserWorkspace> workspaces;

  bool get isStaff => roleCode != 'pilgrim' && workspaces.isNotEmpty;

  bool can(String capability) => capabilities[capability] == true;

  factory UserAccess.fromUser(
    Map<String, dynamic> user, {
    String siteBaseUrl = '',
  }) {
    final roleCode = '${user['role'] ?? 'pilgrim'}';
    final capabilitySource = user['capabilities'];
    final capabilities = <String, bool>{};

    if (capabilitySource is Map) {
      for (final entry in capabilitySource.entries) {
        capabilities['${entry.key}'] = entry.value == true;
      }
    } else {
      capabilities.addAll(_legacyCapabilities(roleCode));
    }

    final workspaces = <UserWorkspace>[];
    final workspaceSource = user['workspaces'];
    final hasServerWorkspacePayload = workspaceSource is List;
    if (workspaceSource is List) {
      for (final value in workspaceSource) {
        if (value is Map) {
          final workspace = UserWorkspace.fromMap(value);
          if (workspace.url.isNotEmpty) workspaces.add(workspace);
        }
      }
    }

    if (!hasServerWorkspacePayload &&
        workspaces.isEmpty &&
        siteBaseUrl.isNotEmpty) {
      workspaces.addAll(
        _legacyWorkspaces(
          roleCode,
          siteBaseUrl.replaceFirst(RegExp(r'/+$'), ''),
        ),
      );
    }

    return UserAccess(
      roleCode: roleCode,
      roleLabel: '${user['role_label'] ?? _roleLabels[roleCode] ?? roleCode}',
      roleDescription:
          '${user['role_description'] ?? _roleDescriptions[roleCode] ?? ''}',
      emailVerified: user['email_verified'] == true,
      capabilities: Map.unmodifiable(capabilities),
      workspaces: List.unmodifiable(workspaces),
    );
  }

  static const _roleLabels = <String, String>{
    'pilgrim': 'Паломник',
    'object_editor': 'Редактор объектов',
    'service_manager': 'Паломническая служба',
    'moderator': 'Модератор',
    'admin': 'Администратор',
    'super_admin': 'Главный администратор',
  };

  static const _roleDescriptions = <String, String>{
    'pilgrim':
        'Личный кабинет паломника, маршруты, бронирования, отзывы и публикации.',
    'object_editor':
        'Работа только с закреплёнными храмами и монастырями через заявки на изменение.',
    'service_manager':
        'Маршруты, поездки, CRM заявок, участники и проверка QR-билетов.',
    'moderator':
        'Проверка публикаций, фотографий, жалоб и изменений от представителей храмов.',
    'admin':
        'Управление каталогом, маршрутами, CRM, модерацией и обычными пользователями.',
    'super_admin':
        'Полный доступ к платформе, ролям пользователей и системным настройкам.',
  };

  static Map<String, bool> _legacyCapabilities(String role) {
    final service = const {
      'object_editor',
      'service_manager',
      'admin',
      'super_admin',
    }.contains(role);
    final backoffice = const {
      'service_manager',
      'moderator',
      'admin',
      'super_admin',
    }.contains(role);

    return {
      'service_access': service,
      'backoffice_access': backoffice,
      'assigned_objects_manage': service,
      'bookings_manage': const {
        'service_manager',
        'admin',
        'super_admin',
      }.contains(role),
      'moderation_manage': const {
        'moderator',
        'admin',
        'super_admin',
      }.contains(role),
      'content_manage': const {'admin', 'super_admin'}.contains(role),
      'users_view': const {'admin', 'super_admin'}.contains(role),
      'users_manage': const {'admin', 'super_admin'}.contains(role),
      'activity_view': const {'admin', 'super_admin'}.contains(role),
      'system_manage': role == 'super_admin',
      'routes_manage': const {
        'service_manager',
        'admin',
        'super_admin',
      }.contains(role),
      'trips_manage': const {
        'service_manager',
        'admin',
        'super_admin',
      }.contains(role),
    };
  }

  static List<UserWorkspace> _legacyWorkspaces(
    String role,
    String siteBaseUrl,
  ) {
    final items = <UserWorkspace>[];
    final capabilities = _legacyCapabilities(role);

    if (capabilities['service_access'] == true) {
      items.add(
        UserWorkspace(
          code: 'service',
          label: role == 'object_editor'
              ? 'Кабинет представителя объекта'
              : 'Работа с закреплёнными объектами',
          description:
              'Сведения, материалы и заявки на изменение закреплённых объектов.',
          url: '$siteBaseUrl/service',
          icon: 'church',
        ),
      );
    }

    if (capabilities['backoffice_access'] == true) {
      items.add(
        UserWorkspace(
          code: 'backoffice',
          label: switch (role) {
            'service_manager' => 'Панель паломнической службы',
            'moderator' => 'Панель модератора',
            'super_admin' => 'Панель главного администратора',
            _ => 'Панель управления',
          },
          description: switch (role) {
            'service_manager' =>
              'Маршруты, поездки, CRM заявок и проверка QR-билетов.',
            'moderator' =>
              'Публикации, фотографии, жалобы и очереди модерации.',
            'super_admin' =>
              'Все разделы платформы, роли и системные настройки.',
            _ => 'Каталог, CRM, модерация, пользователи и журнал действий.',
          },
          url: '$siteBaseUrl/admin',
          icon: role == 'moderator' ? 'verified_user' : 'dashboard',
        ),
      );
    }

    return items;
  }
}
