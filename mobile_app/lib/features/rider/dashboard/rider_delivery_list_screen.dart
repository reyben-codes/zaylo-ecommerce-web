import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/features/rider/dashboard/widgets/rider_dashboard_widgets.dart';
import 'package:zaylo_mobile/features/rider/dashboard/widgets/rider_delivery_actions.dart';
import 'package:zaylo_mobile/models/rider/rider_dashboard_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Every delivery in progress, opened from the dashboard's Active tile.
class RiderDeliveryListScreen extends StatefulWidget {
  const RiderDeliveryListScreen({super.key, required this.api});

  final RiderApi api;

  @override
  State<RiderDeliveryListScreen> createState() => _RiderDeliveryListScreenState();
}

class _RiderDeliveryListScreenState extends State<RiderDeliveryListScreen> with RiderDeliveryActions {
  late Future<RiderDashboard> _dashboard = widget.api.dashboard();

  @override
  RiderApi get riderApi => widget.api;

  @override
  Future<void> reloadDeliveries() async {
    final dashboard = widget.api.dashboard();
    setState(() {
      _dashboard = dashboard;
    });
    try {
      await dashboard;
    } on Exception {
      // Shown by the FutureBuilder's error state.
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: ZayloColors.white,
        appBar: AppBar(backgroundColor: ZayloColors.white, title: const Text('Active deliveries')),
        body: FutureBuilder<RiderDashboard>(
          future: _dashboard,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done && !snapshot.hasData) return const Loader();
            if (snapshot.hasError) {
              return LoadError(error: snapshot.error, onRetry: reloadDeliveries, title: 'Could not load deliveries');
            }
            final deliveries = snapshot.requireData.active;
            return RefreshIndicator(
              color: ZayloColors.ink,
              onRefresh: reloadDeliveries,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
                children: [
                  if (deliveries.isEmpty)
                    const RiderEmptyCard(
                      icon: Icons.local_shipping_outlined,
                      message: 'No deliveries in progress. Your logistics partner assigns parcels to you.',
                    )
                  else
                    for (final delivery in deliveries)
                      RiderDeliveryCard(
                        delivery: delivery,
                        busy: busyDeliveryId == delivery.id,
                        actionLabel: delivery.nextActionLabel,
                        onAction: busyDeliveryId == null ? () => advanceDelivery(delivery) : null,
                        onOtherAction: busyDeliveryId == null ? (status) => advanceDelivery(delivery, status) : null,
                        onChat: () => openDeliveryChat(delivery),
                      ),
                ],
              ),
            );
          },
        ),
      );
}
