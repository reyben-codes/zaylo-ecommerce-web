import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/zaylo_theme.dart';
import '../../../core/utils/validators.dart';
import '../../../core/widgets/buttons.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/form_fields.dart';
import '../../state/app_state.dart';

class PasswordScreen extends StatefulWidget {
  const PasswordScreen({super.key});

  @override
  State<PasswordScreen> createState() => _PasswordScreenState();
}

class _PasswordScreenState extends State<PasswordScreen> {
  final _form = GlobalKey<FormState>();
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

  Future<void> _save(bool hasPassword) async {
    setState(() => _errors = {});
    if (!(_form.currentState?.validate() ?? false)) return;
    final state = AppScope.read(context);
    setState(() => _saving = true);
    try {
      final message = await state.api.updatePassword(
        currentPassword: hasPassword ? _current.text : null,
        password: _password.text,
        passwordConfirmation: _confirmation.text,
      );
      state.updateUser(await state.api.me());
      if (!mounted) return;
      showMessage(context, '$message Other devices were signed out.');
      Navigator.pop(context);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _errors = error.fieldErrors);
      showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final hasPassword = AppScope.of(context).user?.hasPassword ?? true;
    final eye = IconButton(
      tooltip: _obscure ? 'Show passwords' : 'Hide passwords',
      onPressed: () => setState(() => _obscure = !_obscure),
      icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
    );
    return Scaffold(
      appBar: AppBar(title: Text(hasPassword ? 'Change password' : 'Create a password')),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.all(ZayloSpace.page),
          children: [
            if (!hasPassword)
              const Padding(
                padding: EdgeInsets.only(bottom: 18),
                child: Text('You signed up with Google. Create a password to also sign in with your email.'),
              ),
            if (hasPassword) ...[
              const FieldLabel('Current password'),
              TextFormField(
                controller: _current,
                obscureText: _obscure,
                autofillHints: const [AutofillHints.password],
                decoration: zayloInputDecoration('Enter your current password', errorText: _errors['current_password'])
                    .copyWith(suffixIcon: eye),
                validator: (value) => (value ?? '').isEmpty ? 'Enter your current password.' : null,
              ),
              const SizedBox(height: 16),
            ],
            const FieldLabel('New password'),
            TextFormField(
              controller: _password,
              obscureText: _obscure,
              autofillHints: const [AutofillHints.newPassword],
              decoration: zayloInputDecoration('Enter a new password', errorText: _errors['password'])
                  .copyWith(helperText: strongPasswordHint, helperMaxLines: 2, suffixIcon: hasPassword ? null : eye),
              validator: strongPasswordError,
            ),
            const SizedBox(height: 16),
            const FieldLabel('Confirm new password'),
            TextFormField(
              controller: _confirmation,
              obscureText: _obscure,
              autofillHints: const [AutofillHints.newPassword],
              decoration: zayloInputDecoration('Enter it again'),
              validator: (value) => value == _password.text ? null : 'Passwords do not match.',
            ),
            const SizedBox(height: 28),
            PrimaryButton(label: 'SAVE PASSWORD', busy: _saving, onPressed: () => _save(hasPassword)),
          ],
        ),
      ),
    );
  }
}
