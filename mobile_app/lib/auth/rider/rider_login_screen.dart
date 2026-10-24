import 'package:flutter/material.dart';

import 'package:zaylo_mobile/state/chat_head_state.dart';
import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/auth/buyer/login_screen.dart';
import 'package:zaylo_mobile/auth/forgot_password_screen.dart';
import 'package:zaylo_mobile/auth/rider/rider_registration_screen.dart';
import 'package:zaylo_mobile/auth/rider/rider_verify_email_screen.dart';
import 'package:zaylo_mobile/auth/widgets/auth_layout.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/form_fields.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/navigation/rider_shell.dart';
import 'package:zaylo_mobile/state/app_state.dart';

/// Same layout as [LoginScreen], for rider accounts. The rider session lives
/// on its own [RiderApi], so a signed-in buyer session is never replaced.
class RiderLoginScreen extends StatefulWidget {
  const RiderLoginScreen({super.key});

  @override
  State<RiderLoginScreen> createState() => _RiderLoginScreenState();
}

class _RiderLoginScreenState extends State<RiderLoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  // Same server as the buyer client; a separate instance keeps the token apart.
  late final _api = RiderApi(baseUrl: AppScope.read(context).api.baseUrl);
  bool _obscurePassword = true;
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _error = null);
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _submitting = true);
    try {
      final result = await _api.login(_email.text.trim(), _password.text);
      _api.token = result.token;
      if (!mounted) return;
      Navigator.of(context).pushReplacement(MaterialPageRoute(
        builder: (_) => RiderShell(api: _api, user: result.user),
      ));
    } on ApiException catch (error) {
      if (!mounted) return;
      if (error.isUnverified) {
        // Approved riders confirm their email with a code on first sign-in.
        Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => RiderVerifyEmailScreen(api: _api, email: error.email ?? _email.text.trim()),
        ));
      } else {
        setState(() => _error = error.message);
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) => HideChatHead(child: _screen(context));

  // The buyer app's floating chat bubble is hidden in the rider app.
  Widget _screen(BuildContext context) => AuthScaffold(children: [
        const AuthMasthead(registering: false),
        const SizedBox(height: 32),
        const Eyebrow('ZAYLO rider'),
        const SizedBox(height: 9),
        Text('Welcome Back, Rider', style: Theme.of(context).textTheme.headlineSmall),
        const SizedBox(height: 7),
        Text('Sign in to view your deliveries and keep orders moving.',
            style: Theme.of(context).textTheme.bodyMedium),
        const SizedBox(height: 28),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: AuthErrorBanner(_error!),
          ),
        Form(
          key: _formKey,
          child: Column(children: [
            const FieldLabel('Email address'),
            TextFormField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.next,
              autofillHints: const [AutofillHints.email],
              decoration: zayloInputDecoration('Enter your email address'),
              validator: (value) => value != null && value.contains('@') ? null : 'Enter a valid email address.',
            ),
            const SizedBox(height: 18),
            const FieldLabel('Password'),
            TextFormField(
              controller: _password,
              obscureText: _obscurePassword,
              textInputAction: TextInputAction.done,
              onFieldSubmitted: (_) => _submit(),
              autofillHints: const [AutofillHints.password],
              decoration: zayloInputDecoration('Enter your password').copyWith(
                suffixIcon: IconButton(
                  tooltip: _obscurePassword ? 'Show password' : 'Hide password',
                  onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                  icon: Icon(_obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                ),
              ),
              validator: (value) => (value ?? '').isEmpty ? 'Enter your password.' : null,
            ),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                onPressed: () => Navigator.of(context).push(MaterialPageRoute(
                  builder: (_) => ForgotPasswordScreen(api: _api, initialEmail: _email.text.trim()),
                )),
                child: const Text('Forgot password?'),
              ),
            ),
            const SizedBox(height: 12),
            PrimaryButton(label: 'SIGN IN AS RIDER', busy: _submitting, onPressed: _submit),
          ]),
        ),
        const SizedBox(height: 27),
        Center(
          child: TextButton(
            onPressed: () => Navigator.of(context).pushReplacement(
              MaterialPageRoute(builder: (_) => const RiderRegistrationScreen()),
            ),
            child: const Text('New rider? Register as a rider'),
          ),
        ),
        Center(
          child: TextButton(
            onPressed: () => Navigator.of(context).pushReplacement(
              MaterialPageRoute(builder: (_) => const LoginScreen()),
            ),
            child: const Text('Shopping instead? Log in as a buyer'),
          ),
        ),
      ]);
}
