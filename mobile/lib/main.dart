import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';

import 'src/app.dart';
import 'src/core/push_service.dart';
import 'src/core/session_controller.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

  final session = SessionController();
  await session.restore();

  runApp(MoscowPilgrimApp(session: session));
}
