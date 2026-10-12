import 'package:flutter/material.dart';

import '../../core/models/location.dart';
import '../../core/theme/zaylo_theme.dart';
import '../../core/widgets/form_fields.dart';
import '../../core/widgets/layout.dart';

/// A tap-to-choose location field (region, province, city, barangay) that
/// opens a searchable sheet. Shared by buyer and rider address forms.
class LocationField extends StatelessWidget {
  const LocationField({
    super.key,
    required this.label,
    required this.value,
    required this.options,
    required this.enabled,
    required this.onSelected,
    this.errorText,
    this.hint,
  });

  final String label;
  final Location? value;
  final List<Location> options;
  final bool enabled;
  final ValueChanged<Location> onSelected;
  final String? errorText;
  final String? hint;

  Future<void> _pick(BuildContext context) async {
    final choice = await showModalBottomSheet<Location>(
      context: context,
      isScrollControlled: true,
      backgroundColor: ZayloColors.paper,
      builder: (_) => LocationPicker(title: label, options: options),
    );
    if (choice != null && choice.code != value?.code) onSelected(choice);
  }

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          FieldLabel(label),
          InkWell(
            onTap: enabled ? () => _pick(context) : null,
            borderRadius: BorderRadius.circular(10),
            child: InputDecorator(
              isEmpty: value == null,
              decoration: zayloInputDecoration(hint ?? 'Choose ${label.toLowerCase()}', errorText: errorText).copyWith(
                suffixIcon: const Icon(Icons.expand_more),
                fillColor: enabled ? ZayloColors.white : ZayloColors.cream,
              ),
              child: value == null
                  ? null
                  : Text(value!.name, style: TextStyle(fontSize: 15, color: ZayloColors.ink)),
            ),
          ),
        ]),
      );
}

class LocationPicker extends StatefulWidget {
  const LocationPicker({super.key, required this.title, required this.options});

  final String title;
  final List<Location> options;

  @override
  State<LocationPicker> createState() => _LocationPickerState();
}

class _LocationPickerState extends State<LocationPicker> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final query = _query.toLowerCase();
    final matches = widget.options.where((option) => option.name.toLowerCase().contains(query)).toList();
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: .8,
      maxChildSize: .95,
      builder: (context, scroll) => Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 18, 20, 10),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Eyebrow('Choose ${widget.title}'),
            const SizedBox(height: 10),
            TextField(
              autofocus: widget.options.length > 12,
              onChanged: (value) => setState(() => _query = value),
              decoration: zayloInputDecoration('Search').copyWith(prefixIcon: const Icon(Icons.search)),
            ),
          ]),
        ),
        Expanded(
          child: matches.isEmpty
              ? const Center(child: Text('No matches.'))
              : ListView.builder(
                  controller: scroll,
                  itemCount: matches.length,
                  itemBuilder: (context, index) => ListTile(
                    title: Text(matches[index].name),
                    onTap: () => Navigator.pop(context, matches[index]),
                  ),
                ),
        ),
      ]),
    );
  }
}
