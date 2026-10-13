import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/features/rider/dashboard/widgets/rider_dashboard_widgets.dart';
import 'package:zaylo_mobile/features/rider/dashboard/widgets/rider_delivery_actions.dart';
import 'package:zaylo_mobile/models/rider/rider_dashboard_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Which deliveries a [RiderDeliveryListScreen] shows.
enum RiderDeliveryList {
  available('Available deliveries', 'No parcels are waiting right now. Pull down to refresh.', Icons.inventory_2_outlined),
  active('Active deliveries', 'No deliveries in progress. Accept one from Available deliveries.', Icons.local_shipping_outlined);

  const RiderDeliveryList(this.title, this.emptyMessage, this.emptyIcon);

  final String title;
  final String emptyMessage;
  final IconData emptyIcon;
}

/// Full list of available or active deliveries, opened from the dashboard's
/// Available and Active tiles.
class RiderDeliveryListScreen extends StatefulWidget {
  const RiderDeliveryListScreen({super.key, required this.api, required this.list});

  final RiderApi api;
  final RiderDeliveryList list;

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
        appBar: AppBar(backgroundColor: ZayloColors.white, title: Text(widget.list.title)),
        body: FutureBuilder<RiderDashboard>(
          future: _dashboard,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done && !snapshot.hasData) return const Loader();
            if (snapshot.hasError) {
              return LoadError(error: snapshot.error, onRetry: reloadDeliveries, title: 'Could not load deliveries');
            }
            final deliveries =
                widget.list == RiderDeliveryList.available ? snapshot.requireData.available : snapshot.requireData.active;
            return RefreshIndicator(
              color: ZayloColors.ink,
              onRefresh: reloadDeliveries,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
                children: [
                  if (deliveries.isEmpty)
                    RiderEmptyCard(icon: widget.list.emptyIcon, message: widget.list.emptyMessage)
                  else
                    for (final delivery in deliveries)
                      RiderDeliveryCard(
                        delivery: delivery,
                        busy: busyDeliveryId == delivery.id,
                        actionLabel: widget.list == RiderDeliveryList.available ? 'Accept delivery' : delivery.nextActionLabel,
                        onAction: busyDeliveryId != null
                            ? null
                            : () => widget.list == RiderDeliveryList.available
                                ? acceptDelivery(delivery)
                                : advanceDelivery(delivery),
                        onChat: () => openDeliveryChat(delivery),
                      ),
                ],
              ),
            );
          },
        ),
      );
}
