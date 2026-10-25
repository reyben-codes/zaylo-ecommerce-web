import 'package:flutter/material.dart';

import 'package:zaylo_mobile/state/chat_head_state.dart';
import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/auth/widgets/auth_layout.dart';
import 'package:zaylo_mobile/auth/widgets/verification_form.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/layout.dart';

/// Opened from sign-in when the email still needs its code: an application
/// whose code was never entered, or an older account. A fresh code is sent on arrival.
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
  Widget build(BuildContext context) => HideChatHead(child: _screen(context));

  // The buyer app's floating chat bubble is hidden in the rider app.
  Widget _screen(BuildContext context) => AuthScaffold(children: [
        const Eyebrow('One last step'),
        const SizedBox(height: 9),
        Text('Confirm your email', style: Theme.of(context).textTheme.headlineSmall),
        const SizedBox(height: 7),
        Text('Enter the six-digit code we sent to ${widget.email}.',
            style: Theme.of(context).textTheme.bodyMedium),
        const SizedBox(height: 26),
        VerificationForm(
          api: widget.api,
          email: widget.email,
          onVerified: (result) async {
            // The server says what happens next: an application goes to its logistics
            // partner for review; an approved rider can sign in now.
            showMessage(context, result.message.isNotEmpty ? result.message : 'Email confirmed. Sign in to continue.');
            Navigator.pop(context);
          },
        ),
      ]);
}
