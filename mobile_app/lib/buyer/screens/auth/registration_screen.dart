import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/utils/validators.dart';
import '../../../core/widgets/buttons.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/form_fields.dart';
import '../../../core/widgets/layout.dart';
import '../../../shared/auth/auth_layout.dart';
import '../../../shared/auth/registration_progress.dart';
import '../../state/app_state.dart';
import 'login_screen.dart';
import 'verify_email_screen.dart';

class RegistrationScreen extends StatefulWidget {
  const RegistrationScreen({super.key});

  @override
  State<RegistrationScreen> createState() => _RegistrationScreenState();
}

class _RegistrationScreenState extends State<RegistrationScreen> {
  final _detailsKey = GlobalKey<FormState>();
  final _signInKey = GlobalKey<FormState>();
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _mobileNumber = TextEditingController();
  final _birthdayText = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _passwordConfirmation = TextEditingController();
  DateTime? _birthday;
  bool _obscurePassword = true;
  bool _isSubmitting = false;
  Map<String, String> _errors = {};
  int _step = 1;

  @override
  void dispose() {
    for (final controller in [
      _firstName,
      _lastName,
      _mobileNumber,
      _birthdayText,
      _email,
      _password,
      _passwordConfirmation,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _pickBirthday() async {
    final today = DateTime.now();
    final birthday = await showDatePicker(
      context: context,
      initialDate: _birthday ?? DateTime(today.year - 18, today.month, today.day),
      firstDate: DateTime(1900),
      lastDate: today,
      helpText: 'SELECT YOUR BIRTHDAY',
    );
    if (birthday == null) return;
    setState(() {
      _birthday = birthday;
      _birthdayText.text = '${birthday.year.toString().padLeft(4, '0')}-${birthday.month.toString().padLeft(2, '0')}-'
          '${birthday.day.toString().padLeft(2, '0')}';
    });
  }

  void _continueToSignInDetails() {
    if (_detailsKey.currentState?.validate() ?? false) setState(() => _step = 2);
  }

  Future<void> _continueToVerification() async {
    setState(() => _errors = {});
    if (!(_signInKey.currentState?.validate() ?? false)) return;
    setState(() => _isSubmitting = true);
    try {
      final response = await AppScope.read(context).api.register(
            firstName: _firstName.text.trim(),
            lastName: _lastName.text.trim(),
            phone: _mobileNumber.text,
            dateOfBirth: _birthdayText.text,
            email: _email.text.trim(),
            password: _password.text,
            passwordConfirmation: _passwordConfirmation.text,
          );
      if (!mounted) return;
      setState(() => _step = 3);
      showMessage(context, response['message'] as String? ?? 'Your verification code has been sent.');
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _errors = error.fieldErrors;
        // Personal-detail problems are fixed on the first step.
        if (_errors.keys.any((key) => ['first_name', 'last_name', 'phone', 'date_of_birth'].contains(key))) _step = 1;
      });
      showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) => AuthScaffold(
        onBack: _step == 2 ? () => setState(() => _step = 1) : null,
        children: [
          const AuthMasthead(registering: true),
          const SizedBox(height: 32),
          const Eyebrow('Join ZAYLO'),
          const SizedBox(height: 9),
          Text(
            switch (_step) { 1 => 'Create new account', 2 => 'Your sign-in details', _ => 'Verify your email' },
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 7),
          Text(
            switch (_step) {
              1 => 'Save your favorites, track your orders, and discover everyday finds in one place.',
              2 => 'Set up the email address and password you will use to sign in.',
              _ => 'Enter the six-digit code sent to ${_email.text.trim()}.',
            },
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: 25),
          RegistrationProgress(step: _step, labels: const ['Personal details', 'Sign-in details', 'Verify']),
          const SizedBox(height: 24),
          switch (_step) {
            1 => _personalDetailsForm(),
            2 => _signInDetailsForm(),
            _ => buyerVerificationForm(context, _email.text.trim()),
          },
          const SizedBox(height: 25),
          if (_step < 3)
            Center(
              child: TextButton(
                onPressed: () => Navigator.of(context).pushReplacement(
                  MaterialPageRoute(builder: (_) => const LoginScreen()),
                ),
                child: const Text('Already have an account? Sign in'),
              ),
            ),
        ],
      );

  Widget _personalDetailsForm() => Form(
        key: _detailsKey,
        child: Column(children: [
          const FieldLabel('First name'),
          TextFormField(
            controller: _firstName,
            textCapitalization: TextCapitalization.words,
            autofillHints: const [AutofillHints.givenName],
            decoration: zayloInputDecoration('Enter your first name', errorText: _errors['first_name']),
            validator: personNameError,
          ),
          const SizedBox(height: 18),
          const FieldLabel('Last name'),
          TextFormField(
            controller: _lastName,
            textCapitalization: TextCapitalization.words,
            autofillHints: const [AutofillHints.familyName],
            decoration: zayloInputDecoration('Enter your last name', errorText: _errors['last_name']),
            validator: personNameError,
          ),
          const SizedBox(height: 18),
          const FieldLabel('Mobile number'),
          TextFormField(
            controller: _mobileNumber,
            keyboardType: TextInputType.phone,
            autofillHints: const [AutofillHints.telephoneNumber],
            inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(11)],
            decoration: zayloInputDecoration('09171234567', errorText: _errors['phone']),
            validator: phMobileError,
          ),
          const SizedBox(height: 18),
          const FieldLabel('Birthday'),
          TextFormField(
            controller: _birthdayText,
            readOnly: true,
            onTap: _pickBirthday,
            decoration: zayloInputDecoration('Select your birthday', errorText: _errors['date_of_birth'])
                .copyWith(suffixIcon: const Icon(Icons.calendar_month_outlined)),
            validator: (_) => _birthday == null ? 'Select your birthday.' : null,
          ),
          const SizedBox(height: 25),
          PrimaryButton(label: 'CONTINUE TO SIGN-IN DETAILS', onPressed: _continueToSignInDetails),
        ]),
      );

  Widget _signInDetailsForm() => Form(
        key: _signInKey,
        child: Column(children: [
          const FieldLabel('Email address'),
          TextFormField(
            controller: _email,
            keyboardType: TextInputType.emailAddress,
            autofillHints: const [AutofillHints.email],
            decoration: zayloInputDecoration('Enter your email address', errorText: _errors['email']),
            validator: (value) => value != null && value.contains('@') ? null : 'Enter a valid email address.',
          ),
          const SizedBox(height: 18),
          const FieldLabel('Password'),
          TextFormField(
            controller: _password,
            obscureText: _obscurePassword,
            autofillHints: const [AutofillHints.newPassword],
            decoration: zayloInputDecoration('Enter your password', errorText: _errors['password']).copyWith(
              helperText: strongPasswordHint,
              helperMaxLines: 2,
              suffixIcon: IconButton(
                tooltip: _obscurePassword ? 'Show password' : 'Hide password',
                onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                icon: Icon(_obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined),
              ),
            ),
            validator: strongPasswordError,
          ),
          const SizedBox(height: 18),
          const FieldLabel('Confirm password'),
          TextFormField(
            controller: _passwordConfirmation,
            obscureText: _obscurePassword,
            autofillHints: const [AutofillHints.newPassword],
            decoration: zayloInputDecoration('Enter your password again'),
            validator: (value) => value == _password.text ? null : 'Passwords do not match.',
          ),
          const SizedBox(height: 25),
          PrimaryButton(label: 'CONTINUE TO VERIFICATION', busy: _isSubmitting, onPressed: _continueToVerification),
        ]),
      );
}
