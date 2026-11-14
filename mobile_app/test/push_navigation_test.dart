import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/api/api_client.dart';
import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/buyer_api.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/features/buyer/orders/order_detail_screen.dart';
import 'package:zaylo_mobile/features/rider/dashboard/rider_delivery_detail_screen.dart';
import 'package:zaylo_mobile/features/rider/dashboard/rider_pickup_queue_screen.dart';
import 'package:zaylo_mobile/models/auth_models.dart';
import 'package:zaylo_mobile/notifications/phone_notifications_screen.dart';
import 'package:zaylo_mobile/notifications/push_navigation.dart';
import 'package:zaylo_mobile/notifications/push_notifications.dart';
import 'package:zaylo_mobile/state/app_state.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('order notification cannot open under a signed-out or different buyer account', () async {
    final api = BuyerApi()..token = 'fixture-session';
    final state = AppState(api: api);
    addTearDown(state.dispose);
    final tap = PushTap(PushSession(api, 7, rider: false), 'order', 12);
    expect(await pushDestination(tap, state), isNull);
    state.updateUser(const UserProfile(id: 8, name: 'Other buyer', email: 'other@example.test'));
    expect(await pushDestination(tap, state), isNull);
    state.updateUser(const UserProfile(id: 7, name: 'Buyer', email: 'buyer@example.test'));
    final screen = await pushDestination(tap, state);
    expect(screen, isA<OrderDetailScreen>());
    expect((screen as OrderDetailScreen).orderId, 12);
    final differentClient = BuyerApi()..token = 'another-session';
    expect(await pushDestination(PushTap(PushSession(differentClient, 7, rider: false), 'order', 12), state), isNull);
  });

  test('rider notifications open their own parcel or pickup queue and reject revoked sessions', () async {
    final state = AppState(api: BuyerApi());
    addTearDown(state.dispose);
    final api = RiderApi()..token = 'fixture-rider-session';
    final session = PushSession(api, 7, rider: true);
    final screen = await pushDestination(PushTap(session, 'delivery', 42), state);
    expect(screen, isA<RiderDeliveryDetailScreen>());
    expect((screen as RiderDeliveryDetailScreen).api, same(api));
    expect(screen.deliveryId, 42);
    expect(await pushDestination(PushTap(session, 'rider_pickups', 42), state), isA<RiderPickupQueueScreen>());
    api.token = null;
    expect(await pushDestination(PushTap(session, 'delivery', 42), state), isNull);
  });

  testWidgets('phone notification test shows actionable registration errors', (tester) async {
    await tester.pumpWidget(MaterialApp(home: PhoneNotificationsScreen(api: _NoRegistrationApi())));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Send test notification'));
    await tester.pumpAndSettle();
    expect(find.text('Enable phone notifications first, then try again.'), findsOneWidget);
    expect(find.text('Send test notification'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

class _NoRegistrationApi extends ApiClient {
  @override
  Future<String> testPush() async => throw const ApiException('Enable phone notifications first, then try again.', statusCode: 422);
}
