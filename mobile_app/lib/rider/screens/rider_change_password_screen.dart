import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/zaylo_theme.dart';
import '../../core/utils/validators.dart';
import '../../core/widgets/buttons.dart';
import '../../core/widgets/feedback.dart';
import '../../core/widgets/form_fields.dart';
import '../api/rider_api.dart';

/// Changes the rider's password; other devices are signed out by the server.
class RiderChangePasswordScreen extends StatefulWidget {
  const RiderChangePasswordScreen({super.key, required this.api});

  final RiderApi api;

  @override
  State<RiderChangePasswordScreen> createState() => _RiderChangePasswordScreenState();
}

class _RiderChangePasswordScreenState extends State<RiderChangePasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _current = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  bool _obscure = true;
  bool _saving = false;
  Map<String, String> _errors = {};

  @override
  void dispose() {
    _current.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _errors = {});
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _saving = true);
    try {
      final message = await widget.api.updatePassword(
        currentPassword: _current.text,
        password: _password.text,
        passwordConfirmation: _confirmation.text,
      );
      if (!mounted) return;
      showMessage(context, message);
      Navigator.pop(context);
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Rider password change failed: ${error.debugDescription}');
      if (!mounted) return;
      setState(() => _errors = error.fieldErrors);
      if (error.fieldErrors.isEmpty) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Widget _visibilityToggle() => IconButton(
        tooltip: _obscure ? 'Show passwords' : 'Hide passwords',
        onPressed: () => setState(() => _obscure = !_obscure),
        icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
      );

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: ZayloColors.white,
        appBar: AppBar(backgroundColor: ZayloColors.white, title: const Text('Change password')),
        body: SafeArea(
          top: false,
          child: Form(
            key: _formKey,
            child: ListView(padding: const EdgeInsets.fromLTRB(24, 14, 24, 36), children: [
              const FieldLabel('Current password'),
              TextFormField(
                controller: _current,
                obscureText: _obscure,
                autofillHints: const [AutofillHints.password],
                decoration: zayloInputDecoration('Enter your current password', errorText: _errors['current_password'])
                    .copyWith(suffixIcon: _visibilityToggle()),
                validator: (value) => (value ?? '').isEmpty ? 'Enter your current password.' : null,
              ),
              const SizedBox(height: 18),
              const FieldLabel('New password'),
              TextFormField(
                controller: _password,
                obscureText: _obscure,
                autofillHints: const [AutofillHints.newPassword],
                decoration: zayloInputDecoration('Create a new password', errorText: _errors['password'])
                    .copyWith(helperText: strongPasswordHint, helperMaxLines: 2),
                validator: strongPasswordError,
              ),
              const SizedBox(height: 18),
              const FieldLabel('Confirm new password'),
              TextFormField(
                controller: _confirmation,
                obscureText: _obscure,
                autofillHints: const [AutofillHints.newPassword],
                decoration: zayloInputDecoration('Enter the new password again'),
                validator: (value) => value == _password.text ? null : 'Passwords do not match.',
              ),
              const SizedBox(height: 10),
              const Text('Other phones signed in to your rider account will be signed out.',
                  style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
              const SizedBox(height: 24),
              PrimaryButton(label: 'UPDATE PASSWORD', busy: _saving, onPressed: _save),
            ]),
          ),
        ),
      );
}
