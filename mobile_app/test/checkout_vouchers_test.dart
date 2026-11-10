import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/features/buyer/checkout/checkout_screen.dart';
import 'package:zaylo_mobile/models/buyer/cart_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'app_test.dart' show FakeApi;

class VoucherApi extends FakeApi {
  VoucherApi(this.count);
  final int count;
  final claimed = <String>{};

  @override
  Future<Map<String, dynamic>> claimVoucher(String code) async {
    claimed.add(code);
    return {'status': 'claimed'};
  }

  @override
  Future<CheckoutQuote> quote(List<int> ids, {String? voucherCode, String paymentMethod = 'cod'}) async {
    final base = await super.quote(ids, voucherCode: voucherCode, paymentMethod: paymentMethod);
    return CheckoutQuote(
      items: base.items, totals: base.totals, addresses: base.addresses,
      voucherCode: voucherCode,
      availableVouchers: [
        for (var i = 1; i <= count; i++)
          AvailableVoucher.fromJson({
            'id': i, 'code': 'SHOP$i', 'type': 'fixed', 'offer_label': '50 OFF',
            'description': 'Save 50', 'can_apply': true, 'shop_name': 'Garden Store',
            'can_claim': !claimed.contains('SHOP$i'), 'is_claimed': claimed.contains('SHOP$i'),
          }),
      ],
    );
  }
}

void main() {
  Future<void> pumpCheckout(WidgetTester tester, VoucherApi api) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(400, 3000);
    addTearDown(tester.view.reset);
    await tester.pumpWidget(AppScope(
      state: AppState(api: api),
      child: const MaterialApp(home: CheckoutScreen(cartItemIds: [1])),
    ));
    await tester.pumpAndSettle();
  }

  testWidgets('one claimable voucher stays inline', (tester) async {
    await pumpCheckout(tester, VoucherApi(1));
    expect(find.text('SHOP1'), findsOneWidget);
    expect(find.text('Claim'), findsOneWidget);
    expect(find.byKey(const ValueKey('available-vouchers-button')), findsNothing);
  });

  testWidgets('multiple vouchers open a dismissible shop modal and claim updates checkout', (tester) async {
    final api = VoucherApi(2);
    await pumpCheckout(tester, api);
    final button = find.byKey(const ValueKey('available-vouchers-button'));
    expect(find.text('SHOP1'), findsNothing);
    await tester.ensureVisible(button);
    await tester.tap(button);
    await tester.pumpAndSettle();
    expect(find.byType(AlertDialog), findsOneWidget);
    expect(find.text('SHOP1'), findsOneWidget);
    expect(find.text('SHOP2'), findsOneWidget);
    await tester.tap(find.byTooltip('Close available vouchers'));
    await tester.pumpAndSettle();
    expect(find.byType(AlertDialog), findsNothing);
    await tester.tap(button);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Claim').first);
    await tester.pumpAndSettle();
    expect(api.claimed, contains('SHOP1'));
    expect(find.byType(AlertDialog), findsNothing);
    expect(button, findsNothing);
    expect(find.text('SHOP1 applied'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
