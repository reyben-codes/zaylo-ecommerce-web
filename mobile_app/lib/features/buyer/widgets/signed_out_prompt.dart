import 'package:flutter/material.dart';

import 'package:zaylo_mobile/auth/buyer/login_screen.dart';
import 'package:zaylo_mobile/auth/buyer/registration_screen.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/features/buyer/widgets/tab_header.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Shown in the Saved, Bag and Account tabs to guests.
class SignedOutPrompt extends StatelessWidget {
  const SignedOutPrompt({
    super.key,
    required this.icon,
    required this.heading,
    required this.eyebrow,
    required this.title,
    required this.message,
  });

  final IconData icon;

  /// The tab's name, shown in the header.
  final String heading;
  final String eyebrow;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: TabHeader(eyebrow: eyebrow, title: heading),
        body: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(36),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              CircleAvatar(
                radius: 38,
                backgroundColor: ZayloColors.sand,
                child: Icon(icon, size: 34, color: ZayloColors.ink),
              ),
              const SizedBox(height: 20),
              Text(title, style: Theme.of(context).textTheme.headlineSmall),
              const SizedBox(height: 8),
              Text(message, textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodyMedium),
              const SizedBox(height: 24),
              PrimaryButton(
                label: 'SIGN IN',
                onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LoginScreen())),
              ),
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton(
                  onPressed: () =>
                      Navigator.of(context).push(MaterialPageRoute(builder: (_) => const RegistrationScreen())),
                  style: secondaryButtonStyle,
                  child: const Text('CREATE NEW ACCOUNT'),
                ),
              ),
            ]),
          ),
        ),
      );
}
