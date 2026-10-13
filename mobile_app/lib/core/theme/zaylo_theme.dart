import 'package:flutter/material.dart';

/// Centralized visual tokens. Change marketplace styling here, not in screens.
abstract final class ZayloColors {
  static const ink = Color(0xFF1A1714);
  static const cream = Color(0xFFFAF7F2);
  static const paper = Color(0xFFFFFDFC);
  static const sand = Color(0xFFEDE4D8);
  static const taupe = Color(0xFF86786B);
  static const muted = Color(0xFF6D635A);
  static const line = Color(0xFFE7DED4);
  static const white = Color(0xFFFFFFFF);
}

abstract final class ZayloSpace {
  static const page = 20.0;
  static const section = 36.0;
  static const card = 16.0;
}

ThemeData zayloTheme() => ThemeData(
      useMaterial3: true,
      scaffoldBackgroundColor: ZayloColors.cream,
      colorScheme: ColorScheme.fromSeed(
        seedColor: ZayloColors.ink,
        brightness: Brightness.light,
        surface: ZayloColors.paper,
      ),
      // Mirrors the web storefront: Inter for UI copy and Playfair Display
      // for editorial headings.
      fontFamily: 'Inter',
      appBarTheme: const AppBarTheme(
        elevation: 0,
        backgroundColor: ZayloColors.cream,
        foregroundColor: ZayloColors.ink,
        centerTitle: true,
      ),
      textTheme: const TextTheme(
        displaySmall: TextStyle(
          fontFamily: 'Playfair Display',
          color: ZayloColors.ink,
          fontSize: 35,
          height: .98,
          fontWeight: FontWeight.w600,
          letterSpacing: -1.1,
        ),
        headlineSmall: TextStyle(
          fontFamily: 'Playfair Display',
          color: ZayloColors.ink,
          fontSize: 27,
          fontWeight: FontWeight.w600,
          letterSpacing: -.6,
        ),
        titleMedium: TextStyle(
          color: ZayloColors.ink,
          fontWeight: FontWeight.w600,
        ),
        bodyMedium: TextStyle(color: ZayloColors.muted, height: 1.45),
        labelLarge: TextStyle(
          color: ZayloColors.ink,
          letterSpacing: .5,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
