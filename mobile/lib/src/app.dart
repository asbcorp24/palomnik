import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'core/session_controller.dart';
import 'screens/auth_screen.dart';
import 'screens/root_shell.dart';
import 'theme/app_theme.dart';

class MoscowPilgrimApp extends StatelessWidget {
  const MoscowPilgrimApp({super.key, required this.session});

  final SessionController session;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: session,
      builder: (context, _) {
        final preferences = session.user?['preferences'];
        final theme = preferences is Map ? '${preferences['theme'] ?? 'system'}' : 'system';
        final themeMode = switch (theme) {
          'light' => ThemeMode.light,
          'dark' => ThemeMode.dark,
          _ => ThemeMode.system,
        };

        return MaterialApp(
          debugShowCheckedModeBanner: false,
          title: 'Московский паломник',
          theme: AppTheme.light(),
          darkTheme: AppTheme.dark(),
          themeMode: themeMode,
          locale: const Locale('ru'),
          supportedLocales: const [Locale('ru')],
          localizationsDelegates: const [
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          home: session.isRestoring
              ? const Scaffold(body: Center(child: CircularProgressIndicator()))
              : session.isAuthenticated
                  ? RootShell(session: session)
                  : AuthScreen(session: session),
        );
      },
    );
  }
}
