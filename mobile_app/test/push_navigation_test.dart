import 'package:flutter/material.dart';
import 'dart:async';
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
import 'package:zaylo_mobile/notifications/phone_notification_access.dart';
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

  testWidgets('allowed permission still shows Firebase registration failures and never sends an unregistered test', (tester) async {
    final api = _TestPushApi();
    final access = _PhoneAccess(error: const ApiException('Firebase registration timed out. Check your connection and try again.'));
    await tester.pumpWidget(MaterialApp(home: PhoneNotificationsScreen(api: api, notifications: access)));
    await tester.pumpAndSettle();
    expect(find.text('Allowed'), findsOneWidget);
    await tester.tap(find.text('Send test notification'));
    await tester.pumpAndSettle();
    expect(find.text('Firebase registration timed out. Check your connection and try again.'), findsOneWidget);
    expect(find.text('Allowed'), findsOneWidget);
    expect(api.calls, 0);
    expect(find.text('Send test notification'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('test send waits for successful phone registration', (tester) async {
    final api = _TestPushApi();
    final ready = Completer<void>();
    await tester.pumpWidget(MaterialApp(home: PhoneNotificationsScreen(api: api, notifications: _PhoneAccess(ready: ready.future))));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Send test notification'));
    await tester.pump();
    expect(api.calls, 0);
    expect(find.text('Sending…'), findsOneWidget);
    ready.complete();
    await tester.pumpAndSettle();
    expect(api.calls, 1);
    expect(find.text('Test notification queued.'), findsOneWidget);
  });

  testWidgets('registration upload failures remain visible without blaming phone permission', (tester) async {
    final api = _TestPushApi();
    await tester.pumpWidget(MaterialApp(home: PhoneNotificationsScreen(api: api,
      notifications: _PhoneAccess(error: const ApiException('Too many attempts. Please wait a moment and try again.', statusCode: 429)))));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Send test notification'));
    await tester.pumpAndSettle();
    expect(find.text('Too many attempts. Please wait a moment and try again.'), findsOneWidget);
    expect(find.text('Allowed'), findsOneWidget);
    expect(api.calls, 0);
  });
}

class _TestPushApi extends ApiClient {
  int calls = 0;
  @override
  Future<String> testPush() async { calls++; return 'Test notification queued.'; }
}

class _PhoneAccess implements PhoneNotificationAccess {
  _PhoneAccess({this.error, this.ready});
  final ApiException? error;
  final Future<void>? ready;
  @override
  Future<String> permissionStatus() async => 'Allowed';
  @override
  Future<void> ensureRegistered(ApiClient api) async {
    if (error != null) throw error!;
    await ready;
  }
  @override
  Future<void> openSettings() async {}
}
