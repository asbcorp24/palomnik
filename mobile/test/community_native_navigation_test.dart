import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('community publications and joint trips stay inside Flutter', () {
    final source = File('lib/src/screens/community_hub.dart').readAsStringSync();

    expect(source, contains('PostDetailScreen(slug:'));
    expect(source, contains('PublicTogetherDetailScreen('));
    expect(source, isNot(contains('_openSite(')));
    expect(source, isNot(contains('LaunchMode.externalApplication')));
    expect(source, isNot(contains("'/community/together/\${item['slug']}'")));
  });
}
