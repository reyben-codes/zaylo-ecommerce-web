import 'dart:io';
import 'dart:math' as math;
import 'dart:ui' as ui;

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Lets the user frame their profile photo: drag to move and pinch (or use the slider) to zoom inside a
/// circle the size of the final avatar. Pops with the framed square as a new PNG file, or null on cancel.
///
/// The crop happens here rather than on the server so the result is exactly what the user saw, however
/// the phone stored the photo's rotation.
class ProfilePhotoCropScreen extends StatefulWidget {
  const ProfilePhotoCropScreen({super.key, required this.file});

  final File file;

  /// The saved square's size; the server shrinks it to 512px, so this keeps zoomed crops sharp.
  static const outputSize = 768;

  /// Zooming stops before the kept square gets smaller than this many photo pixels.
  static const minCropPixels = 160.0;

  static const maxZoom = 4.0;

  @override
  State<ProfilePhotoCropScreen> createState() => _ProfilePhotoCropScreenState();
}

class _ProfilePhotoCropScreenState extends State<ProfilePhotoCropScreen> {
  ui.Image? _image;
  bool _failed = false;
  bool _saving = false;

  /// The photo pixel under the circle's centre, and the zoom past "just covers the circle".
  Offset _center = Offset.zero;
  double _zoom = 1;
  double _maxZoom = 1;
  double _view = 1;

  double _startZoom = 1;
  Offset _startCenter = Offset.zero;
  Offset _startFocal = Offset.zero;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _image?.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final codec = await ui.instantiateImageCodec(await widget.file.readAsBytes());
      final image = (await codec.getNextFrame()).image;
      if (!mounted) {
        image.dispose();
        return;
      }
      setState(() {
        _image = image;
        _center = Offset(image.width / 2, image.height / 2);
        _maxZoom = math.max(1, math.min(ProfilePhotoCropScreen.maxZoom,
            math.min(image.width, image.height) / ProfilePhotoCropScreen.minCropPixels));
      });
    } catch (_) {
      if (mounted) setState(() => _failed = true);
    }
  }

  double _scaleFor(double zoom) => _view / math.min(_image!.width, _image!.height) * zoom;

  Offset _clamped(Offset center, double zoom) {
    final half = _view / 2 / _scaleFor(zoom);
    return Offset(
      center.dx.clamp(half, _image!.width - half),
      center.dy.clamp(half, _image!.height - half),
    );
  }

  void _setZoom(double zoom) => setState(() {
        _zoom = zoom.clamp(1, _maxZoom);
        _center = _clamped(_center, _zoom);
      });

  void _onScaleStart(ScaleStartDetails details) {
    _startZoom = _zoom;
    _startCenter = _center;
    _startFocal = details.localFocalPoint;
  }

  // Keeps the photo point under the fingers in place while moving and zooming.
  void _onScaleUpdate(ScaleUpdateDetails details) {
    final zoom = (_startZoom * details.scale).clamp(1.0, _maxZoom);
    final middle = Offset(_view / 2, _view / 2);
    final anchor = _startCenter + (_startFocal - middle) / _scaleFor(_startZoom);
    setState(() {
      _zoom = zoom;
      _center = _clamped(anchor - (details.localFocalPoint - middle) / _scaleFor(zoom), zoom);
    });
  }

  Future<void> _save() async {
    final image = _image!;
    setState(() => _saving = true);
    try {
      final side = _view / _scaleFor(_zoom);
      final source = Rect.fromCenter(center: _center, width: side, height: side);
      final size = math.min(ProfilePhotoCropScreen.outputSize, side.round());
      final recorder = ui.PictureRecorder();
      Canvas(recorder).drawImageRect(
        image,
        source,
        Rect.fromLTWH(0, 0, size.toDouble(), size.toDouble()),
        Paint()..filterQuality = FilterQuality.high,
      );
      final cropped = await recorder.endRecording().toImage(size, size);
      final png = await cropped.toByteData(format: ui.ImageByteFormat.png);
      cropped.dispose();
      final file = File('${Directory.systemTemp.path}/zaylo-profile-${DateTime.now().microsecondsSinceEpoch}.png');
      await file.writeAsBytes(png!.buffer.asUint8List(), flush: true);
      if (mounted) Navigator.pop(context, file);
    } catch (_) {
      if (mounted) {
        setState(() => _saving = false);
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Could not crop this photo. Please try another.')));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final image = _image;
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: const Text('Crop photo'),
      ),
      body: SafeArea(
        child: Column(children: [
          Expanded(
            child: Center(
              child: _failed
                  ? const Padding(
                      padding: EdgeInsets.all(24),
                      child: Text('This photo could not be opened. Please choose another.',
                          textAlign: TextAlign.center, style: TextStyle(color: Colors.white)),
                    )
                  : image == null
                      ? const CircularProgressIndicator(color: Colors.white)
                      : LayoutBuilder(builder: (context, constraints) {
                          final view = math.min(constraints.maxWidth, constraints.maxHeight) - 32;
                          if (view != _view) {
                            _view = view;
                            _center = _clamped(_center, _zoom);
                          }
                          return Semantics(
                            label: 'Photo position. Drag to move, pinch to zoom.',
                            child: GestureDetector(
                              onScaleStart: _onScaleStart,
                              onScaleUpdate: _onScaleUpdate,
                              onDoubleTap: () => _setZoom(_zoom > 1 ? 1 : math.min(2, _maxZoom)),
                              child: SizedBox.square(
                                dimension: view,
                                child: CustomPaint(
                                  painter: _CropPainter(image: image, center: _center, scale: _scaleFor(_zoom)),
                                ),
                              ),
                            ),
                          );
                        }),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 20, 0),
            child: Text('Drag to move and pinch to zoom. The circle shows your new profile photo.',
                textAlign: TextAlign.center, style: TextStyle(color: Colors.white.withValues(alpha: .75), fontSize: 13)),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Row(children: [
              const Icon(Icons.photo_size_select_small, color: Colors.white70, size: 20),
              Expanded(
                child: Slider(
                  value: _zoom,
                  min: 1,
                  max: _maxZoom,
                  label: '${(_zoom * 100).round()}%',
                  semanticFormatterCallback: (value) => 'Zoom ${(value * 100).round()}%',
                  activeColor: Colors.white,
                  inactiveColor: Colors.white24,
                  onChanged: image == null || _maxZoom <= 1 ? null : _setZoom,
                ),
              ),
              const Icon(Icons.photo_size_select_large, color: Colors.white70, size: 24),
            ]),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
            child: Row(children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: _saving ? null : () => Navigator.pop(context),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: Colors.white,
                    side: const BorderSide(color: Colors.white54),
                    minimumSize: const Size.fromHeight(48),
                  ),
                  child: const Text('Cancel'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: FilledButton(
                  onPressed: image == null || _saving ? null : _save,
                  style: FilledButton.styleFrom(
                    backgroundColor: Colors.white,
                    foregroundColor: ZayloPalette.light.ink,
                    minimumSize: const Size.fromHeight(48),
                  ),
                  child: _saving
                      ? SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2.5, color: ZayloPalette.light.ink))
                      : const Text('Use photo'),
                ),
              ),
            ]),
          ),
        ]),
      ),
    );
  }
}

/// The photo at its current position, dimmed outside the circle.
class _CropPainter extends CustomPainter {
  _CropPainter({required this.image, required this.center, required this.scale});

  final ui.Image image;
  final Offset center;
  final double scale;

  @override
  void paint(Canvas canvas, Size size) {
    final middle = size.center(Offset.zero);
    canvas.save();
    canvas.clipRect(Offset.zero & size);
    canvas.translate(middle.dx - center.dx * scale, middle.dy - center.dy * scale);
    canvas.scale(scale);
    canvas.drawImage(image, Offset.zero, Paint()..filterQuality = FilterQuality.medium);
    canvas.restore();

    final circle = Rect.fromCircle(center: middle, radius: size.width / 2);
    canvas.drawPath(
      Path()
        ..fillType = PathFillType.evenOdd
        ..addRect(Offset.zero & size)
        ..addOval(circle),
      Paint()..color = Colors.black54,
    );
    canvas.drawOval(circle, Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2
      ..color = Colors.white);
  }

  @override
  bool shouldRepaint(_CropPainter old) => old.image != image || old.center != center || old.scale != scale;
}
