import 'package:flutter/material.dart';

import '../theme/zaylo_theme.dart';

class FieldLabel extends StatelessWidget {
  const FieldLabel(this.label, {super.key});

  final String label;

  @override
  Widget build(BuildContext context) => Align(
        alignment: Alignment.centerLeft,
        child: Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: ZayloColors.ink)),
        ),
      );
}

InputDecoration zayloInputDecoration(String hint, {String? errorText}) => InputDecoration(
      hintText: hint,
      errorText: errorText,
      errorMaxLines: 3,
      filled: true,
      fillColor: ZayloColors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
      enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: ZayloColors.line)),
      focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: ZayloColors.ink)),
      errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Colors.redAccent)),
      focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Colors.redAccent)),
    );
