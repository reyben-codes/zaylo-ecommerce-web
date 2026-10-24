import 'dart:io';

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/file_picking.dart';
import 'package:zaylo_mobile/components/form_fields.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/components/star_rating.dart';
import 'package:zaylo_mobile/models/buyer/order_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Rates a delivered item, as on the website's review form: 1–5 stars, an
/// optional comment, and up to three photos. Pops true once published.
class ReviewFormScreen extends StatefulWidget {
  const ReviewFormScreen({super.key, required this.item});

  final OrderItem item;

  @override
  State<ReviewFormScreen> createState() => _ReviewFormScreenState();
}

class _ReviewFormScreenState extends State<ReviewFormScreen> {
  static const _maxPhotos = 3;
  static const _photoExtensions = ['jpg', 'jpeg', 'png', 'webp'];

  final _comment = TextEditingController();
  final List<File> _photos = [];
  int _rating = 0;
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  Future<void> _addPhoto() async {
    final photo = await pickPhotoOrFile(context);
    if (photo == null || !mounted) return;
    final problem = uploadFileError(photo, extensions: _photoExtensions);
    if (problem != null) {
      showMessage(context, problem);
      return;
    }
    setState(() => _photos.add(photo));
  }

  Future<void> _submit() async {
    if (_rating == 0) {
      setState(() => _error = 'Choose a star rating.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final message = await AppScope.read(context)
          .api
          .reviewOrderItem(widget.item.id, rating: _rating, comment: _comment.text, photos: _photos);
      if (!mounted) return;
      showMessage(context, message);
      Navigator.of(context).pop(true);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Review your purchase')),
        body: ListView(padding: const EdgeInsets.all(ZayloSpace.page), children: [
          SectionCard(
            child: Row(children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: SizedBox(width: 56, height: 56, child: NetworkImageBox(url: widget.item.imageUrl)),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(widget.item.productName,
                      maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
                  Text('Quantity purchased: ${widget.item.quantity}', style: const TextStyle(fontSize: 12)),
                ]),
              ),
            ]),
          ),
          const SizedBox(height: 22),
          const Center(child: Eyebrow('Your rating')),
          StarPicker(value: _rating, onChanged: (value) => setState(() => _rating = value)),
          Center(
            child: Text(_rating == 0 ? 'Tap a star' : StarPicker.labels[_rating - 1],
                style: TextStyle(fontWeight: FontWeight.w600, color: ZayloColors.ink)),
          ),
          const SizedBox(height: 22),
          const FieldLabel('Comment (optional)'),
          TextField(
            controller: _comment,
            minLines: 3,
            maxLines: 6,
            maxLength: 2000,
            textCapitalization: TextCapitalization.sentences,
            decoration: zayloInputDecoration('What did you like or dislike?'),
          ),
          const SizedBox(height: 8),
          FieldLabel('Photos (optional, up to $_maxPhotos)'),
          Wrap(spacing: 10, runSpacing: 10, children: [
            for (final (index, photo) in _photos.indexed)
              Stack(clipBehavior: Clip.none, children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: Image.file(photo, width: 84, height: 84, fit: BoxFit.cover, semanticLabel: 'Photo ${index + 1}'),
                ),
                Positioned(
                  top: -10,
                  right: -10,
                  child: IconButton.filled(
                    tooltip: 'Remove photo ${index + 1}',
                    visualDensity: VisualDensity.compact,
                    iconSize: 16,
                    style: IconButton.styleFrom(backgroundColor: ZayloColors.ink, foregroundColor: ZayloColors.white),
                    onPressed: _saving ? null : () => setState(() => _photos.removeAt(index)),
                    icon: const Icon(Icons.close),
                  ),
                ),
              ]),
            if (_photos.length < _maxPhotos)
              Semantics(
                button: true,
                label: 'Add a photo',
                child: InkWell(
                  onTap: _saving ? null : _addPhoto,
                  borderRadius: BorderRadius.circular(10),
                  child: Container(
                    width: 84,
                    height: 84,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: ZayloColors.line),
                      color: ZayloColors.paper,
                    ),
                    child: Icon(Icons.add_a_photo_outlined, color: ZayloColors.muted),
                  ),
                ),
              ),
          ]),
          if (_error != null) ...[
            const SizedBox(height: 14),
            Text(_error!, style: const TextStyle(color: Colors.redAccent)),
          ],
          const SizedBox(height: 26),
          PrimaryButton(label: 'PUBLISH REVIEW', busy: _saving, onPressed: _submit),
          const SizedBox(height: 10),
          const Text('Reviews show your first name and are marked “Verified purchase”.',
              textAlign: TextAlign.center, style: TextStyle(fontSize: 12)),
        ]),
      );
}
