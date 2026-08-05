import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('object catalog exposes all requested sorting modes', () {
    final source = File(
      'lib/src/screens/sorted_object_catalog.dart',
    ).readAsStringSync();

    expect(source, contains("'sort': _sort"));
    expect(source, contains("value: 'none'"));
    expect(source, contains("value: 'popular'"));
    expect(source, contains("value: 'reviews'"));
    expect(source, contains("Text('Без сортировки')"));
    expect(source, contains("Text('Популярные')"));
    expect(source, contains("Text('С отзывами')"));
  });
}
