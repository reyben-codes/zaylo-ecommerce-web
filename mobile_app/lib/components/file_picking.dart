import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Photos and files the server accepts for evidence, IDs and attachments.
const uploadExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
const uploadMaxBytes = 5 * 1024 * 1024; // 5.0 MB, the server's max:5120

enum _Source { camera, gallery, file }

/// Asks for a camera photo, a gallery image, or (when [fileExtensions] is
/// given) a file, and returns it. Photos are resized and saved as JPG, which
/// keeps them well under 5.0 MB. Returns null when the user cancels.
Future<File?> pickPhotoOrFile(
  BuildContext context, {
  List<String>? fileExtensions,
  String fileHint = 'JPG, PNG, or PDF up to 5.0 MB',
}) async {
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
        if (fileExtensions != null)
          ListTile(
            leading: const Icon(Icons.upload_file_outlined),
            title: const Text('Choose a file'),
            subtitle: Text(fileHint),
            onTap: () => Navigator.pop(sheetContext, _Source.file),
          ),
      ]),
    ),
  );
  if (source == null || !context.mounted) return null;

  try {
    final path = switch (source) {
      _Source.camera || _Source.gallery => (await ImagePicker().pickImage(
          source: source == _Source.camera ? ImageSource.camera : ImageSource.gallery,
          maxWidth: 2000,
          maxHeight: 2000,
          imageQuality: 80,
        ))
            ?.path,
      _Source.file => (await FilePicker.pickFile(type: FileType.custom, allowedExtensions: fileExtensions))?.path,
    };
    return path == null ? null : File(path);
  } on Exception catch (error) {
    // Usually a denied camera, photo, or storage permission.
    debugPrint('[ZAYLO] Picker (${source.name}) failed: $error');
    if (context.mounted) showMessage(context, 'Could not open the ${source.name}. Check the app\'s permissions.');
    return null;
  }
}

/// The server's rules for uploads: JPG, PNG, WebP or PDF, at most 5.0 MB.
String? uploadFileError(File file, {List<String> extensions = uploadExtensions}) {
  final extension = file.path.split('.').last.toLowerCase();
  if (!extensions.contains(extension)) {
    return extensions.contains('pdf') ? 'Use a photo (JPG, PNG, WebP) or a PDF.' : 'Use a JPG, PNG, or WebP photo.';
  }
  if (file.lengthSync() > uploadMaxBytes) return 'Each file must be 5.0 MB or smaller.';
  return null;
}
