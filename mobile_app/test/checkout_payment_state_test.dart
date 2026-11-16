import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/api/buyer_api.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/features/buyer/checkout/checkout_screen.dart';
import 'package:zaylo_mobile/models/buyer/cart_models.dart';
import 'package:zaylo_mobile/features/buyer/checkout/order_success_screen.dart';
import 'package:zaylo_mobile/models/buyer/order_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';

Order paymentOrder(int id, {String receipt = 'pending', String escrow = 'pending'}) => Order.fromJson({
  'id': id, 'order_number': 'ZAY-PAYMENT-$id', 'status': receipt == 'paid' ? 'placed' : 'awaiting_payment', 'status_label': 'To pay',
  'status_message': '', 'subtotal': 1000, 'shipping_fee': 120, 'shipping_discount': 0, 'total': 1120,
  'payment_method': 'gcash', 'payment_status': receipt,
  'gcash': {'gateway': true, 'status': escrow, 'can_pay': escrow == 'pending', 'amount': 1120},
});

class PaymentApi extends BuyerApi {
  @override
  Future<CheckoutQuote> quote(List<int> cartItemIds, {String? voucherCode, String paymentMethod = 'cod'}) async =>
      const CheckoutQuote(items: [], addresses: [], gcashGateway: true, gatewayAvailable: true,
        totals: CheckoutTotals(subtotal: 1000, shipping: 120, shippingDiscount: 0, total: 1120));
  Map<int, Order> saved = {1: paymentOrder(1)};
  bool offline = false;
  @override
  Future<Order> order(int id) async {
    if (offline) throw Exception('Offline');
    return saved[id]!;
  }
}

void main() {
  testWidgets('GCash selection changes the action to Pay via GCash in blue and COD restores Place Order', (tester) async {
    final state = AppState(api: PaymentApi());
    await tester.pumpWidget(AppScope(state: state, child: const MaterialApp(
      home: CheckoutScreen(cartItemIds: [1]),
    )));
    await tester.pumpAndSettle();
    expect(find.text('PLACE ORDER'), findsOneWidget);
    await Scrollable.ensureVisible(tester.element(find.text('GCash')), alignment: .5);
    await tester.pumpAndSettle();
    await tester.tap(find.text('GCash'));
    await tester.pumpAndSettle();
    expect(find.text('Pay via GCash'), findsOneWidget);
    final action = tester.widget<PrimaryButton>(find.widgetWithText(PrimaryButton, 'Pay via GCash'));
    expect(action.style?.backgroundColor?.resolve({}), const Color(0xFF0065FF));
    await Scrollable.ensureVisible(tester.element(find.text('Cash on Delivery')), alignment: .5);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Cash on Delivery'));
    await tester.pumpAndSettle();
    expect(find.text('PLACE ORDER'), findsOneWidget);
    expect(find.text('Pay via GCash'), findsNothing);
    await tester.pumpWidget(const SizedBox());
    state.dispose();
  });
  test('a paid receipt alone cannot confirm gateway payment', () {
    expect(paymentOrder(1, receipt: 'paid').isPaymentConfirmed, isFalse);
    expect(paymentOrder(1, escrow: 'held').isPaymentConfirmed, isFalse);
    expect(paymentOrder(1, receipt: 'paid', escrow: 'held').isPaymentConfirmed, isTrue);
    expect(paymentOrder(1, receipt: 'paid', escrow: 'released').isPaymentConfirmed, isTrue);
  });

  testWidgets('unpaid checkout stays pending, then verifies server payment when the app resumes', (tester) async {
    final api = PaymentApi();
    final state = AppState(api: api);
    await tester.pumpWidget(AppScope(state: state, child: MaterialApp(
      home: OrderSuccessScreen(orders: [paymentOrder(1)], paymentMethod: 'gcash'),
    )));
    await tester.pumpAndSettle();
    expect(find.text('Payment required'), findsOneWidget);
    expect(find.text('Order placed'), findsNothing);
    expect(find.byIcon(Icons.check_rounded), findsNothing);
    api.saved[1] = paymentOrder(1, receipt: 'paid', escrow: 'held');
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    await tester.pumpAndSettle();
    expect(find.text('Order placed'), findsOneWidget);
    expect(find.text('Payment required'), findsNothing);
    expect(find.text('Paid'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    state.dispose();
  });

  testWidgets('partially paid multi-seller checkout and verification errors never show success', (tester) async {
    final api = PaymentApi()..saved[2] = paymentOrder(2, receipt: 'paid', escrow: 'held');
    final state = AppState(api: api);
    await tester.pumpWidget(AppScope(state: state, child: MaterialApp(
      home: OrderSuccessScreen(orders: api.saved.values.toList(), paymentMethod: 'gcash'),
    )));
    await tester.pumpAndSettle();
    expect(find.text('Payment required'), findsOneWidget);
    expect(find.text('Paid'), findsOneWidget);
    api.offline = true;
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    await tester.pumpAndSettle();
    expect(find.text('Payment required'), findsOneWidget);
    expect(find.text('Order placed'), findsNothing);
    await tester.scrollUntilVisible(find.text('Unable to verify payment. Check again or open My Orders.'), 150);
    expect(find.text('Unable to verify payment. Check again or open My Orders.'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    state.dispose();
  });
}
