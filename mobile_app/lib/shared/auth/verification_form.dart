import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/models/auth_models.dart';
import '../../core/theme/zaylo_theme.dart';
import '../../core/widgets/buttons.dart';
import '../../core/widgets/feedback.dart';
import '../../core/widgets/form_fields.dart';

/// The six-digit email code form shared by buyer and rider sign-up.
/// [onVerified] decides what happens next: buyers are signed in, riders are
/// told their application is under review.
class VerificationForm extends StatefulWidget {
  const VerificationForm({super.key, required this.api, required this.email, required this.onVerified});

  final ApiClient api;
  final String email;
  final Future<void> Function(AuthResult result) onVerified;

  @override
  State<VerificationForm> createState() => _VerificationFormState();
}

class _VerificationFormState extends State<VerificationForm> {
  final _formKey = GlobalKey<FormState>();
  final _code = TextEditingController();
  bool _verifying = false;
  bool _resending = false;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _verifying = true);
    try {
      final result = await widget.api.verifyEmail(email: widget.email, code: _code.text);
      if (mounted) await widget.onVerified(result);
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _verifying = false);
    }
  }

  Future<void> _resend() async {
    setState(() => _resending = true);
    try {
      final response = await widget.api.resendVerification(widget.email);
      if (mounted) showMessage(context, response['message'] as String? ?? 'A new code has been sent.');
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _resending = false);
    }
  }

  @override
  Widget build(BuildContext context) => Form(
        key: _formKey,
        child: Column(children: [
          const FieldLabel('Email verification code'),
          TextFormField(
            controller: _code,
            keyboardType: TextInputType.number,
            autofillHints: const [AutofillHints.oneTimeCode],
            inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(6)],
            textAlign: TextAlign.center,
            onFieldSubmitted: (_) => _verify(),
            style: const TextStyle(fontSize: 24, letterSpacing: 8, fontFeatures: [FontFeature.tabularFigures()]),
            decoration: zayloInputDecoration('000000'),
            validator: (value) =>
                value != null && RegExp(r'^\d{6}$').hasMatch(value) ? null : 'Enter the six-digit code from your email.',
          ),
          const SizedBox(height: 8),
          Align(
            alignment: Alignment.centerLeft,
            child: Text('Your code expires after 10 minutes.', style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
          ),
          const SizedBox(height: 25),
          PrimaryButton(label: 'VERIFY EMAIL', busy: _verifying, onPressed: _resending ? null : _verify),
          TextButton(
            onPressed: _verifying || _resending ? null : _resend,
            child: Text(_resending ? 'Sending…' : 'Resend code'),
          ),
        ]),
      );
}
