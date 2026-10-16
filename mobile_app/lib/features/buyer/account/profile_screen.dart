import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/auth/buyer/verify_email_screen.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/form_fields.dart';
import 'package:zaylo_mobile/models/auth_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/validators.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final _form = GlobalKey<FormState>();
  late final UserProfile _user = AppScope.read(context).user!;
  late final _name = TextEditingController(text: _user.name);
  late final _email = TextEditingController(text: _user.email);
  late final _phone = TextEditingController(text: _user.phone);
  late final _birthday = TextEditingController(text: _user.dateOfBirth);
  bool _saving = false;
  Map<String, String> _errors = {};

  @override
  void dispose() {
    for (final controller in [_name, _email, _phone, _birthday]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _pickBirthday() async {
    final today = DateTime.now();
    final current = DateTime.tryParse(_birthday.text);
    final picked = await showDatePicker(
      context: context,
      initialDate: current ?? DateTime(today.year - 18, today.month, today.day),
      firstDate: DateTime(1900),
      lastDate: today,
      helpText: 'SELECT YOUR BIRTHDAY',
    );
    if (picked != null) {
      _birthday.text = '${picked.year.toString().padLeft(4, '0')}-${picked.month.toString().padLeft(2, '0')}-'
          '${picked.day.toString().padLeft(2, '0')}';
    }
  }

  Future<void> _save() async {
    setState(() => _errors = {});
    if (!(_form.currentState?.validate() ?? false)) return;
    final state = AppScope.read(context);
    setState(() => _saving = true);
    try {
      final result = await state.api.updateProfile(
        name: _name.text.trim(),
        email: _email.text.trim(),
        phone: _phone.text,
        dateOfBirth: _birthday.text,
      );
      state.updateUser(result.user);
      if (!mounted) return;
      showMessage(context, result.message);
      if (result.emailVerificationRequired) {
        // Same as the web: a new email must be verified before shopping continues.
        await Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => VerifyEmailScreen(email: result.user.email)),
        );
      } else {
        Navigator.pop(context);
      }
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _errors = error.fieldErrors);
      showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Profile settings')),
        body: Form(
          key: _form,
          child: ListView(
            padding: const EdgeInsets.all(ZayloSpace.page),
            children: [
              const FieldLabel('Full name'),
              TextFormField(
                controller: _name,
                textCapitalization: TextCapitalization.words,
                decoration: zayloInputDecoration('Your name', errorText: _errors['name']),
                validator: personNameError,
              ),
              const SizedBox(height: 16),
              const FieldLabel('Email address'),
              TextFormField(
                controller: _email,
                readOnly: _user.googleLinked,
                keyboardType: TextInputType.emailAddress,
                decoration: zayloInputDecoration('you@example.com', errorText: _errors['email']).copyWith(
                  helperText: _user.googleLinked
                      ? 'This email is linked to Google and cannot be changed here.'
                      : 'Changing it means verifying the new address with a code.',
                  helperMaxLines: 2,
                ),
                validator: (value) =>
                    RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(value?.trim() ?? '') ? null : 'Enter a valid email address.',
              ),
              const SizedBox(height: 16),
              const FieldLabel('Mobile number'),
              TextFormField(
                controller: _phone,
                keyboardType: TextInputType.phone,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(11)],
                decoration: zayloInputDecoration('09171234567', errorText: _errors['phone']),
                validator: phMobileError,
              ),
              const SizedBox(height: 16),
              const FieldLabel('Birthday'),
              TextFormField(
                controller: _birthday,
                readOnly: true,
                onTap: _pickBirthday,
                decoration: zayloInputDecoration('Select your birthday', errorText: _errors['date_of_birth'])
                    .copyWith(suffixIcon: const Icon(Icons.calendar_month_outlined)),
                validator: (value) => (value ?? '').isEmpty ? 'Select your birthday.' : null,
              ),
              const SizedBox(height: 28),
              PrimaryButton(label: 'SAVE CHANGES', busy: _saving, onPressed: _save),
            ],
          ),
        ),
      );
}
