import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/api/buyer_api.dart';
import 'package:zaylo_mobile/auth/buyer/login_screen.dart';
import 'package:zaylo_mobile/auth/buyer/registration_screen.dart';
import 'package:zaylo_mobile/auth/rider/rider_login_screen.dart';
import 'package:zaylo_mobile/auth/widgets/role_selection_cards.dart';
import 'package:zaylo_mobile/state/app_state.dart';

void main() {
  Widget testHarness(Widget child) => AppScope(
        state: AppState(api: BuyerApi()),
        child: MaterialApp(home: child),
      );

  testWidgets('AccountTypeSelectionScreen for registration shows Buyer and Rider selection cards without Courier', (tester) async {
    tester.view.physicalSize = const Size(400, 3200);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });

    await tester.pumpWidget(testHarness(const AccountTypeSelectionScreen(isLogin: false)));

    expect(find.text('Create New Account'), findsOneWidget);
    expect(find.text('Buyer'), findsOneWidget);
    expect(find.text('Delivery Rider'), findsOneWidget);
    expect(find.text('Pickup Courier'), findsNothing);
    expect(find.text('Courier'), findsNothing);

    // Tapping Buyer navigates to RegistrationScreen
    await tester.tap(find.text('Buyer'));
    await tester.pumpAndSettle();
    expect(find.byType(RegistrationScreen), findsOneWidget);
  });

  testWidgets('AccountTypeSelectionScreen for login shows Buyer and Rider selection cards without Courier', (tester) async {
    tester.view.physicalSize = const Size(400, 3200);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });

    await tester.pumpWidget(testHarness(const AccountTypeSelectionScreen(isLogin: true)));

    expect(find.text('Sign In to ZAYLO'), findsOneWidget);
    expect(find.text('Buyer'), findsOneWidget);
    expect(find.text('Delivery Rider'), findsOneWidget);
    expect(find.text('Pickup Courier'), findsNothing);
    expect(find.text('Courier'), findsNothing);

    // Tapping Delivery Rider navigates to RiderLoginScreen
    await tester.tap(find.text('Delivery Rider'));
    await tester.pumpAndSettle();
    expect(find.byType(RiderLoginScreen), findsOneWidget);
  });

  testWidgets('AuthRoleCards switches between Buyer and Rider on login screens', (tester) async {
    tester.view.physicalSize = const Size(400, 3200);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });

    await tester.pumpWidget(testHarness(const LoginScreen()));

    expect(find.byType(LoginScreen), findsOneWidget);
    expect(find.text('Buyer'), findsOneWidget);
    expect(find.text('Rider'), findsOneWidget);
    expect(find.text('Courier'), findsNothing);

    // Switch to Rider
    await tester.tap(find.text('Rider'));
    await tester.pumpAndSettle();
    expect(find.byType(RiderLoginScreen), findsOneWidget);

    // Switch back to Buyer
    await tester.tap(find.text('Buyer'));
    await tester.pumpAndSettle();
    expect(find.byType(LoginScreen), findsOneWidget);
  });
}
