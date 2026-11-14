import 'package:flutter/material.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/features/rider/dashboard/widgets/rider_delivery_actions.dart';
import 'package:zaylo_mobile/models/rider/rider_dashboard_models.dart';

class RiderPickupQueueScreen extends StatefulWidget {
  const RiderPickupQueueScreen({super.key, required this.api});
  final RiderApi api;
  @override
  State<RiderPickupQueueScreen> createState() => _RiderPickupQueueScreenState();
}

class _RiderPickupQueueScreenState extends State<RiderPickupQueueScreen> with RiderDeliveryActions<RiderPickupQueueScreen> {
  late Future<List<RiderSellerPickup>> _queue = widget.api.pickupQueue();
  @override
  RiderApi get riderApi => widget.api;
  @override
  Future<void> reloadDeliveries() async {
    final queue = widget.api.pickupQueue();
    setState(() => _queue = queue);
    try { await queue; } catch (_) {}
  }
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Assigned pickups')),
    body: FutureBuilder<List<RiderSellerPickup>>(future: _queue, builder: (context, snapshot) {
      if (snapshot.hasError) return LoadError(error: snapshot.error, onRetry: reloadDeliveries);
      if (!snapshot.hasData) return const Loader();
      return RefreshIndicator(onRefresh: reloadDeliveries, child: ListView(
        physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.all(20), children: [
          FilledButton.icon(onPressed: scanSellerPickup, icon: const Icon(Icons.qr_code_scanner), label: const Text('Scan seller pickup')),
          const SizedBox(height: 16),
          if (snapshot.requireData.isEmpty) const Padding(padding: EdgeInsets.all(24), child: Text('No pickups are waiting for collection.')),
          for (final pickup in snapshot.requireData) Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(
            crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(pickup.trackingNumber, style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8), Text(pickup.seller), Text(pickup.address),
              const SizedBox(height: 8), Text(pickup.statusLabel),
            ],
          ))),
        ],
      ));
    }),
  );
}
