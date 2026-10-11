import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/zaylo_theme.dart';
import '../../core/widgets/buttons.dart';
import '../../core/widgets/feedback.dart';
import '../../shared/address/ph_address_section.dart';
import '../api/rider_api.dart';

/// Edits the rider's home address with the PSGC dropdowns. Pops `true` when saved.
class RiderAddressScreen extends StatefulWidget {
  const RiderAddressScreen({super.key, required this.api, this.initial});

  final RiderApi api;
  final PhAddressValue? initial;

  @override
  State<RiderAddressScreen> createState() => _RiderAddressScreenState();
}

class _RiderAddressScreenState extends State<RiderAddressScreen> {
  final _formKey = GlobalKey<FormState>();
  late final _address = PhAddressController(initial: widget.initial);
  Map<String, String> _errors = {};
  bool _saving = false;

  @override
  void dispose() {
    _address.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _errors = {});
    final valid = _formKey.currentState?.validate() ?? false;
    final value = _address.value;
    if (!valid || value == null) return;
    setState(() => _saving = true);
    try {
      await widget.api.updateAddress(value);
      if (!mounted) return;
      showMessage(context, 'Address updated.');
      Navigator.pop(context, true);
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Rider address update failed: ${error.debugDescription}');
      if (!mounted) return;
      setState(() => _errors = error.fieldErrors);
      showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: ZayloColors.white,
        appBar: AppBar(backgroundColor: ZayloColors.white, title: const Text('Home address')),
        body: SafeArea(
          top: false,
          child: Form(
            key: _formKey,
            child: ListView(padding: const EdgeInsets.fromLTRB(24, 14, 24, 36), children: [
              const Text('Choose your region first, then your province, city or municipality, and barangay.',
                  style: TextStyle(color: ZayloColors.muted)),
              const SizedBox(height: 20),
              PhAddressSection(api: widget.api, controller: _address, errors: _errors),
              const SizedBox(height: 24),
              PrimaryButton(label: 'SAVE ADDRESS', busy: _saving, onPressed: _save),
            ]),
          ),
        ),
      );
}
