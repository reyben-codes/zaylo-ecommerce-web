import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import 'package:zaylo_mobile/api/api_client.dart';
import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/profile_photo_crop_screen.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// A round profile photo, or the person's initials when there is none (or it fails to load).
class ProfileAvatar extends StatelessWidget {
  const ProfileAvatar({super.key, required this.name, this.photoUrl, this.radius = 28});

  final String name;
  final String? photoUrl;
  final double radius;

  static String initialsOf(String name) {
    final parts = name.trim().split(RegExp(r'\s+')).where((part) => part.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    return (parts.first[0] + (parts.length > 1 ? parts.last[0] : '')).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final initials = Center(
      child: Text(initialsOf(name),
          style: TextStyle(color: ZayloColors.white, fontWeight: FontWeight.w700, fontSize: radius * .64)),
    );
    final size = radius * 2;
    return SizedBox.square(
      dimension: size,
      child: ClipOval(
        child: ColoredBox(
          color: ZayloColors.ink,
          child: photoUrl == null
              ? initials
              : Image.network(
                  photoUrl!,
                  width: size,
                  height: size,
                  fit: BoxFit.cover,
                  cacheWidth: (size * MediaQuery.devicePixelRatioOf(context)).round(),
                  errorBuilder: (_, __, ___) => initials,
                ),
        ),
      ),
    );
  }
}

enum _PhotoAction { camera, gallery, remove }

/// [ProfileAvatar] with a camera badge: tap to take or choose a new photo, frame it in
/// [ProfilePhotoCropScreen], or remove the current one. Uploads through [api] (the buyer or rider API)
/// and reports the new photo URL through [onChanged].
class ProfilePhotoEditor extends StatefulWidget {
  const ProfilePhotoEditor({
    super.key,
    required this.api,
    required this.name,
    required this.photoUrl,
    required this.onChanged,
    this.radius = 28,
  });

  final ApiClient api;
  final String name;
  final String? photoUrl;
  final ValueChanged<String?> onChanged;
  final double radius;

  @override
  State<ProfilePhotoEditor> createState() => _ProfilePhotoEditorState();
}

class _ProfilePhotoEditorState extends State<ProfilePhotoEditor> {
  bool _busy = false;

  Future<void> _edit() async {
    final action = await showModalBottomSheet<_PhotoAction>(
      context: context,
      backgroundColor: ZayloColors.white,
      builder: (sheetContext) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          ListTile(
            leading: const Icon(Icons.photo_camera_outlined),
            title: const Text('Take photo'),
            onTap: () => Navigator.pop(sheetContext, _PhotoAction.camera),
          ),
          ListTile(
            leading: const Icon(Icons.photo_library_outlined),
            title: const Text('Choose from gallery'),
            onTap: () => Navigator.pop(sheetContext, _PhotoAction.gallery),
          ),
          if (widget.photoUrl != null)
            ListTile(
              leading: Icon(Icons.delete_outline, color: ZayloColors.danger),
              title: Text('Remove photo', style: TextStyle(color: ZayloColors.danger)),
              onTap: () => Navigator.pop(sheetContext, _PhotoAction.remove),
            ),
        ]),
      ),
    );
    if (action == null || !mounted) return;

    if (action == _PhotoAction.remove) {
      await _run(() async {
        await widget.api.removeProfilePhoto();
        return null;
      }, 'Profile photo removed.');
      return;
    }

    final XFile? picked;
    try {
      // Large enough to stay sharp when zoomed in on the crop screen.
      picked = await ImagePicker().pickImage(
        source: action == _PhotoAction.camera ? ImageSource.camera : ImageSource.gallery,
        preferredCameraDevice: CameraDevice.front,
        maxWidth: 2048,
        maxHeight: 2048,
        imageQuality: 90,
      );
    } catch (_) {
      if (mounted) {
        showMessage(context,
            action == _PhotoAction.camera ? 'Could not open the camera. Check the camera permission.' : 'Could not open your photos.');
      }
      return;
    }
    if (picked == null || !mounted) return;

    final cropped = await Navigator.of(context).push<File>(MaterialPageRoute(
      fullscreenDialog: true,
      builder: (_) => ProfilePhotoCropScreen(file: File(picked!.path)),
    ));
    if (cropped == null || !mounted) return;
    try {
      await _run(() => widget.api.uploadProfilePhoto(cropped), 'Profile photo updated.');
    } finally {
      cropped.delete().ignore();
    }
  }

  Future<void> _run(Future<String?> Function() request, String done) async {
    setState(() => _busy = true);
    try {
      final url = await request();
      if (!mounted) return;
      widget.onChanged(url);
      showMessage(context, done);
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.fieldErrors['photo'] ?? error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Semantics(
        button: true,
        label: widget.photoUrl == null ? 'Add profile photo' : 'Change profile photo',
        child: InkWell(
          onTap: _busy ? null : _edit,
          customBorder: const CircleBorder(),
          child: Stack(clipBehavior: Clip.none, children: [
            ProfileAvatar(name: widget.name, photoUrl: widget.photoUrl, radius: widget.radius),
            if (_busy)
              Positioned.fill(
                child: ClipOval(
                  child: ColoredBox(
                    color: Colors.black45,
                    child: Center(
                      child: SizedBox.square(
                        dimension: widget.radius * .7,
                        child: const CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                      ),
                    ),
                  ),
                ),
              ),
            Positioned(
              right: -2,
              bottom: -2,
              child: ExcludeSemantics(
                child: Container(
                  padding: const EdgeInsets.all(5),
                  decoration: BoxDecoration(
                    color: ZayloColors.white,
                    shape: BoxShape.circle,
                    border: Border.all(color: ZayloColors.line),
                  ),
                  child: Icon(Icons.photo_camera, size: widget.radius * .45, color: ZayloColors.ink),
                ),
              ),
            ),
          ]),
        ),
      );
}
