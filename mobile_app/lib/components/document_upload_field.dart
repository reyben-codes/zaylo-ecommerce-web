import 'dart:io';

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/components/file_picking.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/rider_validators.dart';

/// A required document (driver's license, OR/CR, government ID): a camera
/// photo, a gallery image, or a file. Type and the 5.0 MB limit are checked
/// the moment a file is picked, so the problem shows right away. Rider
/// documents use the rider sign-up rules unless a [validator] is given.
class DocumentUploadField extends FormField<File> {
  DocumentUploadField({
    super.key,
    required String label,
    String? serverError,
    super.initialValue,
    ValueChanged<File>? onChanged,
    FormFieldValidator<File>? validator,
    List<String> extensions = riderDocumentExtensions,
    String hint = 'JPG, PNG, or PDF up to 5.0 MB',
  }) : super(
          validator: validator ?? (file) => riderDocumentError(file, label: label),
          builder: (field) => _DocumentUploadPicker(
            label: label,
            hint: hint,
            extensions: extensions,
            file: field.value,
            errorText: field.errorText ?? serverError,
            onPicked: (file) {
              field.didChange(file);
              field.validate();
              onChanged?.call(file);
            },
          ),
        );
}

class _DocumentUploadPicker extends StatelessWidget {
  const _DocumentUploadPicker({
    required this.label,
    required this.hint,
    required this.extensions,
    required this.file,
    required this.errorText,
    required this.onPicked,
  });

  final String label;
  final String hint;
  final List<String> extensions;
  final File? file;
  final String? errorText;
  final ValueChanged<File> onPicked;

  bool get _isPdf => file != null && file!.path.toLowerCase().endsWith('.pdf');

  Future<void> _choose(BuildContext context) async {
    final picked = await pickPhotoOrFile(context, fileExtensions: extensions, fileHint: hint);
    if (picked != null) onPicked(picked);
  }

  @override
  Widget build(BuildContext context) {
    final hasError = errorText != null;
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Material(
        color: ZayloColors.paper,
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: () => _choose(context),
          child: Container(
            height: 150,
            width: double.infinity,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: hasError ? Colors.redAccent : ZayloColors.line),
            ),
            clipBehavior: Clip.antiAlias,
            child: switch (file) {
              null => _EmptyPrompt(label: label, hint: hint),
              _ when _isPdf => _PdfPreview(file: file!),
              _ => Stack(fit: StackFit.expand, children: [
                  Image.file(
                    file!,
                    fit: BoxFit.cover,
                    semanticLabel: label,
                    errorBuilder: (_, __, ___) => const Center(child: Icon(Icons.broken_image_outlined)),
                  ),
                  const Positioned(right: 8, bottom: 8, child: _ChangeBadge()),
                ]),
            },
          ),
        ),
      ),
      if (hasError)
        Padding(
          padding: const EdgeInsets.only(top: 6, left: 12),
          child: Text(errorText!, style: const TextStyle(color: Colors.redAccent, fontSize: 12)),
        ),
    ]);
  }
}

class _EmptyPrompt extends StatelessWidget {
  const _EmptyPrompt({required this.label, required this.hint});

  final String label;
  final String hint;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          Icon(Icons.add_a_photo_outlined, size: 30, color: ZayloColors.muted),
          const SizedBox(height: 8),
          Text('Upload $label', style: const TextStyle(fontWeight: FontWeight.w600), textAlign: TextAlign.center),
          const SizedBox(height: 2),
          Text(hint,
              style: TextStyle(fontSize: 12, color: ZayloColors.muted), textAlign: TextAlign.center),
        ]),
      );
}

class _PdfPreview extends StatelessWidget {
  const _PdfPreview({required this.file});

  final File file;

  @override
  Widget build(BuildContext context) {
    final megabytes = (file.lengthSync() / (1024 * 1024)).toStringAsFixed(1);
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Row(children: [
        const Icon(Icons.picture_as_pdf_outlined, size: 40, color: Colors.redAccent),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(file.uri.pathSegments.last,
                    maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600)),
                const SizedBox(height: 4),
                Text('PDF · $megabytes MB', style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
              ]),
        ),
        const _ChangeBadge(),
      ]),
    );
  }
}

class _ChangeBadge extends StatelessWidget {
  const _ChangeBadge();

  @override
  Widget build(BuildContext context) => DecoratedBox(
        decoration: BoxDecoration(color: ZayloColors.ink, borderRadius: BorderRadius.circular(20)),
        child: Padding(
          padding: EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          child: Text('Change', style: TextStyle(color: ZayloColors.white, fontSize: 12)),
        ),
      );
}
