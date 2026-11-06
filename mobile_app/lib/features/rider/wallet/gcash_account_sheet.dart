import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/form_fields.dart';
import 'package:zaylo_mobile/models/rider/rider_wallet_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Sets the GCash number and name the rider's withdrawals are sent to. Returns the updated wallet once saved, or null
/// when the rider closes the sheet.
Future<RiderWallet?> showGcashAccountSheet(BuildContext context, {required RiderApi api, String? number, String? name}) =>
    showModalBottomSheet<RiderWallet>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      backgroundColor: ZayloColors.paper,
      builder: (_) => _GcashAccountSheet(api: api, number: number, name: name),
    );

class _GcashAccountSheet extends StatefulWidget {
  const _GcashAccountSheet({required this.api, this.number, this.name});

  final RiderApi api;
  final String? number;
  final String? name;

  @override
  State<_GcashAccountSheet> createState() => _GcashAccountSheetState();
}

class _GcashAccountSheetState extends State<_GcashAccountSheet> {
  static final _gcashNumber = RegExp(r'^09\d{9}$');

  final _formKey = GlobalKey<FormState>();
  late final _number = TextEditingController(text: widget.number?.replaceAll(' ', '') ?? '');
  late final _name = TextEditingController(text: widget.name ?? '');
  bool _saving = false;
  Map<String, String> _errors = const {};

  @override
  void dispose() {
    _number.dispose();
    _name.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _errors = const {});
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _saving = true);
    try {
      final (message, wallet) = await widget.api.saveGcashAccount(gcashNumber: _number.text.trim(), gcashName: _name.text.trim());
      if (!mounted) return;
      showMessage(context, message);
      Navigator.of(context).pop(wallet);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _errors = error.fieldErrors);
      if (error.fieldErrors.isEmpty) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Padding(
        padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.viewInsetsOf(context).bottom),
        child: SingleChildScrollView(
          child: Form(
            key: _formKey,
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Text('GCash account', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
              const SizedBox(height: 6),
              Text('Your withdrawals are sent here. Use the number and name registered on your GCash account.',
                  style: TextStyle(color: ZayloColors.muted)),
              const SizedBox(height: 18),
              const FieldLabel('GCash number'),
              TextFormField(
                controller: _number,
                keyboardType: TextInputType.phone,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(11)],
                decoration: zayloInputDecoration('09XXXXXXXXX', errorText: _errors['gcash_number']),
                validator: (value) =>
                    _gcashNumber.hasMatch(value ?? '') ? null : 'Enter your 11-digit GCash number starting with 09.',
              ),
              const SizedBox(height: 14),
              const FieldLabel('Name on the GCash account'),
              TextFormField(
                controller: _name,
                textCapitalization: TextCapitalization.words,
                maxLength: 100,
                decoration: zayloInputDecoration('e.g. Juan Dela Cruz', errorText: _errors['gcash_name']),
                validator: (value) => (value ?? '').trim().length < 2 ? 'Enter the name on your GCash account.' : null,
              ),
              const SizedBox(height: 12),
              PrimaryButton(label: 'SAVE GCASH ACCOUNT', busy: _saving, onPressed: _save),
            ]),
          ),
        ),
      );
}
