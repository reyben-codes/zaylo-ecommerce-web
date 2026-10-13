import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/widgets/buttons.dart';
import '../../../core/widgets/form_fields.dart';
import '../../../core/widgets/layout.dart';
import '../../../shared/auth/auth_layout.dart';
import '../../../shared/auth/forgot_password_screen.dart';
import '../../state/app_state.dart';
import 'finish_sign_in.dart';
import 'registration_screen.dart';
import 'verify_email_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
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
      final result = await AppScope.read(context).api.login(_email.text.trim(), _password.text);
      if (mounted) await finishBuyerSignIn(context, result);
    } on ApiException catch (error) {
      if (!mounted) return;
      if (error.isUnverified) {
        // Same as the web: unverified accounts go to the code screen.
        Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => VerifyEmailScreen(email: error.email ?? _email.text.trim(), offerResend: true),
        ));
      } else {
        setState(() => _error = error.message);
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) => AuthScaffold(children: [
        const AuthMasthead(registering: false),
        const SizedBox(height: 32),
        const Eyebrow('ZAYLO account'),
        const SizedBox(height: 9),
        Text('Welcome Back', style: Theme.of(context).textTheme.headlineSmall),
        const SizedBox(height: 7),
        Text('Sign in to track orders, save favorites, and shop your everyday essentials.',
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
                  builder: (_) => ForgotPasswordScreen(api: AppScope.read(context).api, initialEmail: _email.text.trim()),
                )),
                child: const Text('Forgot password?'),
              ),
            ),
            const SizedBox(height: 12),
            PrimaryButton(label: 'SIGN IN', busy: _submitting, onPressed: _submit),
          ]),
        ),
        const SizedBox(height: 27),
        Center(
          child: TextButton(
            onPressed: () => Navigator.of(context).pushReplacement(
              MaterialPageRoute(builder: (_) => const RegistrationScreen()),
            ),
            child: const Text("Don't have an account? Create new account"),
          ),
        ),
      ]);
}
