import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_client.dart';
import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/auth/widgets/auth_layout.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/form_fields.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key, required this.api, this.initialEmail = ''});

  /// Buyer or rider client; the reset endpoint is the same for both.
  final ApiClient api;
  final String initialEmail;

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  late final _email = TextEditingController(text: widget.initialEmail);
  bool _submitting = false;
  String? _sentMessage;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _error = null);
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _submitting = true);
    try {
      final message = await widget.api.forgotPassword(_email.text.trim());
      if (mounted) setState(() => _sentMessage = message);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) => AuthScaffold(children: [
        const Eyebrow('Account help'),
        const SizedBox(height: 9),
        Text('Reset your password', style: Theme.of(context).textTheme.headlineSmall),
        const SizedBox(height: 7),
        Text(
          'Enter the email you shop with. We will send a link to set a new password; '
          'it opens in your browser, then you can sign in here.',
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        const SizedBox(height: 26),
        if (_sentMessage != null) ...[
          SectionCard(
            child: Row(children: [
              Icon(Icons.mark_email_read_outlined, color: ZayloColors.success),
              const SizedBox(width: 12),
              Expanded(child: Text(_sentMessage!, style: TextStyle(color: ZayloColors.ink))),
            ]),
          ),
          const SizedBox(height: 20),
          PrimaryButton(label: 'BACK TO SIGN IN', onPressed: () => Navigator.pop(context)),
        ] else
          Form(
            key: _formKey,
            child: Column(children: [
              if (_error != null) Padding(padding: const EdgeInsets.only(bottom: 16), child: AuthErrorBanner(_error!)),
              const FieldLabel('Email address'),
              TextFormField(
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                autofillHints: const [AutofillHints.email],
                onFieldSubmitted: (_) => _submit(),
                decoration: zayloInputDecoration('Enter your email address'),
                validator: (value) => value != null && value.contains('@') ? null : 'Enter a valid email address.',
              ),
              const SizedBox(height: 24),
              PrimaryButton(label: 'SEND RESET LINK', busy: _submitting, onPressed: _submit),
            ]),
          ),
      ]);
}
