import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'src/app.dart';
import 'src/core/session_controller.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final session = SessionController();
  await session.restore();

  runApp(MoscowPilgrimApp(session: session));
}
