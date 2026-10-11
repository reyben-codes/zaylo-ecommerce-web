import 'package:flutter/material.dart';

import '../../core/theme/zaylo_theme.dart';
import '../../core/widgets/buttons.dart';
import '../screens/auth/login_screen.dart';
import '../screens/auth/registration_screen.dart';

/// Shown in the Saved, Bag and Account tabs to guests.
class SignedOutPrompt extends StatelessWidget {
  const SignedOutPrompt({super.key, required this.icon, required this.title, required this.message});

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(title)),
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
