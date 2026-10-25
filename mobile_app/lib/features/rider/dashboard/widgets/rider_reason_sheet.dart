import 'package:flutter/material.dart';

import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/form_fields.dart';
import 'package:zaylo_mobile/models/rider/rider_dashboard_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// The reason the rider picked, and their own words.
typedef RiderReasonChoice = ({String reason, String? note});

/// Asks why the rider is declining an assignment or could not deliver a parcel. Returns null when dismissed.
/// "Other reason" needs a short description.
Future<RiderReasonChoice?> askRiderReason(
  BuildContext context, {
  required String title,
  required String message,
  required List<RiderReason> reasons,
  required String confirmLabel,
}) =>
    showModalBottomSheet<RiderReasonChoice>(
      context: context,
      isScrollControlled: true,
      backgroundColor: ZayloColors.paper,
      builder: (_) => _RiderReasonSheet(title: title, message: message, reasons: reasons, confirmLabel: confirmLabel),
    );

class _RiderReasonSheet extends StatefulWidget {
  const _RiderReasonSheet({required this.title, required this.message, required this.reasons, required this.confirmLabel});

  final String title;
  final String message;
  final List<RiderReason> reasons;
  final String confirmLabel;

  @override
  State<_RiderReasonSheet> createState() => _RiderReasonSheetState();
}

class _RiderReasonSheetState extends State<_RiderReasonSheet> {
  final _note = TextEditingController();
  RiderReason? _chosen;
  String? _error;

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  void _confirm() {
    final chosen = _chosen;
    if (chosen == null) {
      setState(() => _error = 'Choose a reason.');
      return;
    }
    if (chosen.needsNote && _note.text.trim().isEmpty) {
      setState(() => _error = 'Describe what happened.');
      return;
    }
    Navigator.pop<RiderReasonChoice>(context, (reason: chosen.code, note: _note.text.trim().isEmpty ? null : _note.text.trim()));
  }

  @override
  Widget build(BuildContext context) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
        child: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 18, ZayloSpace.page, 16),
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(widget.title, style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 6),
              Text(widget.message, style: TextStyle(color: ZayloColors.muted)),
              const SizedBox(height: 10),
              for (final reason in widget.reasons)
                Semantics(
                  selected: _chosen == reason,
                  inMutuallyExclusiveGroup: true,
                  child: ListTile(
                    contentPadding: EdgeInsets.zero,
                    dense: true,
                    leading: Icon(
                      _chosen == reason ? Icons.radio_button_checked : Icons.radio_button_unchecked,
                      color: _chosen == reason ? ZayloColors.ink : ZayloColors.muted,
                    ),
                    title: Text(reason.label),
                    onTap: () => setState(() {
                      _chosen = reason;
                      _error = null;
                    }),
                  ),
                ),
              const SizedBox(height: 8),
              FieldLabel(_chosen?.needsNote ?? false ? 'What happened?' : 'Details (optional)'),
              TextField(
                controller: _note,
                maxLength: 500,
                minLines: 2,
                maxLines: 4,
                textCapitalization: TextCapitalization.sentences,
                decoration: zayloInputDecoration('e.g. Gate was locked and no one answered'),
                onChanged: (_) {
                  if (_error != null) setState(() => _error = null);
                },
              ),
              if (_error != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Text(_error!, style: TextStyle(color: ZayloColors.danger, fontSize: 12)),
                ),
              const SizedBox(height: 4),
              PrimaryButton(label: widget.confirmLabel.toUpperCase(), onPressed: _confirm),
            ]),
          ),
        ),
      );
}
