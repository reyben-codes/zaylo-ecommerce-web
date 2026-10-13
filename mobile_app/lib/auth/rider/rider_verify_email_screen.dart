import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/auth/widgets/auth_layout.dart';
import 'package:zaylo_mobile/auth/widgets/verification_form.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/layout.dart';

/// Riders confirm their email the first time they sign in after approval
/// (sign-up itself has no code step). A fresh code is sent on arrival.
class RiderVerifyEmailScreen extends StatefulWidget {
  const RiderVerifyEmailScreen({super.key, required this.api, required this.email});

  final RiderApi api;
  final String email;

  @override
  State<RiderVerifyEmailScreen> createState() => _RiderVerifyEmailScreenState();
}

class _RiderVerifyEmailScreenState extends State<RiderVerifyEmailScreen> {
  @override
  void initState() {
    super.initState();
    _sendCode();
  }

  Future<void> _sendCode() async {
    try {
      final response = await widget.api.resendVerification(widget.email);
      if (mounted) showMessage(context, response['message'] as String? ?? 'A verification code has been sent.');
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Rider verification code failed: ${error.debugDescription}');
      if (mounted) showMessage(context, error.message);
    }
  }

  @override
  Widget build(BuildContext context) => AuthScaffold(children: [
        const Eyebrow('One last step'),
        const SizedBox(height: 9),
        Text('Confirm your email', style: Theme.of(context).textTheme.headlineSmall),
        const SizedBox(height: 7),
        Text('Your rider account is approved. Enter the six-digit code we sent to ${widget.email}.',
            style: Theme.of(context).textTheme.bodyMedium),
        const SizedBox(height: 26),
        VerificationForm(
          api: widget.api,
          email: widget.email,
          onVerified: (_) async {
            showMessage(context, 'Email confirmed. Sign in to start riding.');
            Navigator.pop(context);
          },
        ),
      ]);
}
