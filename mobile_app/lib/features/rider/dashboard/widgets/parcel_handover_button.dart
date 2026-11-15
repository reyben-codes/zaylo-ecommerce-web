import 'package:flutter/material.dart';
import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/feedback.dart';

class ParcelHandoverButton extends StatefulWidget {
  const ParcelHandoverButton({super.key, required this.api, required this.shipmentId, required this.enabled, required this.confirmed, required this.onChanged});
  final RiderApi api;
  final int shipmentId;
  final bool enabled;
  final bool confirmed;
  final VoidCallback onChanged;
  @override
  State<ParcelHandoverButton> createState() => _ParcelHandoverButtonState();
}

class _ParcelHandoverButtonState extends State<ParcelHandoverButton> {
  bool _busy = false;
  Future<void> _confirm() async {
    setState(() => _busy = true);
    try {
      final message = await widget.api.confirmParcelHandover(widget.shipmentId);
      if (!mounted) return;
      showMessage(context, message);
      widget.onChanged();
    } on ApiException catch (error) { if (mounted) showMessage(context, error.message); }
    finally { if (mounted) setState(() => _busy = false); }
  }
  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
    OutlinedButton.icon(onPressed: widget.enabled && !widget.confirmed && !_busy ? _confirm : null,
      icon: Icon(widget.confirmed ? Icons.task_alt : Icons.handshake_outlined),
      label: Text(widget.confirmed ? 'Handover confirmed' : (_busy ? 'Confirming…' : 'Confirm Handover to Logistics'))),
    if (!widget.enabled && !widget.confirmed) const Text('Scan the assigned waybill to unlock handover.', style: TextStyle(fontSize: 12)),
  ]);
}
