import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import 'api_client.dart';

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
  } catch (_) {
    // Native Firebase configuration may not be installed in local builds.
  }
}

class PushService {
  PushService._();

  static final PushService instance = PushService._();
  final FlutterLocalNotificationsPlugin _local = FlutterLocalNotificationsPlugin();
  bool _initialized = false;
  String? _token;

  Future<void> initialize() async {
    if (_initialized) return;
    _initialized = true;

    try {
      await Firebase.initializeApp();
      FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

      const androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
      const iosSettings = DarwinInitializationSettings();
      await _local.initialize(const InitializationSettings(android: androidSettings, iOS: iosSettings));

      const channel = AndroidNotificationChannel(
        'pilgrim_notifications',
        'Московский паломник',
        description: 'События, поездки, сообщения и достижения',
        importance: Importance.high,
      );
      await _local
          .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
          ?.createNotificationChannel(channel);

      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission(alert: true, badge: true, sound: true);
      _token = await messaging.getToken();
      if (_token != null) await _register(_token!);
      messaging.onTokenRefresh.listen((token) async {
        _token = token;
        await _register(token);
      });

      FirebaseMessaging.onMessage.listen(_showForegroundNotification);
    } catch (_) {
      // The rest of the application remains fully usable before Firebase files are added.
    }
  }

  Future<void> unregister() async {
    if (_token == null) return;
    try {
      await ApiClient.instance.dio.delete('/mobile/push-devices', data: {'token': _token});
    } catch (_) {
      // Logout must not fail because push deregistration is temporarily unavailable.
    }
  }

  Future<void> _register(String token) async {
    await ApiClient.instance.dio.post('/mobile/push-devices', data: {
      'token': token,
      'platform': Platform.isIOS ? 'ios' : 'android',
      'device_name': Platform.operatingSystem,
    });
  }

  Future<void> _showForegroundNotification(RemoteMessage message) async {
    final notification = message.notification;
    if (notification == null) return;

    await _local.show(
      message.hashCode,
      notification.title,
      notification.body,
      const NotificationDetails(
        android: AndroidNotificationDetails(
          'pilgrim_notifications',
          'Московский паломник',
          channelDescription: 'События, поездки, сообщения и достижения',
          importance: Importance.high,
          priority: Priority.high,
          icon: '@mipmap/ic_launcher',
        ),
        iOS: DarwinNotificationDetails(),
      ),
      payload: message.data['url'],
    );
  }
}
