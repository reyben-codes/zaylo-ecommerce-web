import 'package:flutter/material.dart';

import '../../../core/theme/zaylo_theme.dart';

class SearchField extends StatelessWidget {
  const SearchField({super.key, required this.controller, required this.onSearch});

  final TextEditingController controller;
  final VoidCallback onSearch;

  @override
  Widget build(BuildContext context) => TextField(
        controller: controller,
        textInputAction: TextInputAction.search,
        onSubmitted: (_) => onSearch(),
        decoration: InputDecoration(
          hintText: 'Search products',
          prefixIcon: const Icon(Icons.search_rounded),
          suffixIcon: IconButton(
              tooltip: 'Search', onPressed: onSearch, icon: const Icon(Icons.arrow_forward_rounded)),
          filled: true,
          fillColor: ZayloColors.white,
          contentPadding: const EdgeInsets.symmetric(vertical: 12),
          border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: ZayloColors.line)),
          enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: ZayloColors.line)),
          focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: ZayloColors.ink)),
        ),
      );
}
