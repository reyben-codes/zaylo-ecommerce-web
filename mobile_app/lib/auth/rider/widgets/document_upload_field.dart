import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/rider_validators.dart';

/// A required document (driver's license, OR/CR): a camera photo, a gallery
/// image, or a JPG/PNG/PDF file. Type and the 5.0 MB limit are checked the
/// moment a file is picked, so the rider sees the problem right away.
class DocumentUploadField extends FormField<File> {
  DocumentUploadField({
    super.key,
    required String label,
    String? serverError,
    super.initialValue,
    ValueChanged<File>? onChanged,
  }) : super(
          validator: (file) => riderDocumentError(file, label: label),
          builder: (field) => _DocumentUploadPicker(
            label: label,
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

enum _Source { camera, gallery, file }

class _DocumentUploadPicker extends StatelessWidget {
  const _DocumentUploadPicker(
      {required this.label, required this.file, required this.errorText, required this.onPicked});

  final String label;
  final File? file;
  final String? errorText;
  final ValueChanged<File> onPicked;

  bool get _isPdf => file != null && file!.path.toLowerCase().endsWith('.pdf');

  Future<void> _choose(BuildContext context) async {
    final source = await showModalBottomSheet<_Source>(
      context: context,
      backgroundColor: ZayloColors.white,
      builder: (sheetContext) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          ListTile(
            leading: const Icon(Icons.photo_camera_outlined),
            title: const Text('Take photo'),
            onTap: () => Navigator.pop(sheetContext, _Source.camera),
          ),
          ListTile(
            leading: const Icon(Icons.photo_library_outlined),
            title: const Text('Choose from gallery'),
            onTap: () => Navigator.pop(sheetContext, _Source.gallery),
          ),
          ListTile(
            leading: const Icon(Icons.upload_file_outlined),
            title: const Text('Choose a file'),
            subtitle: const Text('JPG, PNG, or PDF up to 5.0 MB'),
            onTap: () => Navigator.pop(sheetContext, _Source.file),
          ),
        ]),
      ),
    );
    if (source == null || !context.mounted) return;

    try {
      final path = switch (source) {
        // Photos are resized and saved as JPG, which keeps them well under 5.0 MB.
        _Source.camera || _Source.gallery => (await ImagePicker().pickImage(
            source: source == _Source.camera ? ImageSource.camera : ImageSource.gallery,
            maxWidth: 2000,
            maxHeight: 2000,
            imageQuality: 80,
          ))
              ?.path,
        _Source.file => (await FilePicker.pickFile(
            type: FileType.custom,
            allowedExtensions: riderDocumentExtensions,
          ))
              ?.path,
      };
      if (path != null) onPicked(File(path));
    } on Exception catch (error) {
      // Usually a denied camera, photo, or storage permission.
      debugPrint('[ZAYLO] Document picker (${source.name}) failed: $error');
      if (context.mounted) showMessage(context, 'Could not open the ${source.name}. Check the app\'s permissions.');
    }
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
              null => _EmptyPrompt(label: label),
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
  const _EmptyPrompt({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          Icon(Icons.add_a_photo_outlined, size: 30, color: ZayloColors.muted),
          const SizedBox(height: 8),
          Text('Upload $label', style: const TextStyle(fontWeight: FontWeight.w600), textAlign: TextAlign.center),
          const SizedBox(height: 2),
          Text('JPG, PNG, or PDF up to 5.0 MB',
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
