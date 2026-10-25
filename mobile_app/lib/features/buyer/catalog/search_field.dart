import 'package:flutter/material.dart';

import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// The product search box. With [onTap] it is a button that opens the catalog (as on Home); otherwise it is
/// typed into, with [onChanged] firing on every keystroke so results can follow the text.
class SearchField extends StatelessWidget {
  const SearchField({
    super.key,
    this.controller,
    this.onSearch,
    this.onChanged,
    this.onTap,
    this.autofocus = false,
  });

  final TextEditingController? controller;
  final VoidCallback? onSearch;
  final ValueChanged<String>? onChanged;
  final VoidCallback? onTap;
  final bool autofocus;

  @override
  Widget build(BuildContext context) {
    final field = controller;
    return TextField(
      controller: field,
      readOnly: onTap != null,
      onTap: onTap,
      autofocus: autofocus,
      textInputAction: TextInputAction.search,
      onChanged: onChanged,
      onSubmitted: (_) => onSearch?.call(),
      decoration: InputDecoration(
        hintText: 'Search products',
        prefixIcon: const Icon(Icons.search_rounded),
        suffixIcon: field == null || onTap != null
            ? null
            : ValueListenableBuilder<TextEditingValue>(
                valueListenable: field,
                builder: (context, value, _) => value.text.isEmpty
                    ? const SizedBox.shrink()
                    : IconButton(
                        tooltip: 'Clear search',
                        icon: const Icon(Icons.close_rounded),
                        onPressed: () {
                          field.clear();
                          onChanged?.call('');
                        },
                      ),
              ),
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
}
