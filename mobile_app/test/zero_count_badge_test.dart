import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/api/buyer_api.dart';
import 'package:zaylo_mobile/features/buyer/orders/orders_screen.dart';
import 'package:zaylo_mobile/features/rider/dashboard/widgets/rider_dashboard_widgets.dart';
import 'package:zaylo_mobile/models/buyer/order_models.dart';
import 'package:zaylo_mobile/notifications/account_update_badge.dart';
import 'package:zaylo_mobile/state/app_state.dart';

class CounterApi extends BuyerApi {
  Map<String, int> counts = {'all': 2, 'to_pay': 0, 'cancelled': 0};
  int unread = 0;
  @override
  Future<OrderPage> orders({String status = 'all', int page = 1}) async =>
      OrderPage(orders: const [], counts: Map.of(counts), currentPage: 1, lastPage: 1);
  @override
  Future<Map<String, dynamic>> accountUpdates() async => {'unread': unread, 'counts': {'orders': unread}};
}

void main() {
  testWidgets('order tabs hide empty counts, then show and hide counts as live updates arrive', (tester) async {
    final api = CounterApi();
    final state = AppState(api: api);
    await tester.pumpWidget(AppScope(state: state, child: const MaterialApp(home: OrdersScreen())));
    await tester.pumpAndSettle();
    expect(find.text('0'), findsNothing);
    expect(find.text('2'), findsOneWidget);
    api.counts = {'all': 0, 'to_pay': 3, 'cancelled': 0};
    await tester.pump(const Duration(seconds: 15));
    await tester.pumpAndSettle();
    expect(find.text('2'), findsNothing);
    expect(find.text('3'), findsOneWidget);
    api.counts = {};
    await tester.pump(const Duration(seconds: 15));
    await tester.pumpAndSettle();
    expect(find.byType(Badge), findsNothing);
    expect(find.text('0'), findsNothing);
    await tester.pumpWidget(const SizedBox());
    state.dispose();
  });

  testWidgets('rider section badges appear only for positive counts', (tester) async {
    for (final count in [null, 0, -1, 4, 0]) {
      await tester.pumpWidget(MaterialApp(home: Scaffold(body: RiderSectionHeader(title: 'Assigned pickups', count: count))));
      expect(find.text('Assigned pickups'), findsOneWidget);
      expect(find.text('$count'), count != null && count > 0 ? findsOneWidget : findsNothing);
    }
  });

  testWidgets('navigation notification badge remains hidden at zero and reappears for new updates', (tester) async {
    final api = CounterApi()..token = 'session';
    await tester.pumpWidget(MaterialApp(home: Scaffold(body:
      AccountUpdateBadge(api: api, section: 'orders', child: const Icon(Icons.shopping_bag)))));
    await tester.pump();
    expect(tester.widget<Badge>(find.byType(Badge)).isLabelVisible, isFalse);
    api.unread = 5;
    await tester.pump(const Duration(seconds: 15));
    await tester.pump();
    expect(tester.widget<Badge>(find.byType(Badge)).isLabelVisible, isTrue);
    api.unread = 0;
    await tester.pump(const Duration(seconds: 15));
    await tester.pump();
    expect(tester.widget<Badge>(find.byType(Badge)).isLabelVisible, isFalse);
    await tester.pumpWidget(const SizedBox());
  });
}
