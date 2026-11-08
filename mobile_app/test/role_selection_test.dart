import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/auth/buyer/registration_screen.dart';
import 'package:zaylo_mobile/auth/courier/courier_login_screen.dart';
import 'package:zaylo_mobile/auth/rider/rider_login_screen.dart';
import 'package:zaylo_mobile/auth/widgets/role_selection_cards.dart';

void main() {
  testWidgets('AccountTypeSelectionScreen for registration shows Buyer, Rider, and Courier selection cards', (tester) async {
    tester.view.physicalSize = const Size(400, 3200);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });

    await tester.pumpWidget(
      const MaterialApp(
        home: AccountTypeSelectionScreen(isLogin: false),
      ),
    );

    expect(find.text('Create New Account'), findsOneWidget);
    expect(find.text('Buyer'), findsOneWidget);
    expect(find.text('Delivery Rider'), findsOneWidget);
    expect(find.text('Pickup Courier'), findsOneWidget);

    // Tapping Buyer navigates to RegistrationScreen
    await tester.tap(find.text('Buyer'));
    await tester.pumpAndSettle();
    expect(find.byType(RegistrationScreen), findsOneWidget);
  });

  testWidgets('AccountTypeSelectionScreen for login shows Buyer, Rider, and Courier selection cards', (tester) async {
    tester.view.physicalSize = const Size(400, 3200);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });

    await tester.pumpWidget(
      const MaterialApp(
        home: AccountTypeSelectionScreen(isLogin: true),
      ),
    );

    expect(find.text('Sign In to ZAYLO'), findsOneWidget);
    expect(find.text('Buyer'), findsOneWidget);
    expect(find.text('Delivery Rider'), findsOneWidget);
    expect(find.text('Pickup Courier'), findsOneWidget);

    // Tapping Delivery Rider navigates to RiderLoginScreen
    await tester.tap(find.text('Delivery Rider'));
    await tester.pumpAndSettle();
    expect(find.byType(RiderLoginScreen), findsOneWidget);
  });

  testWidgets('AccountTypeSelectionScreen for login can navigate to CourierLoginScreen', (tester) async {
    tester.view.physicalSize = const Size(400, 3200);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });

    await tester.pumpWidget(
      const MaterialApp(
        home: AccountTypeSelectionScreen(isLogin: true),
      ),
    );

    await tester.tap(find.text('Pickup Courier'));
    await tester.pumpAndSettle();
    expect(find.byType(CourierLoginScreen), findsOneWidget);
  });
}
