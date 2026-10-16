import 'package:flutter/material.dart';

import 'package:zaylo_mobile/auth/buyer/finish_sign_in.dart';
import 'package:zaylo_mobile/auth/widgets/auth_layout.dart';
import 'package:zaylo_mobile/auth/widgets/verification_form.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/state/app_state.dart';

/// Enter the six-digit code. Used after registering, when signing in to an
/// unverified account, and after changing the email in profile settings.
class VerifyEmailScreen extends StatefulWidget {
  const VerifyEmailScreen({super.key, required this.email, this.offerResend = false});

  final String email;

  /// Highlights "Resend code" when the buyer may not have a fresh code.
  final bool offerResend;

  @override
  State<VerifyEmailScreen> createState() => _VerifyEmailScreenState();
}

class _VerifyEmailScreenState extends State<VerifyEmailScreen> {
  @override
  Widget build(BuildContext context) => AuthScaffold(children: [
        const Eyebrow('One last step'),
        const SizedBox(height: 9),
        Text('Verify your email', style: Theme.of(context).textTheme.headlineSmall),
        const SizedBox(height: 7),
        Text(
          widget.offerResend
              ? 'Your email is not verified yet. Enter the code we sent to ${widget.email}, or tap Resend code for a new one.'
              : 'Enter the six-digit code sent to ${widget.email}.',
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        const SizedBox(height: 26),
        buyerVerificationForm(context, widget.email),
      ]);
}

/// [VerificationForm] wired for buyers: a verified buyer is signed straight in.
Widget buyerVerificationForm(BuildContext context, String email) => VerificationForm(
      api: AppScope.read(context).api,
      email: email,
      onVerified: (result) async {
        if (result.token != null) {
          await finishBuyerSignIn(context, result);
        } else {
          showMessage(context, 'Email verified. Please sign in to continue.');
          Navigator.of(context).popUntil((route) => route.isFirst);
        }
      },
    );
