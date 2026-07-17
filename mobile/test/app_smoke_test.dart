import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:moscow_pilgrim/src/app.dart';
import 'package:moscow_pilgrim/src/core/session_controller.dart';

void main() {
  testWidgets('application shows restore indicator while session is loading', (tester) async {
    await tester.pumpWidget(MoscowPilgrimApp(session: SessionController()));

    expect(find.byType(MaterialApp), findsOneWidget);
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
  });
}
