import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Reads the QR code on a parcel label and pops with its text. When [accept]
/// is given, codes it rejects (another parcel's label) are ignored with a
/// message and scanning goes on. A damaged label can be typed in instead.
class RiderScanScreen extends StatefulWidget {
  const RiderScanScreen({super.key, required this.title, required this.hint, this.accept});

  final String title;

  /// One line under the viewfinder, e.g. which parcel to scan.
  final String hint;

  /// Returns an error message for a code that is not the expected parcel, or null to accept it.
  final String? Function(String code)? accept;

  @override
  State<RiderScanScreen> createState() => _RiderScanScreenState();
}

class _RiderScanScreenState extends State<RiderScanScreen> {
  final _controller = MobileScannerController(formats: const [BarcodeFormat.qrCode]);

  /// Set once a code is accepted, so a label held in view is not read twice.
  bool _done = false;

  /// Why the last code was turned away, shown above the manual entry button.
  String? _error;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    final code = capture.barcodes.map((barcode) => barcode.rawValue?.trim()).whereType<String>().firstOrNull;
    if (code != null && code.isNotEmpty) _submit(code);
  }

  void _submit(String code) {
    if (_done) return;
    final error = widget.accept?.call(code);
    if (error != null) {
      if (_error != error) setState(() => _error = error);
      return;
    }
    _done = true;
    Navigator.of(context).pop(code);
  }

  Future<void> _enterManually() async {
    final code = await showDialog<String>(context: context, builder: (_) => const _ManualCodeDialog());
    if (code != null && code.trim().isNotEmpty && mounted) _submit(code.trim());
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: Colors.black,
        appBar: AppBar(
          backgroundColor: Colors.black,
          foregroundColor: Colors.white,
          title: Text(widget.title),
          actions: [
            ValueListenableBuilder<MobileScannerState>(
              valueListenable: _controller,
              builder: (context, state, _) => state.torchState == TorchState.unavailable
                  ? const SizedBox.shrink()
                  : IconButton(
                      tooltip: state.torchState == TorchState.on ? 'Turn flashlight off' : 'Turn flashlight on',
                      icon: Icon(state.torchState == TorchState.on ? Icons.flash_on : Icons.flash_off),
                      onPressed: _controller.toggleTorch,
                    ),
            ),
          ],
        ),
        body: Column(children: [
          Expanded(
            child: Stack(fit: StackFit.expand, children: [
              MobileScanner(
                controller: _controller,
                onDetect: _onDetect,
                errorBuilder: (context, error) => _CameraError(error: error, onEnterManually: _enterManually),
              ),
              IgnorePointer(
                child: Center(
                  child: Container(
                    width: 240,
                    height: 240,
                    decoration: BoxDecoration(
                      border: Border.all(color: Colors.white, width: 3),
                      borderRadius: BorderRadius.circular(20),
                    ),
                  ),
                ),
              ),
            ]),
          ),
          Container(
            width: double.infinity,
            color: Colors.black,
            padding: EdgeInsets.fromLTRB(20, 16, 20, 16 + MediaQuery.paddingOf(context).bottom),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              if (_error != null) ...[
                Semantics(
                  liveRegion: true,
                  child: Text(_error!,
                      textAlign: TextAlign.center,
                      style: TextStyle(color: ZayloColors.warning, fontWeight: FontWeight.w700)),
                ),
                const SizedBox(height: 8),
              ],
              Text(widget.hint, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white)),
              const SizedBox(height: 4),
              Text('Point the camera at the QR code on the parcel label.',
                  textAlign: TextAlign.center, style: TextStyle(color: Colors.white.withValues(alpha: .7), fontSize: 12)),
              const SizedBox(height: 10),
              TextButton.icon(
                onPressed: _enterManually,
                style: TextButton.styleFrom(foregroundColor: Colors.white),
                icon: const Icon(Icons.keyboard_outlined),
                label: const Text('Label damaged? Type the tracking number'),
              ),
            ]),
          ),
        ]),
      );
}

class _CameraError extends StatelessWidget {
  const _CameraError({required this.error, required this.onEnterManually});

  final MobileScannerException error;
  final VoidCallback onEnterManually;

  @override
  Widget build(BuildContext context) => ColoredBox(
        color: Colors.black,
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.no_photography_outlined, color: Colors.white, size: 40),
              const SizedBox(height: 12),
              Text(
                error.errorCode == MobileScannerErrorCode.permissionDenied
                    ? 'ZAYLO needs camera access to scan parcels. Allow it in your phone settings, or type the tracking number.'
                    : 'The camera could not start. You can type the tracking number instead.',
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.white),
              ),
              const SizedBox(height: 12),
              FilledButton(
                onPressed: onEnterManually,
                style: FilledButton.styleFrom(backgroundColor: ZayloColors.white, foregroundColor: ZayloColors.ink),
                child: const Text('Type tracking number'),
              ),
            ]),
          ),
        ),
      );
}

class _ManualCodeDialog extends StatefulWidget {
  const _ManualCodeDialog();

  @override
  State<_ManualCodeDialog> createState() => _ManualCodeDialogState();
}

class _ManualCodeDialogState extends State<_ManualCodeDialog> {
  final _field = TextEditingController();

  @override
  void dispose() {
    _field.dispose();
    super.dispose();
  }

  void _done() => Navigator.pop(context, _field.text);

  @override
  Widget build(BuildContext context) => AlertDialog(
        title: const Text('Tracking number'),
        content: TextField(
          controller: _field,
          autofocus: true,
          textCapitalization: TextCapitalization.characters,
          textInputAction: TextInputAction.done,
          onSubmitted: (_) => _done(),
          decoration: const InputDecoration(hintText: 'e.g. TRK-ABC123XYZ456'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
          TextButton(onPressed: _done, child: const Text('Continue')),
        ],
      );
}
