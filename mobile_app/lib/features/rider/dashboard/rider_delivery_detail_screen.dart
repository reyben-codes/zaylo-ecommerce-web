import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';

import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/components/map/zaylo_map.dart';
import 'package:zaylo_mobile/features/rider/dashboard/widgets/rider_dashboard_widgets.dart';
import 'package:zaylo_mobile/features/rider/dashboard/widgets/rider_delivery_actions.dart';
import 'package:zaylo_mobile/models/rider/rider_dashboard_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

/// One parcel in full, such as a delivered one from History: its stops, items and amounts, and a timeline of
/// everything the rider did with it (accepted, out for delivery, failed attempts and why, delivered).
class RiderDeliveryDetailScreen extends StatefulWidget {
  const RiderDeliveryDetailScreen({super.key, required this.api, required this.deliveryId, this.preview});

  final RiderApi api;
  final int deliveryId;

  /// Shown while the full details load.
  final RiderDelivery? preview;

  @override
  State<RiderDeliveryDetailScreen> createState() => _RiderDeliveryDetailScreenState();
}

class _RiderDeliveryDetailScreenState extends State<RiderDeliveryDetailScreen>
    with RiderDeliveryActions<RiderDeliveryDetailScreen> {
  late Future<RiderDelivery> _delivery = widget.api.deliveryDetails(widget.deliveryId);

  @override
  RiderApi get riderApi => widget.api;

  @override
  Future<void> reloadDeliveries() => _reload();

  Future<void> _addPhoto(RiderDelivery delivery) async {
    await addDeliveryPhoto(delivery);
    if (mounted) await _reload();
  }

  Future<void> _reload() async {
    final delivery = widget.api.deliveryDetails(widget.deliveryId);
    setState(() {
      _delivery = delivery;
    });
    try {
      await delivery;
    } on Exception {
      // Shown by the FutureBuilder's error state.
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: ZayloColors.white,
        appBar: AppBar(
          backgroundColor: ZayloColors.white,
          title: Text(widget.preview?.orderNumber ?? 'Delivery details'),
        ),
        body: FutureBuilder<RiderDelivery>(
          future: _delivery,
          builder: (context, snapshot) {
            final delivery = snapshot.data;
            if (delivery == null && snapshot.hasError) {
              return LoadError(error: snapshot.error, onRetry: _reload, title: 'Could not load this delivery');
            }
            final shown = delivery ?? widget.preview;
            if (shown == null) return const Loader();
            return RefreshIndicator(
              color: ZayloColors.ink,
              onRefresh: _reload,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
                children: [
                  RiderDeliveryCard(delivery: shown),
                  if (shown.destination case final destination? when shown.status != 'delivered')
                    _DestinationMap(destination: destination, recipient: shown.recipientName),
                  if (delivery != null && (delivery.canAddDeliveryPhoto || delivery.hasDeliveryPhoto))
                    Padding(
                      padding: const EdgeInsets.only(top: 12),
                      child: Row(children: [
                        Icon(
                          delivery.hasDeliveryPhoto ? Icons.task_alt : Icons.photo_camera_outlined,
                          size: 20,
                          color: delivery.hasDeliveryPhoto ? ZayloColors.success : ZayloColors.muted,
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(delivery.hasDeliveryPhoto
                              ? 'Delivery photo sent to the seller and buyer.'
                              : 'No delivery photo yet.'),
                        ),
                        if (delivery.canAddDeliveryPhoto)
                          OutlinedButton(
                            onPressed: busyDeliveryId == delivery.id ? null : () => _addPhoto(delivery),
                            child: Text(delivery.hasDeliveryPhoto ? 'Retake' : 'Add photo'),
                          ),
                      ]),
                    ),
                  const RiderSectionHeader(title: 'Timeline'),
                  if (delivery == null)
                    const Padding(padding: EdgeInsets.all(16), child: Loader())
                  else if (delivery.timeline.isEmpty)
                    const RiderEmptyCard(icon: Icons.timeline, message: 'No steps recorded for this parcel yet.')
                  else
                    SectionCard(
                      child: Column(children: [
                        for (final (index, entry) in delivery.timeline.indexed)
                          _TimelineRow(entry: entry, last: index == delivery.timeline.length - 1),
                      ]),
                    ),
                ],
              ),
            );
          },
        ),
      );
}

/// Where the buyer pinned the delivery, with directions in Google Maps and the rider's own position on request.
class _DestinationMap extends StatefulWidget {
  const _DestinationMap({required this.destination, this.recipient});

  final MapPoint destination;
  final String? recipient;

  @override
  State<_DestinationMap> createState() => _DestinationMapState();
}

class _DestinationMapState extends State<_DestinationMap> {
  final _map = MapController();
  LatLng? _me;
  bool _locating = false;

  Future<void> _showMe() async {
    setState(() => _locating = true);
    try {
      final here = await DeviceLocation.current();
      if (!mounted) return;
      setState(() => _me = here);
      _map.fitCamera(CameraFit.bounds(
        bounds: LatLngBounds.fromPoints([here, widget.destination.latLng]),
        padding: const EdgeInsets.all(40),
        maxZoom: 17,
      ));
    } on LocationUnavailable catch (error) {
      if (mounted) showMessage(context, error.message);
    } on Exception {
      if (mounted) showMessage(context, 'Could not get your location.');
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  Future<void> _directions() async {
    if (!await openDirections(widget.destination) && mounted) {
      showMessage(context, 'Could not open directions. Install Google Maps or a browser.');
    }
  }

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 12),
        child: SectionCard(
          padding: EdgeInsets.zero,
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
              child: SizedBox(
                height: 220,
                child: ZayloMap(
                  controller: _map,
                  center: widget.destination.latLng,
                  zoom: 16,
                  markers: [
                    mapMarker(widget.destination.latLng, MapPinKind.home),
                    if (_me != null) mapMarker(_me!, MapPinKind.me),
                  ],
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 10, 14, 12),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(
                  widget.recipient == null ? 'Pinned delivery spot' : "${widget.recipient}'s pinned delivery spot",
                  style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink),
                ),
                const SizedBox(height: 10),
                Row(children: [
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: _directions,
                      icon: const Icon(Icons.directions),
                      label: const Text('DIRECTIONS'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: OutlinedButton.icon(
                      style: secondaryButtonStyle,
                      onPressed: _locating ? null : _showMe,
                      icon: _locating
                          ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                          : const Icon(Icons.my_location),
                      label: const Text('WHERE AM I'),
                    ),
                  ),
                ]),
              ]),
            ),
          ]),
        ),
      );
}

class _TimelineRow extends StatelessWidget {
  const _TimelineRow({required this.entry, required this.last});

  final RiderTimelineEntry entry;
  final bool last;

  Color get _color => switch (entry.action) {
        'delivered' => ZayloColors.success,
        'failed' || 'declined' => ZayloColors.danger,
        _ => ZayloColors.ink,
      };

  IconData get _icon => switch (entry.action) {
        'accepted' => Icons.check_circle_outline,
        'declined' => Icons.do_not_disturb_on_outlined,
        'out_for_delivery' => Icons.local_shipping_outlined,
        'failed' => Icons.error_outline,
        'delivered' => Icons.task_alt,
        _ => Icons.circle_outlined,
      };

  @override
  Widget build(BuildContext context) => IntrinsicHeight(
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Column(children: [
            Icon(_icon, size: 22, color: _color),
            if (!last) Expanded(child: Container(width: 2, color: ZayloColors.line)),
          ]),
          const SizedBox(width: 12),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: last ? 0 : 16),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(entry.label, style: TextStyle(fontWeight: FontWeight.w700, color: _color)),
                if (entry.reason != null) Text(entry.reason!),
                if (entry.note != null) Text('“${entry.note}”', style: TextStyle(color: ZayloColors.muted)),
                if (entry.at != null)
                  Text(dateTimeLabel(entry.at), style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
              ]),
            ),
          ),
        ]),
      );
}
