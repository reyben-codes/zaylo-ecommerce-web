import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/features/rider/dashboard/widgets/parcel_handover_button.dart';
import 'package:zaylo_mobile/models/buyer/order_models.dart';
import 'package:zaylo_mobile/models/rider/rider_dashboard_models.dart';
import 'package:zaylo_mobile/notifications/account_update_badge.dart';

class FakeSettlementApi extends RiderApi {
  int handovers = 0;
  @override
  Future<String> confirmParcelHandover(int id) async { handovers++; return 'Handover confirmed'; }
  @override
  Future<Map<String, dynamic>> accountUpdates() async => {'unread': 3, 'counts': {'wallet': 2, 'orders': 1}};
}

void main() {
  test('a collected pickup exposes pending commission without relocking handover', () {
    final pickup = RiderSellerPickup.fromJson({'id': 1, 'tracking_number': 'TRK-1', 'status': 'picked_up',
      'commission_pending': true, 'can_confirm_handover': true, 'items': []});
    expect(pickup.commissionPending, isTrue);
    expect(pickup.canConfirmHandover, isTrue);
    expect(pickup.canScan, isFalse);
  });
  test('gateway payments never ask buyers for a manual reference', () {
    final payment = OrderGcash.fromJson({'gateway': true, 'status': 'pending', 'can_submit': false, 'can_pay': true, 'amount': 1120});
    expect(payment.gateway, isTrue);
    expect(payment.canPay, isTrue);
    expect(payment.canSubmit, isFalse);
    expect(payment.sellerNumber, isNull);
  });

  testWidgets('handover stays locked until a scan and sends one confirmation when unlocked', (tester) async {
    final api = FakeSettlementApi();
    var changed = false;
    Widget app(bool enabled) => MaterialApp(home: Scaffold(body: ParcelHandoverButton(api: api, shipmentId: 1,
        enabled: enabled, confirmed: false, onChanged: () => changed = true)));
    await tester.pumpWidget(app(false));
    expect(tester.widget<OutlinedButton>(find.byType(OutlinedButton)).onPressed, isNull);
    expect(api.handovers, 0);
    await tester.pumpWidget(app(true));
    await tester.tap(find.text('Confirm Handover to Logistics'));
    await tester.pump();
    expect(api.handovers, 1);
    expect(changed, isTrue);
    await tester.pumpWidget(const SizedBox());
  });

  testWidgets('wallet navigation badge displays only that sessions wallet updates', (tester) async {
    final api = FakeSettlementApi()..token = 'test-session';
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: AccountUpdateBadge(api: api, section: 'wallet', child: const Icon(Icons.wallet)))));
    await tester.pump();
    expect(find.text('2'), findsOneWidget);
    expect(find.text('3'), findsNothing);
    await tester.pumpWidget(const SizedBox());
  });
}
