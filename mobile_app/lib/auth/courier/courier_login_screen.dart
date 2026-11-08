import 'package:flutter/material.dart';

import 'package:zaylo_mobile/state/chat_head_state.dart';
import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/auth/buyer/login_screen.dart';
import 'package:zaylo_mobile/auth/forgot_password_screen.dart';
import 'package:zaylo_mobile/auth/rider/rider_login_screen.dart';
import 'package:zaylo_mobile/auth/rider/rider_verify_email_screen.dart';
import 'package:zaylo_mobile/auth/widgets/auth_layout.dart';
import 'package:zaylo_mobile/auth/widgets/google_sign_in_button.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/form_fields.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/navigation/rider_shell.dart';
import 'package:zaylo_mobile/state/app_state.dart';

/// Sign-in screen for courier accounts (first-mile pickup drivers).
class CourierLoginScreen extends StatefulWidget {
  const CourierLoginScreen({super.key, this.api});

  final RiderApi? api;

  @override
  State<CourierLoginScreen> createState() => _CourierLoginScreenState();
}

class _CourierLoginScreenState extends State<CourierLoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  late final _api = widget.api ?? RiderApi(baseUrl: AppScope.read(context).api.baseUrl);
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
      final result = await _api.courierLogin(_email.text.trim(), _password.text);
      await _api.keepSignedIn(result.token);
      if (!mounted) return;
      Navigator.of(context).pushReplacement(MaterialPageRoute(
        builder: (_) => RiderShell(api: _api, user: result.user),
      ));
    } on ApiException catch (error) {
      if (!mounted) return;
      if (error.isUnverified) {
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

  /// "Continue with Google" for couriers; errors show in the screen's banner.
  Future<void> _googleSignIn(String idToken) async {
    final result = await _api.courierGoogleSignIn(idToken);
    await _api.keepSignedIn(result.token);
    if (!mounted) return;
    Navigator.of(context).pushReplacement(MaterialPageRoute(
      builder: (_) => RiderShell(api: _api, user: result.user),
    ));
  }

  @override
  Widget build(BuildContext context) => HideChatHead(child: _screen(context));

  Widget _screen(BuildContext context) => AuthScaffold(children: [
        const AuthMasthead(registering: false),
        const SizedBox(height: 32),
        const Eyebrow('ZAYLO courier'),
        const SizedBox(height: 9),
        Text('Welcome Back, Courier', style: Theme.of(context).textTheme.headlineSmall),
        const SizedBox(height: 7),
        Text('Sign in to view seller pickup requests and bring parcels to your partner hub.',
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
            PrimaryButton(label: 'SIGN IN AS COURIER', busy: _submitting, onPressed: _submit),
            GoogleSignInSection(
              onError: (message) => setState(() => _error = message),
              signIn: _googleSignIn,
            ),
          ]),
        ),
        const SizedBox(height: 27),
        Center(
          child: TextButton(
            onPressed: () => Navigator.of(context).pushReplacement(
              MaterialPageRoute(builder: (_) => const RiderLoginScreen()),
            ),
            child: const Text('Rider instead? Log in as a rider'),
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

