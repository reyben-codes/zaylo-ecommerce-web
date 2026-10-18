import 'dart:io';

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/document_upload_field.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/file_picking.dart';
import 'package:zaylo_mobile/components/form_fields.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/models/buyer/account_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

/// ID verification, as in the website's Profile Settings: buyers upload a
/// government ID, and an administrator approves it before they can order.
class IdentityScreen extends StatefulWidget {
  const IdentityScreen({super.key});

  @override
  State<IdentityScreen> createState() => _IdentityScreenState();
}

class _IdentityScreenState extends State<IdentityScreen> {
  final _formKey = GlobalKey<FormState>();
  IdentityStatus? _status;
  Object? _error;
  String? _idType;
  File? _document;
  bool _saving = false;

  /// A pending upload can be replaced; the form stays closed until asked.
  bool _replacing = false;
  Map<String, String> _serverErrors = const {};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    try {
      final status = await AppScope.read(context).api.identity();
      if (mounted) {
        setState(() {
          _status = status;
          _error = null;
        });
      }
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error);
    }
  }

  Future<void> _submit() async {
    setState(() => _serverErrors = const {});
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _saving = true);
    try {
      final status = await AppScope.read(context).api.submitIdentity(idType: _idType!, document: _document!);
      if (!mounted) return;
      setState(() {
        _status = status;
        _document = null;
        _replacing = false;
      });
      showMessage(context, 'ID uploaded. An administrator will review it soon; you can keep shopping meanwhile.');
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _serverErrors = error.fieldErrors);
      if (error.fieldErrors.isEmpty) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final status = _status;
    return Scaffold(
      appBar: AppBar(title: const Text('ID verification')),
      body: status == null
          ? (_error != null ? LoadError(error: _error, onRetry: _load, title: 'Could not load your ID status') : const Loader())
          : ListView(padding: const EdgeInsets.all(ZayloSpace.page), children: [
              _StatusCard(status: status),
              if (!status.verified && status.enabled) ...[
                const SizedBox(height: 22),
                if (status.pending && !_replacing)
                  OutlinedButton(
                    onPressed: () => setState(() => _replacing = true),
                    style: secondaryButtonStyle,
                    child: const Text('REPLACE MY UPLOAD'),
                  )
                else
                  _form(status),
              ],
            ]),
    );
  }

  Widget _form(IdentityStatus status) => Form(
        key: _formKey,
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const FieldLabel('ID type'),
          DropdownButtonFormField<String>(
            initialValue: _idType,
            isExpanded: true,
            decoration: zayloInputDecoration('Choose your ID', errorText: _serverErrors['id_type']),
            items: [for (final type in status.idTypes) DropdownMenuItem(value: type.id, child: Text(type.label))],
            validator: (value) => value == null ? 'Choose the type of ID you are uploading.' : null,
            onChanged: _saving ? null : (value) => setState(() => _idType = value),
          ),
          const SizedBox(height: 16),
          const FieldLabel('Photo or PDF of your ID'),
          DocumentUploadField(
            label: 'your ID',
            hint: 'JPG, PNG, WebP or PDF up to 5.0 MB',
            extensions: uploadExtensions,
            serverError: _serverErrors['document'],
            validator: (file) => file == null ? 'Choose a photo or PDF of your ID.' : uploadFileError(file),
            onChanged: (file) => _document = file,
          ),
          const SizedBox(height: 10),
          Text('Make sure your name, photo and ID number are clear. Only ZAYLO administrators can see this file.',
              style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
          const SizedBox(height: 22),
          PrimaryButton(label: 'SUBMIT ID FOR REVIEW', busy: _saving, onPressed: _submit),
        ]),
      );
}

class _StatusCard extends StatelessWidget {
  const _StatusCard({required this.status});

  final IdentityStatus status;

  @override
  Widget build(BuildContext context) {
    final submission = status.submission;
    final (IconData icon, Color color, String title, String message) = switch (status) {
      IdentityStatus(verified: true) => (
          Icons.verified_outlined,
          ZayloColors.success,
          'ID verified',
          'You can place orders. Thank you for helping keep ZAYLO safe.',
        ),
      IdentityStatus(enabled: false) => (
          Icons.info_outline,
          ZayloColors.info,
          'ID checks are not active yet',
          'You can place orders without uploading an ID for now.',
        ),
      IdentityStatus(pending: true) => (
          Icons.hourglass_top_rounded,
          ZayloColors.warning,
          'Under review',
          'An administrator is checking your ${submission!.idTypeLabel}, uploaded ${shortDate(submission.submittedAt)}. '
              'You can keep shopping meanwhile.',
        ),
      IdentityStatus(rejected: true) => (
          Icons.error_outline,
          ZayloColors.danger,
          'ID not approved',
          submission!.rejectionReason?.trim().isNotEmpty == true
              ? 'Reason: ${submission.rejectionReason}. Upload a clearer photo or a different ID.'
              : 'Upload a clearer photo or a different ID.',
        ),
      _ => (
          Icons.badge_outlined,
          ZayloColors.ink,
          'ID not verified yet',
          'Upload a government ID once. After an administrator approves it, you can place orders.',
        ),
    };
    return SectionCard(
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, color: color, size: 28),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: ZayloColors.ink)),
            const SizedBox(height: 4),
            Text(message, style: const TextStyle(fontSize: 13)),
          ]),
        ),
      ]),
    );
  }
}
