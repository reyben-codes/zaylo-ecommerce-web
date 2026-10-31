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
import 'package:zaylo_mobile/utils/formatters.dart';

/// Files a complaint about an order, as on the website: what went wrong, a
/// description, and up to four photos or PDFs. A delivered order can also be
/// sent back: a rider collects it on the chosen day, or the buyer drops it off
/// at the logistics hub. Pops the new case.
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

  /// Sending the item back, for delivered orders only.
  bool _returning = false;
  String _returnType = 'return_refund';
  String _returnMethod = 'pickup';
  DateTime _pickupDate = DateUtils.dateOnly(DateTime.now()).add(const Duration(days: 1));
  String _pickupSlot = 'morning';

  bool get _canReturn => widget.order.canReturn || (const {'delivered', 'completed'}.contains(widget.order.status) && widget.order.returnDeadline == null);
  bool get _isReturnExpired => widget.order.returnDeadline != null && !widget.order.canReturn;

  Future<void> _choosePickupDate() async {
    final today = DateUtils.dateOnly(DateTime.now());
    final picked = await showDatePicker(
      context: context,
      initialDate: _pickupDate,
      firstDate: today,
      lastDate: today.add(const Duration(days: 14)),
      helpText: 'Pickup day',
    );
    if (picked != null && mounted) setState(() => _pickupDate = picked);
  }

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
    if (_isReturnExpired && const {'damaged', 'wrong_item'}.contains(_category)) {
      showMessage(context, 'Return requests for damaged or incorrect items must be filed within 24 hours of delivery. The return window has expired.');
      return;
    }
    setState(() => _saving = true);
    try {
      final returning = _canReturn && _returning;
      final complaint = await AppScope.read(context).api.fileComplaint(
            widget.order.id,
            category: _category!,
            details: _details.text,
            evidence: _evidence,
            returnType: returning ? _returnType : null,
            returnMethod: returning ? _returnMethod : null,
            pickupDate: returning ? _pickupDate : null,
            pickupSlot: returning ? _pickupSlot : null,
          );
      if (!mounted) return;
      showMessage(
          context,
          returning
              ? 'Your return request was filed. Follow the pickup and refund in the case.'
              : 'Your complaint was filed. The ZAYLO team will reply in the case.');
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
                if (_canReturn && widget.order.returnDeadline != null)
                  Container(
                    margin: const EdgeInsets.only(bottom: 14),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFEFCE8),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: const Color(0xFFFEF08A)),
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(Icons.history_toggle_off, size: 20, color: Color(0xFF854D0E)),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            '24-Hour Return Window Active: Damaged, defective, or incorrect items can be returned until ${dateTimeLabel(widget.order.returnDeadline!.toLocal())}.',
                            style: const TextStyle(fontSize: 12.5, color: Color(0xFF854D0E), fontWeight: FontWeight.w600),
                          ),
                        ),
                      ],
                    ),
                  )
                else if (_isReturnExpired && widget.order.returnDeadline != null)
                  Container(
                    margin: const EdgeInsets.only(bottom: 14),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(Icons.info_outline, size: 20, color: Color(0xFF64748B)),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            'The 24-hour return window for damaged or wrong products closed on ${dateTimeLabel(widget.order.returnDeadline!.toLocal())}. Non-delivery or payment issues can still be reported below.',
                            style: const TextStyle(fontSize: 12.5, color: Color(0xFF64748B)),
                          ),
                        ),
                      ],
                    ),
                  ),
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
                      DropdownMenuItem(
                        value: category.id,
                        enabled: !(_isReturnExpired && const {'damaged', 'wrong_item'}.contains(category.id)),
                        child: Text(
                          _isReturnExpired && const {'damaged', 'wrong_item'}.contains(category.id)
                              ? '${category.label} (24h closed)'
                              : category.label,
                          style: TextStyle(
                            color: _isReturnExpired && const {'damaged', 'wrong_item'}.contains(category.id)
                                ? ZayloColors.muted
                                : null,
                          ),
                        ),
                      ),
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
                if (_canReturn) ...[
                  const SizedBox(height: 16),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    value: _returning,
                    onChanged: _saving ? null : (value) => setState(() => _returning = value),
                    title: const Text('Send the item back'),
                    subtitle: const Text('Ask for a refund or a replacement and return the item to the seller.'),
                  ),
                  if (_returning) ..._returnFields(),
                ],
                const SizedBox(height: 26),
                PrimaryButton(
                    label: _canReturn && _returning ? 'REQUEST RETURN' : 'SUBMIT COMPLAINT', busy: _saving, onPressed: _submit),
                const SizedBox(height: 8),
                Text('You can file one complaint per order and add details in its thread later.',
                    textAlign: TextAlign.center, style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
              ]),
            );
          },
        ),
      );

  List<Widget> _returnFields() {
    Widget choices(Map<String, String> options, String selected, ValueChanged<String> onSelected) => Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final MapEntry(:key, :value) in options.entries)
              ChoiceChip(
                label: Text(value),
                selected: selected == key,
                onSelected: _saving ? null : (_) => onSelected(key),
              ),
          ],
        );

    return [
      const SizedBox(height: 4),
      const FieldLabel('What would you like?'),
      choices(const {'return_refund': 'Refund', 'exchange': 'Replacement'}, _returnType,
          (value) => setState(() => _returnType = value)),
      const SizedBox(height: 16),
      const FieldLabel('How will you send it back?'),
      choices(const {'pickup': 'A rider picks it up', 'dropoff': 'I drop it off at the hub'}, _returnMethod,
          (value) => setState(() => _returnMethod = value)),
      const SizedBox(height: 12),
      if (_returnMethod == 'pickup') ...[
        OutlinedButton.icon(
          onPressed: _saving ? null : _choosePickupDate,
          icon: const Icon(Icons.event_outlined),
          label: Text('Pickup day: ${MaterialLocalizations.of(context).formatMediumDate(_pickupDate)}'),
        ),
        if (_serverErrors['pickup_date'] case final error?)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Text(error, style: const TextStyle(color: Colors.redAccent, fontSize: 12)),
          ),
        const SizedBox(height: 10),
        choices(const {'morning': 'Morning, 9 AM–1 PM', 'afternoon': 'Afternoon, 1–6 PM'}, _pickupSlot,
            (value) => setState(() => _pickupSlot = value)),
        const SizedBox(height: 8),
        Text('The rider scans your return slip when collecting the item, so keep the case open to show it.',
            style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
      ] else
        Text('Bring the item to your logistics hub within 3 days and show the return slip from the case.',
            style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
    ];
  }
}
