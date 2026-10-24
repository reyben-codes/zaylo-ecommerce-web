import 'dart:io';

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/file_picking.dart';
import 'package:zaylo_mobile/components/form_fields.dart';
import 'package:zaylo_mobile/models/buyer/order_models.dart';
import 'package:zaylo_mobile/models/support_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Files a complaint about an order, as on the website: what went wrong, a
/// description, and up to four photos or PDFs. Pops the new case.
class ComplaintFormScreen extends StatefulWidget {
  const ComplaintFormScreen({super.key, required this.order});

  final Order order;

  @override
  State<ComplaintFormScreen> createState() => _ComplaintFormScreenState();
}

class _ComplaintFormScreenState extends State<ComplaintFormScreen> {
  static const _maxFiles = 4;
  static const _minDetails = 20;

  final _formKey = GlobalKey<FormState>();
  final _details = TextEditingController();
  final List<File> _evidence = [];
  late Future<List<ComplaintCategory>> _categories;
  String? _category;
  bool _saving = false;
  Map<String, String> _serverErrors = const {};

  @override
  void initState() {
    super.initState();
    _categories = AppScope.read(context).api.complaintCategories();
  }

  @override
  void dispose() {
    _details.dispose();
    super.dispose();
  }

  Future<void> _addFile() async {
    final file = await pickPhotoOrFile(context, fileExtensions: uploadExtensions, fileHint: 'JPG, PNG, WebP or PDF up to 5.0 MB');
    if (file == null || !mounted) return;
    final problem = uploadFileError(file);
    if (problem != null) {
      showMessage(context, problem);
      return;
    }
    setState(() => _evidence.add(file));
  }

  Future<void> _submit() async {
    setState(() => _serverErrors = const {});
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _saving = true);
    try {
      final complaint = await AppScope.read(context)
          .api
          .fileComplaint(widget.order.id, category: _category!, details: _details.text, evidence: _evidence);
      if (!mounted) return;
      showMessage(context, 'Your complaint was filed. The ZAYLO team will reply in the case.');
      Navigator.of(context).pop(complaint);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _serverErrors = error.fieldErrors);
      if (error.fieldErrors.isEmpty) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Report a problem')),
        body: FutureBuilder<List<ComplaintCategory>>(
          future: _categories,
          builder: (context, snapshot) {
            if (snapshot.hasError) {
              return LoadError(
                error: snapshot.error,
                title: 'Could not open the form',
                onRetry: () => setState(() => _categories = AppScope.read(context).api.complaintCategories()),
              );
            }
            if (!snapshot.hasData) return const Loader();
            return Form(
              key: _formKey,
              child: ListView(padding: const EdgeInsets.all(ZayloSpace.page), children: [
                Text('Order ${widget.order.orderNumber}', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 4),
                const Text('The ZAYLO team reviews the case with the seller and the rider, and replies in the case thread.',
                    style: TextStyle(fontSize: 13)),
                const SizedBox(height: 20),
                const FieldLabel('What went wrong?'),
                DropdownButtonFormField<String>(
                  initialValue: _category,
                  isExpanded: true,
                  decoration: zayloInputDecoration('Choose one', errorText: _serverErrors['category']),
                  items: [
                    for (final category in snapshot.requireData)
                      DropdownMenuItem(value: category.id, child: Text(category.label)),
                  ],
                  validator: (value) => value == null ? 'Choose what went wrong.' : null,
                  onChanged: _saving ? null : (value) => setState(() => _category = value),
                ),
                const SizedBox(height: 16),
                const FieldLabel('Describe the problem'),
                TextFormField(
                  controller: _details,
                  minLines: 4,
                  maxLines: 8,
                  maxLength: 2000,
                  enabled: !_saving,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: zayloInputDecoration('What happened, and what would you like done?',
                      errorText: _serverErrors['details']),
                  validator: (value) {
                    final text = value?.trim() ?? '';
                    if (text.isEmpty) return 'Describe the problem.';
                    if (text.length < _minDetails) {
                      return 'Describe the problem in at least $_minDetails characters so the team can act on it.';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 8),
                FieldLabel('Photos or PDFs (optional, up to $_maxFiles)'),
                for (final (index, file) in _evidence.indexed)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(file.path.toLowerCase().endsWith('.pdf') ? Icons.picture_as_pdf_outlined : Icons.image_outlined),
                    title: Text(file.uri.pathSegments.last, maxLines: 1, overflow: TextOverflow.ellipsis),
                    trailing: IconButton(
                      tooltip: 'Remove file ${index + 1}',
                      onPressed: _saving ? null : () => setState(() => _evidence.removeAt(index)),
                      icon: const Icon(Icons.close),
                    ),
                  ),
                if (_evidence.length < _maxFiles)
                  Align(
                    alignment: Alignment.centerLeft,
                    child: OutlinedButton.icon(
                      onPressed: _saving ? null : _addFile,
                      icon: const Icon(Icons.attach_file),
                      label: const Text('Add photo or PDF'),
                    ),
                  ),
                if (_serverErrors.entries.where((entry) => entry.key.startsWith('evidence')).firstOrNull case final error?)
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text(error.value, style: const TextStyle(color: Colors.redAccent, fontSize: 12)),
                  ),
                const SizedBox(height: 26),
                PrimaryButton(label: 'SUBMIT COMPLAINT', busy: _saving, onPressed: _submit),
                const SizedBox(height: 8),
                Text('You can file one complaint per order and add details in its thread later.',
                    textAlign: TextAlign.center, style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
              ]),
            );
          },
        ),
      );
}
