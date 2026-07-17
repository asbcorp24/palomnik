import 'package:flutter/material.dart';

class AppTheme {
  static const green = Color(0xFF26443B);
  static const gold = Color(0xFFB58A32);
  static const cream = Color(0xFFF7F0E6);
  static const paper = Color(0xFFFFFDF9);

  static ThemeData light() {
    final scheme = ColorScheme.fromSeed(
      seedColor: green,
      brightness: Brightness.light,
      primary: green,
      secondary: gold,
      surface: paper,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: const Color(0xFFFBFAF7),
      appBarTheme: const AppBarTheme(
        backgroundColor: paper,
        foregroundColor: green,
        elevation: 0,
        centerTitle: false,
      ),
      cardTheme: CardThemeData(
        color: paper,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(22),
          side: const BorderSide(color: Color(0x1F6F4D37)),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: Color(0x336F4D37)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: Color(0x336F4D37)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: gold, width: 1.5),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: gold,
          foregroundColor: Colors.white,
          minimumSize: const Size(0, 50),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: paper,
        indicatorColor: cream,
        labelTextStyle: WidgetStateProperty.all(const TextStyle(fontSize: 11)),
      ),
    );
  }
}
