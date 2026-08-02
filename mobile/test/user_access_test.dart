import 'package:flutter_test/flutter_test.dart';
import 'package:moscow_pilgrim/src/core/user_access.dart';

void main() {
  test('parses server role capabilities and workspaces', () {
    final access = UserAccess.fromUser({
      'role': 'service_manager',
      'role_label': 'Паломническая служба',
      'role_description': 'Маршруты, поездки и CRM.',
      'email_verified': true,
      'capabilities': {
        'backoffice_access': true,
        'service_access': true,
        'bookings_manage': true,
        'moderation_manage': false,
      },
      'workspaces': [
        {
          'code': 'backoffice',
          'label': 'Панель паломнической службы',
          'description': 'CRM и билеты',
          'url': 'https://palom.example/admin',
          'icon': 'dashboard',
        },
      ],
    });

    expect(access.roleCode, 'service_manager');
    expect(access.roleLabel, 'Паломническая служба');
    expect(access.emailVerified, isTrue);
    expect(access.isStaff, isTrue);
    expect(access.can('bookings_manage'), isTrue);
    expect(access.can('moderation_manage'), isFalse);
    expect(access.workspaces.single.code, 'backoffice');
  });

  test('builds legacy workspaces when old API payload has only role', () {
    final access = UserAccess.fromUser(
      {'role': 'moderator'},
      siteBaseUrl: 'https://palom.example/',
    );

    expect(access.roleLabel, 'Модератор');
    expect(access.can('moderation_manage'), isTrue);
    expect(access.can('bookings_manage'), isFalse);
    expect(access.workspaces, hasLength(1));
    expect(access.workspaces.single.url, 'https://palom.example/admin');
  });

  test('does not override an explicit empty server workspace list', () {
    final access = UserAccess.fromUser(
      {
        'role': 'moderator',
        'capabilities': {'moderation_manage': true},
        'workspaces': <Map<String, dynamic>>[],
      },
      siteBaseUrl: 'https://palom.example',
    );

    expect(access.can('moderation_manage'), isTrue);
    expect(access.workspaces, isEmpty);
    expect(access.isStaff, isFalse);
  });

  test('pilgrim remains a user without staff workspaces', () {
    final access = UserAccess.fromUser(
      {'role': 'pilgrim', 'email_verified': false},
      siteBaseUrl: 'https://palom.example',
    );

    expect(access.roleLabel, 'Паломник');
    expect(access.emailVerified, isFalse);
    expect(access.isStaff, isFalse);
    expect(access.workspaces, isEmpty);
  });
}
