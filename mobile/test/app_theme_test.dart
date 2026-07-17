import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:moscow_pilgrim/src/theme/app_theme.dart';

void main() {
  testWidgets('application theme renders the pilgrim shell', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light(),
        home: const Scaffold(
          body: Center(child: Text('Московский паломник')),
        ),
      ),
    );

    expect(find.text('Московский паломник'), findsOneWidget);
    expect(AppTheme.light().colorScheme.primary, AppTheme.green);
  });

  test('dark theme is configured', () {
    expect(AppTheme.dark().brightness, Brightness.dark);
  });
}
