import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/features/rider/dashboard/widgets/rider_dashboard_widgets.dart';
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

class _RiderDeliveryDetailScreenState extends State<RiderDeliveryDetailScreen> {
  late Future<RiderDelivery> _delivery = widget.api.deliveryDetails(widget.deliveryId);

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
