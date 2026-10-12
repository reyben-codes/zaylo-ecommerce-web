import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/zaylo_theme.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/appearance_picker.dart';
import '../../core/widgets/buttons.dart';
import '../../core/widgets/feedback.dart';
import '../../core/widgets/layout.dart';
import '../api/rider_api.dart';
import '../models/rider_dashboard_models.dart';
import '../models/rider_models.dart';
import '../utils/rider_input_formatters.dart';
import '../utils/rider_validators.dart';
import '../widgets/rider_dashboard_widgets.dart';
import 'rider_address_screen.dart';
import 'rider_change_password_screen.dart';

/// Account management: the rider's details, vehicle and license, mobile
/// number, password, and sign out. Identity and vehicle details were verified
/// during approval, so changes to those go through support chat.
class RiderAccountTab extends StatefulWidget {
  const RiderAccountTab({super.key, required this.api, required this.onSignOut, required this.onContactSupport});

  final RiderApi api;
  final Future<void> Function() onSignOut;
  final VoidCallback onContactSupport;

  @override
  State<RiderAccountTab> createState() => _RiderAccountTabState();
}

class _RiderAccountTabState extends State<RiderAccountTab> {
  late Future<RiderProfile> _profile = _fetch();
  bool _signingOut = false;

  Future<RiderProfile> _fetch() async => (await widget.api.dashboard()).rider;

  Future<void> _reload() async {
    final profile = _fetch();
    setState(() {
      _profile = profile;
    });
    try {
      await profile;
    } on Exception {
      // Shown by the FutureBuilder's error state.
    }
  }

  Future<void> _editPhone(RiderProfile rider) async {
    final saved = await showDialog<String>(context: context, builder: (_) => _PhoneDialog(api: widget.api, current: rider.phone));
    if (saved == null || !mounted) return;
    showMessage(context, 'Mobile number updated.');
    await _reload();
  }

  Future<void> _editGender(RiderProfile rider) async {
    final chosen = await showDialog<String>(
      context: context,
      builder: (dialogContext) => SimpleDialog(
        title: const Text('Gender'),
        children: [
          for (final entry in riderGenders.entries)
            ListTile(
              leading: Icon(rider.gender == entry.key ? Icons.radio_button_checked : Icons.radio_button_off),
              title: Text(entry.value),
              onTap: () => Navigator.pop(dialogContext, entry.key),
            ),
        ],
      ),
    );
    if (chosen == null || chosen == rider.gender || !mounted) return;
    try {
      await widget.api.updateGender(chosen);
      if (!mounted) return;
      showMessage(context, 'Gender updated.');
      await _reload();
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    }
  }

  Future<void> _editAddress(RiderProfile rider) async {
    final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(
      builder: (_) => RiderAddressScreen(api: widget.api, initial: rider.address?.value),
    ));
    if (saved == true && mounted) await _reload();
  }

  Future<void> _confirmSignOut() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Sign out?'),
        content: const Text('You will need to sign in again to see and accept deliveries.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('Sign out')),
        ],
      ),
    );
    if (confirmed != true) return;
    setState(() => _signingOut = true);
    await widget.onSignOut();
    if (mounted) setState(() => _signingOut = false);
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<RiderProfile>(
        future: _profile,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done && !snapshot.hasData) return const Loader();
          if (snapshot.hasError) {
            return LoadError(error: snapshot.error, onRetry: _reload, title: 'Could not load your account');
          }
          final rider = snapshot.requireData;
          return RefreshIndicator(
            color: ZayloColors.ink,
            onRefresh: _reload,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: EdgeInsets.fromLTRB(20, 12, 20, 32 + MediaQuery.paddingOf(context).bottom),
              children: [
                Row(children: [
                  CircleAvatar(
                    radius: 30,
                    backgroundColor: ZayloColors.ink,
                    foregroundColor: ZayloColors.white,
                    child: Text(_initials(rider.name), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(rider.name, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
                      Text(rider.email, style: TextStyle(color: ZayloColors.muted)),
                    ]),
                  ),
                ]),
                if (rider.licenseExpiringSoon) ...[
                  const SizedBox(height: 16),
                  LicenseWarningBanner(rider: rider),
                ],
                const _Heading('Vehicle and license'),
                SectionCard(
                  padding: EdgeInsets.zero,
                  child: Column(children: [
                    _Row(icon: Icons.two_wheeler_outlined, label: 'Vehicle', value: rider.vehicleLabel ?? '—'),
                    _Row(icon: Icons.pin_outlined, label: 'Plate number', value: rider.plateNumber ?? '—'),
                    _Row(icon: Icons.badge_outlined, label: 'License number', value: rider.licenseNumber ?? '—'),
                    _Row(
                      icon: Icons.event_outlined,
                      label: 'License expires',
                      value: rider.licenseExpiresOn == null ? '—' : shortDate(rider.licenseExpiresOn),
                    ),
                  ]),
                ),
                const _Heading('Personal details'),
                SectionCard(
                  padding: EdgeInsets.zero,
                  child: Column(children: [
                    _Row(
                      icon: Icons.wc_outlined,
                      label: 'Gender',
                      value: rider.genderLabel ?? 'Not set',
                      action: 'Edit',
                      onTap: () => _editGender(rider),
                    ),
                    _Row(
                      icon: Icons.home_outlined,
                      label: 'Home address',
                      value: rider.address?.formatted ?? 'Add your home address',
                      action: rider.address == null ? 'Add' : 'Edit',
                      onTap: () => _editAddress(rider),
                    ),
                  ]),
                ),
                const _Heading('Contact details'),
                SectionCard(
                  padding: EdgeInsets.zero,
                  child: Column(children: [
                    _Row(
                      icon: Icons.phone_iphone,
                      label: 'Mobile number',
                      value: rider.phone ?? 'Not set',
                      action: 'Edit',
                      onTap: () => _editPhone(rider),
                    ),
                    _Row(icon: Icons.mail_outline, label: 'Email', value: rider.email),
                  ]),
                ),
                const _Heading('Security'),
                SectionCard(
                  padding: EdgeInsets.zero,
                  child: _Row(
                    icon: Icons.lock_outline,
                    label: 'Password',
                    value: 'Change your password',
                    action: 'Change',
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => RiderChangePasswordScreen(api: widget.api),
                    )),
                  ),
                ),
                const SizedBox(height: 16),
                const SectionCard(padding: EdgeInsets.zero, child: AppearancePicker()),
                const SizedBox(height: 16),
                SectionCard(
                  padding: EdgeInsets.zero,
                  child: ListTile(
                    leading: const Icon(Icons.support_agent_outlined),
                    title: const Text('Need to change your name, email, license, or vehicle?'),
                    subtitle: const Text('Message ZAYLO support and we will update it after checking your documents.'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: widget.onContactSupport,
                  ),
                ),
                const SizedBox(height: 28),
                OutlinedButton.icon(
                  onPressed: _signingOut ? null : _confirmSignOut,
                  style: secondaryButtonStyle.copyWith(
                    foregroundColor: WidgetStatePropertyAll(ZayloColors.danger),
                    side: WidgetStatePropertyAll(BorderSide(color: ZayloColors.danger)),
                  ),
                  icon: _signingOut
                      ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Icon(Icons.logout),
                  label: const Text('SIGN OUT'),
                ),
              ],
            ),
          );
        },
      );

  static String _initials(String name) {
    final parts = name.trim().split(RegExp(r'\s+')).where((part) => part.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    return (parts.first[0] + (parts.length > 1 ? parts.last[0] : '')).toUpperCase();
  }
}

class _Heading extends StatelessWidget {
  const _Heading(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 24, bottom: 10),
        child: Text(text, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
      );
}

class _Row extends StatelessWidget {
  const _Row({required this.icon, required this.label, required this.value, this.action, this.onTap});

  final IconData icon;
  final String label;
  final String value;
  final String? action;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => ListTile(
        leading: Icon(icon),
        title: Text(label, style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
        subtitle: Text(value, style: TextStyle(fontSize: 15, color: ZayloColors.ink, fontWeight: FontWeight.w600)),
        trailing: action == null ? null : Text(action!, style: const TextStyle(fontWeight: FontWeight.w700)),
        onTap: onTap,
      );
}

/// Edits the mobile number with the same rules as sign-up; pops the saved number.
class _PhoneDialog extends StatefulWidget {
  const _PhoneDialog({required this.api, this.current});

  final RiderApi api;
  final String? current;

  @override
  State<_PhoneDialog> createState() => _PhoneDialogState();
}

class _PhoneDialogState extends State<_PhoneDialog> {
  final _formKey = GlobalKey<FormState>();
  late final _phone = TextEditingController(text: widget.current ?? '');
  bool _saving = false;
  String? _serverError;

  @override
  void dispose() {
    _phone.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _serverError = null);
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _saving = true);
    try {
      final saved = await widget.api.updatePhone(_phone.text);
      if (mounted) Navigator.pop(context, saved);
    } on ApiException catch (error) {
      if (mounted) setState(() => _serverError = error.fieldErrors['phone'] ?? error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
        title: const Text('Mobile number'),
        content: Form(
          key: _formKey,
          child: TextFormField(
            controller: _phone,
            autofocus: true,
            keyboardType: TextInputType.number,
            inputFormatters: riderPhoneInputFormatters,
            autovalidateMode: AutovalidateMode.onUserInteraction,
            decoration: InputDecoration(hintText: '09171234567', errorText: _serverError, errorMaxLines: 2),
            validator: riderPhoneError,
          ),
        ),
        actions: [
          TextButton(onPressed: _saving ? null : () => Navigator.pop(context), child: const Text('Cancel')),
          TextButton(onPressed: _saving ? null : _save, child: Text(_saving ? 'Saving…' : 'Save')),
        ],
      );
}
