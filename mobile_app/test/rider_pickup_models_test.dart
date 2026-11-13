import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/models/rider/rider_dashboard_models.dart';

void main() {
  test('pickup cards retain tracking, contents and handover state', () {
    final dashboard = RiderDashboard.fromJson({
      'seller_pickups': [
        {
          'id': 7, 'tracking_number': 'TRK-7', 'seller': 'Shop',
          'pickup_address': 'Manila', 'status': 'picked_up',
          'items': [{'name': 'Blender', 'quantity': 2}],
          'scanned_at': '2026-11-13T04:00:00Z',
        },
        {
          'id': 8, 'tracking_number': 'TRK-8', 'status': 'handed_over',
          'received_at': '2026-11-13T05:00:00Z',
        },
      ],
    });
    expect(dashboard.sellerPickups.first.trackingNumber, 'TRK-7');
    expect(dashboard.sellerPickups.first.items, ['Blender × 2']);
    expect(dashboard.sellerPickups.first.canScan, false);
    expect(dashboard.sellerPickups.first.scannedAt, isNotNull);
    expect(dashboard.sellerPickups.last.statusLabel, 'Handover confirmed by logistics');
    expect(dashboard.sellerPickups.last.receivedAt, isNotNull);
    expect(RiderDashboard.fromJson({}).sellerPickups, isEmpty);
  });
}
