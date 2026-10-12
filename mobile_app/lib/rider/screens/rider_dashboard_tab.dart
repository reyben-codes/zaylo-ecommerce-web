import 'package:flutter/material.dart';

import '../../core/theme/zaylo_theme.dart';
import '../../core/widgets/feedback.dart';
import '../api/rider_api.dart';
import '../models/rider_activity_models.dart';
import '../models/rider_dashboard_models.dart';
import '../widgets/rider_dashboard_widgets.dart';
import '../widgets/rider_delivery_actions.dart';
import 'rider_delivery_list_screen.dart';
import 'rider_earnings_screen.dart';

/// The Home tab: today's numbers (each opens its own list), deliveries in
/// progress with their next step, deliveries waiting for a rider, and the
/// most recent completed ones.
class RiderDashboardTab extends StatefulWidget {
  const RiderDashboardTab({super.key, required this.api, required this.onOpenHistory});

  final RiderApi api;

  /// Switches to the History tab filtered to a period.
  final ValueChanged<RiderPeriod> onOpenHistory;

  @override
  State<RiderDashboardTab> createState() => RiderDashboardTabState();
}

class RiderDashboardTabState extends State<RiderDashboardTab> with RiderDeliveryActions {
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

  /// Opens a screen, then refreshes: the rider may have accepted or delivered there.
  Future<void> _open(Widget screen) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen));
    if (mounted) await reloadDeliveries();
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<RiderDashboard>(
        future: _dashboard,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done && !snapshot.hasData) return const Loader();
          if (snapshot.hasError) {
            return LoadError(error: snapshot.error, onRetry: reloadDeliveries, title: 'Could not load your dashboard');
          }
          return RefreshIndicator(
            color: ZayloColors.ink,
            onRefresh: reloadDeliveries,
            child: _content(snapshot.requireData),
          );
        },
      );

  Widget _content(RiderDashboard data) => ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.fromLTRB(20, 8, 20, 32 + MediaQuery.paddingOf(context).bottom),
        children: [
          RiderGreetingCard(rider: data.rider),
          if (data.rider.licenseExpiringSoon) ...[
            const SizedBox(height: 12),
            LicenseWarningBanner(rider: data.rider),
          ],
          const SizedBox(height: 16),
          RiderStatsGrid(
            stats: data.stats,
            onAvailable: () => _open(RiderDeliveryListScreen(api: widget.api, list: RiderDeliveryList.available)),
            onActive: () => _open(RiderDeliveryListScreen(api: widget.api, list: RiderDeliveryList.active)),
            onDeliveredToday: () => widget.onOpenHistory(RiderPeriod.today),
            onEarnedToday: () => _open(RiderEarningsScreen(
              api: widget.api,
              onOpenHistory: (period) {
                Navigator.of(context).pop();
                widget.onOpenHistory(period);
              },
            )),
          ),

          RiderSectionHeader(title: 'Your active deliveries', count: data.active.length),
          if (data.active.isEmpty)
            const RiderEmptyCard(
              icon: Icons.local_shipping_outlined,
              message: 'No deliveries in progress. Accept one below to get started.',
            )
          else
            for (final delivery in data.active)
              RiderDeliveryCard(
                delivery: delivery,
                actionLabel: delivery.nextActionLabel,
                busy: busyDeliveryId == delivery.id,
                onAction: busyDeliveryId == null ? () => advanceDelivery(delivery) : null,
                onChat: () => openDeliveryChat(delivery),
              ),

          RiderSectionHeader(title: 'Available deliveries', count: data.available.length),
          if (data.available.isEmpty)
            const RiderEmptyCard(
              icon: Icons.inventory_2_outlined,
              message: 'No parcels are waiting right now. Pull down to refresh.',
            )
          else
            for (final delivery in data.available)
              RiderDeliveryCard(
                delivery: delivery,
                actionLabel: 'Accept delivery',
                busy: busyDeliveryId == delivery.id,
                onAction: busyDeliveryId == null ? () => acceptDelivery(delivery) : null,
              ),

          if (data.recent.isNotEmpty) ...[
            const RiderSectionHeader(title: 'Recently delivered'),
            for (final delivery in data.recent) RiderDeliveryCard(delivery: delivery),
            TextButton(onPressed: () => widget.onOpenHistory(RiderPeriod.all), child: const Text('See all delivery history')),
          ],
        ],
      );
}
