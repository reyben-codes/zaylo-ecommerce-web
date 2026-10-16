import 'dart:io';

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/auth/rider/rider_login_screen.dart';
import 'package:zaylo_mobile/auth/rider/widgets/document_upload_field.dart';
import 'package:zaylo_mobile/auth/rider/widgets/rider_terms_dialog.dart';
import 'package:zaylo_mobile/auth/widgets/auth_layout.dart';
import 'package:zaylo_mobile/auth/widgets/registration_progress.dart';
import 'package:zaylo_mobile/components/address/ph_address_section.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/form_fields.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/models/rider/rider_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';
import 'package:zaylo_mobile/utils/rider_input_formatters.dart';
import 'package:zaylo_mobile/utils/rider_validators.dart';
import 'package:zaylo_mobile/utils/validators.dart';

/// Rider sign-up, laid out like buyer registration:
/// 1. personal details, 2. rider details and documents (checked with the
/// server, nothing uploaded yet), 3. Rider Terms, then the upload, and
/// 4. a confirmation. The rider applies to a logistics partner, which
/// approves the account from its Riders page on the website.
class RiderRegistrationScreen extends StatefulWidget {
  const RiderRegistrationScreen({super.key, this.api});

  /// Defaults to a rider client on the buyer client's server; tests pass a fake.
  final RiderApi? api;

  @override
  State<RiderRegistrationScreen> createState() => _RiderRegistrationScreenState();
}

class _RiderRegistrationScreenState extends State<RiderRegistrationScreen> {
  static const minimumAge = 18; // RegisterRiderRequest::MINIMUM_AGE
  static const _stepOneFields = [
    'first_name',
    'last_name',
    'date_of_birth',
    'gender',
    'region_code',
    'province_code',
    'city_municipality_code',
    'barangay_code',
    'line1',
    'postal_code',
  ];
  static const _termsStep = 3;
  static const _successStep = 4;

  // Same server as the buyer client; a separate instance keeps sessions apart.
  late final _api = widget.api ?? RiderApi(baseUrl: AppScope.read(context).api.baseUrl);

  final _personalKey = GlobalKey<FormState>();
  final _riderKey = GlobalKey<FormState>();

  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _birthdayText = TextEditingController();
  final _email = TextEditingController();
  final _mobileNumber = TextEditingController();
  final _password = TextEditingController();
  final _passwordConfirmation = TextEditingController();
  final _licenseNumber = TextEditingController();
  final _licenseExpiryText = TextEditingController();
  final _plateNumber = TextEditingController();

  DateTime? _birthday;
  DateTime? _licenseExpiry;
  String? _vehicleType;
  int? _partnerId;

  /// Logistics partners and vehicle types, loaded from the server.
  Future<RiderSignUpOptions>? _options;
  String? _gender;
  final _address = PhAddressController();
  File? _licenseImage;
  File? _orCrImage;

  bool _obscurePassword = true;
  bool _termsAccepted = false;
  bool _isSubmitting = false;
  Map<String, String> _errors = {};
  int _step = 1;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _options ??= _api.signUpOptions();
  }

  void _reloadOptions() => setState(() => _options = _api.signUpOptions());

  /// Bicycles need no driver's license, plate or OR/CR.
  bool _needsLicense(RiderSignUpOptions? options) =>
      _vehicleType == null || !(options?.unlicensedVehicleTypes ?? riderUnlicensedVehicleTypes).contains(_vehicleType);

  String? get _partnerName => _loadedOptions?.partners.where((partner) => partner.id == _partnerId).firstOrNull?.name;

  RiderSignUpOptions? _loadedOptions;

  @override
  void dispose() {
    for (final controller in [
      _firstName,
      _lastName,
      _birthdayText,
      _email,
      _mobileNumber,
      _password,
      _passwordConfirmation,
      _licenseNumber,
      _licenseExpiryText,
      _plateNumber,
    ]) {
      controller.dispose();
    }
    _address.dispose();
    super.dispose();
  }

  // ------------------------------------------------------------ Date pickers

  Future<void> _pickBirthday() async {
    final today = DateTime.now();
    final latest = DateTime(today.year - minimumAge, today.month, today.day);
    final birthday = await showDatePicker(
      context: context,
      initialDate: _birthday ?? latest,
      firstDate: DateTime(1900),
      lastDate: latest,
      helpText: 'SELECT YOUR BIRTHDAY',
    );
    if (birthday == null) return;
    setState(() {
      _birthday = birthday;
      _birthdayText.text = apiDate(birthday);
    });
  }

  Future<void> _pickLicenseExpiry() async {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    // Expiring today or later is accepted; anything before today has expired.
    final expiry = await showDatePicker(
      context: context,
      initialDate: _licenseExpiry ?? DateTime(today.year + 1, today.month, today.day),
      firstDate: today,
      lastDate: DateTime(today.year + 15),
      helpText: 'LICENSE EXPIRY DATE',
    );
    if (expiry == null) return;
    setState(() {
      _licenseExpiry = expiry;
      _licenseExpiryText.text = apiDate(expiry);
    });
  }

  // ----------------------------------------------------------------- Submit

  void _continueToRiderDetails() {
    final valid = _personalKey.currentState?.validate() ?? false;
    if (valid && _address.value != null) setState(() => _step = 2);
  }

  RiderRegistration _registration() {
    final licensed = _needsLicense(_loadedOptions);
    return RiderRegistration(
      firstName: _firstName.text.trim(),
      lastName: _lastName.text.trim(),
      dateOfBirth: _birthdayText.text,
      gender: _gender!,
      address: _address.value!,
      email: _email.text.trim(),
      phone: _mobileNumber.text,
      password: _password.text,
      passwordConfirmation: _passwordConfirmation.text,
      logisticsProviderId: _partnerId!,
      vehicleType: _vehicleType!,
      licenseNumber: licensed ? _licenseNumber.text.trim() : '',
      licenseExpiresOn: licensed ? _licenseExpiryText.text : '',
      plateNumber: licensed ? _plateNumber.text : '',
      licenseImage: licensed ? _licenseImage : null,
      orCrImage: licensed ? _orCrImage : null,
      termsAccepted: _termsAccepted,
    );
  }

  /// Step 2 -> 3: the server checks every field (including "already
  /// registered") before anything is uploaded.
  Future<void> _continueToTerms() async {
    setState(() => _errors = {});
    if (!(_riderKey.currentState?.validate() ?? false)) return;
    await _run(() async {
      await _api.checkRegistration(_registration());
      if (mounted) setState(() => _step = _termsStep);
    });
  }

  /// Step 3 -> 4: uploads the documents and creates the pending application.
  Future<void> _submitApplication() async {
    if (!_termsAccepted) {
      setState(() => _errors = {'terms_accepted': 'Read and accept the Rider Terms and Conditions to continue.'});
      return;
    }
    await _run(() async {
      await _api.register(_registration());
      if (mounted) setState(() => _step = _successStep);
    });
  }

  Future<void> _run(Future<void> Function() action) async {
    setState(() {
      _errors = {};
      _isSubmitting = true;
    });
    try {
      await action();
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Rider registration failed: ${error.debugDescription}');
      if (!mounted) return;
      setState(() {
        _errors = error.fieldErrors;
        // Send the rider back to the step that has the problem.
        if (_errors.keys.any(_stepOneFields.contains)) {
          _step = 1;
        } else if (_errors.keys.any((key) => key != 'terms_accepted')) {
          _step = 2;
        }
      });
      showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  /// Opens the terms; reading them to the bottom ticks the box.
  Future<void> _openTerms() async {
    final read = await showRiderTermsDialog(context);
    if (read && mounted) {
      setState(() {
        _termsAccepted = true;
        _errors.remove('terms_accepted');
      });
    }
  }

  void _finish() => Navigator.of(context).popUntil((route) => route.isFirst);

  // ------------------------------------------------------------------ Build

  @override
  Widget build(BuildContext context) => PopScope(
        // After submitting there is nothing to go back to; back leaves sign-up.
        canPop: _step != _successStep,
        onPopInvokedWithResult: (didPop, _) {
          if (!didPop) _finish();
        },
        child: AuthScaffold(
          onBack: switch (_step) {
            2 || _termsStep => () => setState(() => _step--),
            _successStep => _finish,
            _ => null,
          },
          children: [
            const AuthMasthead(registering: true),
            const SizedBox(height: 32),
            if (_step == _successStep)
              _ApplicationSubmitted(email: _email.text.trim(), partner: _partnerName, onDone: _finish)
            else ...[
              const Eyebrow('Ride with ZAYLO'),
              const SizedBox(height: 9),
              Text(
                switch (_step) {
                  1 => 'Register as a rider',
                  2 => 'Your rider details',
                  _ => 'Terms and conditions',
                },
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 7),
              Text(
                switch (_step) {
                  1 => 'Tell us who you are and where you live. You must be at least $minimumAge years old to ride with ZAYLO.',
                  2 => 'Choose the logistics partner you will ride for, then add your contact details and vehicle.',
                  _ => 'Read the Rider Terms and Conditions, then submit your application.',
                },
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 25),
              RegistrationProgress(step: _step, labels: const ['Personal details', 'Rider details', 'Terms']),
              const SizedBox(height: 24),
              switch (_step) {
                1 => _personalDetailsForm(),
                2 => _riderDetailsForm(),
                _ => _termsForm(),
              },
              const SizedBox(height: 25),
              Center(
                child: TextButton(
                  onPressed: () => Navigator.of(context).pushReplacement(
                    MaterialPageRoute(builder: (_) => const RiderLoginScreen()),
                  ),
                  child: const Text('Already a rider? Sign in'),
                ),
              ),
            ],
          ],
        ),
      );

  Widget _termsForm() {
    final error = _errors['terms_accepted'];
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Material(
        color: ZayloColors.paper,
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          // Tapping always opens the terms; they are accepted by reading them.
          onTap: _openTerms,
          child: Container(
            padding: const EdgeInsets.fromLTRB(6, 6, 14, 6),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: error != null ? Colors.redAccent : ZayloColors.line),
            ),
            child: Row(children: [
              Checkbox(
                value: _termsAccepted,
                activeColor: ZayloColors.ink,
                onChanged: (value) => value == false ? setState(() => _termsAccepted = false) : _openTerms(),
              ),
              const Expanded(
                child: Text.rich(TextSpan(children: [
                  TextSpan(text: 'I have read and agree to the '),
                  TextSpan(
                    text: 'Rider Terms and Conditions',
                    style: TextStyle(fontWeight: FontWeight.w700, decoration: TextDecoration.underline),
                  ),
                ])),
              ),
            ]),
          ),
        ),
      ),
      Padding(
        padding: const EdgeInsets.only(top: 6, left: 12),
        child: Text(
          error ?? (_termsAccepted ? 'Accepted.' : 'Tap to open the terms. Reading to the end accepts them.'),
          style: TextStyle(fontSize: 12, color: error != null ? Colors.redAccent : ZayloColors.muted),
        ),
      ),
      const SizedBox(height: 25),
      PrimaryButton(label: 'SUBMIT APPLICATION', busy: _isSubmitting, onPressed: _submitApplication),
    ]);
  }

  Widget _personalDetailsForm() => Form(
        key: _personalKey,
        child: Column(children: [
          const FieldLabel('First name'),
          TextFormField(
            controller: _firstName,
            textCapitalization: TextCapitalization.words,
            inputFormatters: riderNameInputFormatters,
            autofillHints: const [AutofillHints.givenName],
            decoration: zayloInputDecoration('Enter your first name', errorText: _errors['first_name']),
            validator: riderNameError,
          ),
          const SizedBox(height: 18),
          const FieldLabel('Last name'),
          TextFormField(
            controller: _lastName,
            textCapitalization: TextCapitalization.words,
            inputFormatters: riderNameInputFormatters,
            autofillHints: const [AutofillHints.familyName],
            decoration: zayloInputDecoration('Enter your last name', errorText: _errors['last_name']),
            validator: riderNameError,
          ),
          const SizedBox(height: 18),
          const FieldLabel('Date of birth'),
          TextFormField(
            controller: _birthdayText,
            readOnly: true,
            onTap: _pickBirthday,
            decoration: zayloInputDecoration('Select your date of birth', errorText: _errors['date_of_birth'])
                .copyWith(suffixIcon: const Icon(Icons.calendar_month_outlined)),
            validator: (_) => _birthday == null ? 'Select your date of birth.' : null,
          ),
          const SizedBox(height: 18),
          const FieldLabel('Gender'),
          FormField<String>(
            initialValue: _gender,
            validator: (_) => _gender == null ? 'Select your gender.' : null,
            builder: (field) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Wrap(spacing: 10, children: [
                for (final entry in riderGenders.entries)
                  ChoiceChip(
                    label: Text(entry.value),
                    selected: _gender == entry.key,
                    selectedColor: ZayloColors.ink,
                    labelStyle: TextStyle(color: _gender == entry.key ? ZayloColors.white : ZayloColors.ink),
                    onSelected: (_) {
                      setState(() => _gender = entry.key);
                      field.didChange(entry.key);
                    },
                  ),
              ]),
              if ((field.errorText ?? _errors['gender']) != null)
                Padding(
                  padding: const EdgeInsets.only(top: 6, left: 12),
                  child: Text(field.errorText ?? _errors['gender']!,
                      style: const TextStyle(color: Colors.redAccent, fontSize: 12)),
                ),
            ]),
          ),
          const SizedBox(height: 28),
          const _SectionTitle('Home address'),
          PhAddressSection(api: _api, controller: _address, errors: _errors),
          const SizedBox(height: 25),
          PrimaryButton(label: 'CONTINUE TO RIDER DETAILS', onPressed: _continueToRiderDetails),
        ]),
      );

  Widget _riderDetailsForm() => Form(
        key: _riderKey,
        child: Column(children: [
          const FieldLabel('Email address'),
          TextFormField(
            controller: _email,
            keyboardType: TextInputType.emailAddress,
            autofillHints: const [AutofillHints.email],
            inputFormatters: riderEmailInputFormatters,
            decoration: zayloInputDecoration('name@example.com', errorText: _errors['email']),
            validator: riderEmailError,
          ),
          const SizedBox(height: 18),
          const FieldLabel('Mobile number'),
          TextFormField(
            controller: _mobileNumber,
            keyboardType: TextInputType.number,
            autofillHints: const [AutofillHints.telephoneNumber],
            inputFormatters: riderPhoneInputFormatters,
            // Shows "must start with 09" as soon as a wrong first digit is typed.
            autovalidateMode: AutovalidateMode.onUserInteraction,
            decoration: zayloInputDecoration('09171234567', errorText: _errors['phone']),
            validator: riderPhoneError,
          ),
          const SizedBox(height: 18),
          const FieldLabel('Password'),
          TextFormField(
            controller: _password,
            obscureText: _obscurePassword,
            autofillHints: const [AutofillHints.newPassword],
            decoration: zayloInputDecoration('Create a password', errorText: _errors['password']).copyWith(
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
          const SizedBox(height: 28),
          FutureBuilder<RiderSignUpOptions>(
            future: _options,
            builder: (context, snapshot) {
              if (snapshot.hasError) {
                return LoadError(
                  error: snapshot.error,
                  onRetry: _reloadOptions,
                  title: 'Could not load logistics partners',
                );
              }
              if (!snapshot.hasData) return const Padding(padding: EdgeInsets.all(24), child: Loader());
              _loadedOptions = snapshot.requireData;
              return _partnerAndVehicleFields(snapshot.requireData);
            },
          ),
          const SizedBox(height: 25),
          PrimaryButton(label: 'CONTINUE TO TERMS', busy: _isSubmitting, onPressed: _continueToTerms),
        ]),
      );

  Widget _partnerAndVehicleFields(RiderSignUpOptions options) {
    final licensed = _needsLicense(options);
    return Column(children: [
      const _SectionTitle('Logistics partner'),
      const FieldLabel('Who will you ride for?'),
      if (options.partners.isEmpty)
        SectionCard(
          child: Row(children: [
            const Icon(Icons.info_outline),
            const SizedBox(width: 12),
            const Expanded(child: Text('No logistics partners are accepting riders right now. Please check again later.')),
            IconButton(tooltip: 'Check again', onPressed: _reloadOptions, icon: const Icon(Icons.refresh)),
          ]),
        )
      else
        DropdownButtonFormField<int>(
          initialValue: _partnerId,
          isExpanded: true,
          decoration: zayloInputDecoration('Select a logistics partner', errorText: _errors['logistics_provider_id']),
          items: [
            for (final partner in options.partners)
              DropdownMenuItem(
                value: partner.id,
                child: Text(
                  [partner.name, if (partner.serviceArea?.isNotEmpty ?? false) partner.serviceArea].join(' · '),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
          ],
          onChanged: (value) => setState(() => _partnerId = value),
          validator: (value) => value == null ? 'Choose the logistics partner you are applying to.' : null,
        ),
      const SizedBox(height: 28),
      const _SectionTitle('Vehicle'),
      const FieldLabel('Vehicle type'),
      DropdownButtonFormField<String>(
        initialValue: _vehicleType,
        isExpanded: true,
        decoration: zayloInputDecoration('Select your vehicle type', errorText: _errors['vehicle_type']),
        items: [for (final type in options.vehicleTypes) DropdownMenuItem(value: type, child: Text(type))],
        onChanged: (value) => setState(() => _vehicleType = value),
        validator: (value) => value == null ? 'Select your vehicle type.' : null,
      ),
      if (!licensed) ...[
        const SizedBox(height: 12),
        Align(
          alignment: Alignment.centerLeft,
          child: Text("Bicycles need no driver's license, plate number or OR/CR.",
              style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
        ),
      ] else ...[
        const SizedBox(height: 18),
        const FieldLabel('Plate number'),
        TextFormField(
          controller: _plateNumber,
          textCapitalization: TextCapitalization.characters,
          keyboardType: TextInputType.visiblePassword, // letters and digits, no autocorrect
          autocorrect: false,
          inputFormatters: riderPlateInputFormatters,
          decoration: zayloInputDecoration('NBC1234', errorText: _errors['plate_number'])
              .copyWith(helperText: '5 to 7 letters and numbers'),
          validator: riderPlateNumberError,
        ),
        const SizedBox(height: 18),
        const FieldLabel('Vehicle OR/CR (photo or PDF)'),
        DocumentUploadField(
          label: 'vehicle OR/CR',
          initialValue: _orCrImage,
          serverError: _errors['or_cr_image'],
          onChanged: (file) => _orCrImage = file,
        ),
        const SizedBox(height: 28),
        const _SectionTitle("Driver's license"),
        const FieldLabel("Driver's license number"),
        TextFormField(
          controller: _licenseNumber,
          textCapitalization: TextCapitalization.characters,
          keyboardType: TextInputType.visiblePassword, // letters and digits, no autocorrect
          autocorrect: false,
          inputFormatters: riderLicenseInputFormatters,
          decoration: zayloInputDecoration(riderLicenseFormat, errorText: _errors['license_number'])
              .copyWith(helperText: 'Format: $riderLicenseFormat (dashes are added for you)'),
          validator: riderLicenseNumberError,
        ),
        const SizedBox(height: 18),
        const FieldLabel("Driver's license (photo or PDF)"),
        DocumentUploadField(
          label: "driver's license",
          initialValue: _licenseImage,
          serverError: _errors['license_image'],
          onChanged: (file) => _licenseImage = file,
        ),
        const SizedBox(height: 18),
        const FieldLabel('License expiry date'),
        TextFormField(
          controller: _licenseExpiryText,
          readOnly: true,
          onTap: _pickLicenseExpiry,
          decoration: zayloInputDecoration('Select the expiry date', errorText: _errors['license_expires_on'])
              .copyWith(suffixIcon: const Icon(Icons.event_outlined)),
          validator: (_) => _licenseExpiry == null ? 'Select your license expiry date.' : null,
        ),
      ],
    ]);
  }
}

/// Shown after the application is uploaded.
class _ApplicationSubmitted extends StatelessWidget {
  const _ApplicationSubmitted({required this.email, required this.onDone, this.partner});

  final String email;

  /// The logistics partner reviewing the application.
  final String? partner;
  final VoidCallback onDone;

  @override
  Widget build(BuildContext context) => Column(children: [
        CircleAvatar(
          radius: 38,
          backgroundColor: ZayloColors.successSoft,
          child: Icon(Icons.check_rounded, size: 42, color: ZayloColors.success),
        ),
        const SizedBox(height: 20),
        Text('Application successful',
            style: Theme.of(context).textTheme.headlineSmall, textAlign: TextAlign.center),
        const SizedBox(height: 10),
        Text(
          '${partner ?? 'Your logistics partner'} will review your application. '
          'We will email $email once it is approved.',
          style: Theme.of(context).textTheme.bodyMedium,
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 18),
        const SectionCard(
          child: Row(children: [
            Icon(Icons.schedule_outlined),
            SizedBox(width: 12),
            Expanded(
              child: Text('You can sign in as a rider after approval. We will ask you to confirm your email '
                  'with a code the first time you sign in.'),
            ),
          ]),
        ),
        const SizedBox(height: 25),
        PrimaryButton(label: 'BACK TO HOME', onPressed: onDone),
      ]);
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.title);

  final String title;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 14),
        child: Row(children: [
          Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(width: 12),
          const Expanded(child: Divider()),
        ]),
      );
}
