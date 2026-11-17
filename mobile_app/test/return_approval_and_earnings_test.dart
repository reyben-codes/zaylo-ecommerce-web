import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/components/complaints.dart';
import 'package:zaylo_mobile/models/rider/rider_wallet_models.dart';
import 'package:zaylo_mobile/models/support_models.dart';

void main() {
  test('wallet preserves separate first-mile and last-mile totals', () {
    final wallet = RiderWallet.fromJson({
      'balance': 36, 'earned': 36,
      'first_mile_earned': 15, 'last_mile_earned': 21,
    });
    expect(wallet.firstMileEarned, 15);
    expect(wallet.lastMileEarned, 21);
    expect(wallet.firstMileEarned + wallet.lastMileEarned, wallet.earned);
    expect(RiderWallet.fromJson({'earned': 40}).lastMileEarned, 40);
  });

  testWidgets('pending approval does not tell buyers to arrange a rider or show a barcode', (tester) async {
    final info = ComplaintReturn.fromJson({
      'tracking_number': 'RET-PENDING', 'method': 'pickup',
      'status': 'pending_seller', 'status_label': 'Waiting for seller approval',
      'barcode': [2, 1, 2, 1],
    });
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: ReturnCard(info: info))));
    expect(find.text('Waiting for seller approval before pickup or drop-off.'), findsOneWidget);
    expect(find.text('Your logistics partner will assign a rider before the pickup day.'), findsNothing);
    expect(find.bySemanticsLabel('Return slip barcode RET-PENDING'), findsNothing);
  });
}
