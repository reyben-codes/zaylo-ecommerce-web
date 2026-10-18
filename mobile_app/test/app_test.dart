import 'dart:io';
import 'dart:ui' show Tristate;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/buyer_api.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/app.dart';
import 'package:zaylo_mobile/auth/buyer/login_screen.dart';
import 'package:zaylo_mobile/auth/google_auth.dart';
import 'package:zaylo_mobile/auth/rider/rider_registration_screen.dart';
import 'package:zaylo_mobile/auth/rider/widgets/rider_terms_dialog.dart';
import 'package:zaylo_mobile/auth/token_store.dart';
import 'package:zaylo_mobile/components/announcement_banner.dart';
import 'package:zaylo_mobile/components/chat/delivery_chat.dart';
import 'package:zaylo_mobile/components/policies.dart';
import 'package:zaylo_mobile/features/buyer/checkout/checkout_screen.dart';
import 'package:zaylo_mobile/features/buyer/orders/order_detail_screen.dart';
import 'package:zaylo_mobile/features/buyer/home/widgets/home_header.dart';
import 'package:zaylo_mobile/models/auth_models.dart';
import 'package:zaylo_mobile/models/buyer/account_models.dart';
import 'package:zaylo_mobile/models/buyer/cart_models.dart';
import 'package:zaylo_mobile/models/buyer/catalog_models.dart';
import 'package:zaylo_mobile/models/buyer/order_models.dart';
import 'package:zaylo_mobile/models/buyer/seller_chat_models.dart';
import 'package:zaylo_mobile/models/location.dart';
import 'package:zaylo_mobile/models/rider/rider_activity_models.dart';
import 'package:zaylo_mobile/models/rider/rider_dashboard_models.dart';
import 'package:zaylo_mobile/models/rider/rider_models.dart';
import 'package:zaylo_mobile/models/support_models.dart';
import 'package:zaylo_mobile/navigation/rider_shell.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/theme_controller.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';
import 'package:zaylo_mobile/utils/rider_input_formatters.dart';
import 'package:zaylo_mobile/utils/rider_validators.dart';
import 'package:zaylo_mobile/utils/validators.dart';

/// JSON in the exact shape produced by app/Support/MobilePayload.php.
Map<String, dynamic> productJson(int id, {String name = 'Watering Can', double price = 499, double? original}) => {
      'id': id,
      'slug': 'product-$id',
      'name': '$name $id',
      'category': 'home_living',
      'category_label': 'Home & Living',
      'price': price,
      if (original != null) 'original_price': original,
      'has_options': false,
      'saved': false,
    };

Map<String, dynamic> cartJson(List<int> ids) => {
      'items': [
        for (final id in ids)
          {
            'id': id,
            'quantity': 1,
            'unit_price': 499.0,
            'line_total': 499.0,
            'available_stock': 10,
            'seller_id': 1,
            'seller_name': 'Garden Store',
            'product': productJson(id),
          },
      ],
      'count': ids.length,
      'subtotal': 499.0 * ids.length,
    };

class FakeApi extends BuyerApi {
  FakeApi({this.cartIds = const [], this.announcements = const [], this.identityVerified = true})
      : super(baseUrl: 'http://fake.test/api');

  final List<int> cartIds;

  /// Admin notices on the home screen, in the API's shape.
  final List<Map<String, dynamic>> announcements;

  /// Whether the buyer's ID is approved, which checkout requires.
  final bool identityVerified;

  @override
  Future<StorefrontHome> home() async => StorefrontHome.fromJson({
        'categories': [
          {'id': 'fashion', 'label': 'Fashion', 'image': 'http://fake.test/fashion.jpg'},
        ],
        'hero_images': ['http://fake.test/hero.jpg'],
        'new_arrivals': [productJson(1), productJson(2)],
        if (token != null) 'flash_sales': [productJson(3, name: 'Gloves', price: 399, original: 599)],
        if (token != null) 'suggested': [productJson(4)],
        'announcements': announcements,
      });

  @override
  Future<NotificationFeed> notifications() async => NotificationFeed.fromJson({'notifications': [], 'unread_count': 0});

  @override
  Future<int> sellerUnreadCount() async => 3;

  @override
  Future<int> supportUnreadCount() async => 0;

  @override
  Future<CheckoutQuote> quote(List<int> cartItemIds, {String? voucherCode}) async => CheckoutQuote.fromJson({
        'items': cartJson(cartItemIds)['items'],
        'totals': {'subtotal': 499.0, 'shipping': 120.0, 'shipping_discount': 0.0, 'total': 619.0},
        'addresses': [
          {
            'id': 1,
            'label': 'Home',
            'recipient_name': 'Maria Santos',
            'phone': '09171234567',
            'line1': '12 Rizal St.',
            'formatted': '12 Rizal St., Calamba, Laguna',
            'is_default': true,
            'is_structured': true,
          },
        ],
        'identity_verified': identityVerified,
      });

  @override
  Future<Order> order(int id) async => Order.fromJson({
        'id': id,
        'order_number': 'ZAY-DELIVERED',
        'status': 'completed',
        'status_label': 'Completed',
        'status_message': 'Your order has been delivered.',
        'subtotal': 998.0,
        'shipping_fee': 120.0,
        'shipping_discount': 0.0,
        'total': 1118.0,
        'payment_method': 'cod',
        'items': [
          {'id': 1, 'product_name': 'Watering Can', 'unit_price': 499.0, 'quantity': 1, 'line_total': 499.0, 'can_review': true},
          {'id': 2, 'product_name': 'Garden Gloves', 'unit_price': 499.0, 'quantity': 1, 'line_total': 499.0, 'review_rating': 4},
        ],
        'history': [],
        'complaint': null,
        'can_complain': true,
      });

  @override
  Future<UserProfile> me() async =>
      UserProfile.fromJson({'id': 7, 'name': 'Maria Santos', 'email': 'maria@example.test', 'phone': '09171234567'});

  @override
  Future<CartSummary> cart() async => CartSummary.fromJson(cartJson(cartIds));

  @override
  Future<List<Product>> wishlist() async => [];

  /// ID tokens sent to POST /mobile/google; "rejected" answers like a seller's account.
  final googleTokens = <String>[];

  @override
  Future<AuthResult> googleSignIn(String idToken) async {
    googleTokens.add(idToken);
    if (idToken == 'rejected') {
      throw ApiException.fromResponse(422, {
        'errors': {
          'google': ['This sign-in is for buyer accounts. Riders can use Login as a Rider; sellers can use the website.'],
        },
      });
    }
    return AuthResult.fromJson({
      'message': 'Signed in.',
      'token': 'google-session',
      'user': {'id': 7, 'name': 'Maria Santos', 'email': 'maria@example.test', 'google_linked': true, 'has_password': false},
    });
  }
}

/// Stands in for the phone's Google sign-in sheet.
class FakeGoogleIdTokenSource implements GoogleIdTokenSource {
  FakeGoogleIdTokenSource({this.available = true, this.token = 'google-id-token'});

  @override
  final bool available;

  /// What the account picker returns: a token, or null when the buyer closes it.
  final String? token;
  int signOuts = 0;

  @override
  Future<String?> idToken() async => token;

  @override
  Future<void> signOut() async => signOuts++;
}

/// Rider API that answers from memory, in the shape MobileRiderDashboardController returns.
class FakeRiderApi extends RiderApi {
  FakeRiderApi() : super(baseUrl: 'http://fake.test/api');

  /// Steps sent to [updateDelivery], as "id:status".
  final updates = <String>[];

  /// The steps the rider can take at each status, as the server sends them.
  static const _steps = {
    'assigned': [
      {'status': 'out_for_delivery', 'label': 'Start delivery'},
    ],
    'out_for_delivery': [
      {'status': 'delivered', 'label': 'Mark as delivered'},
      {'status': 'failed', 'label': 'Could not deliver'},
    ],
  };

  static Map<String, dynamic> delivery(int id, String status) => {
        'id': id,
        'tracking_number': 'TRK-$id',
        'status': status,
        'status_label': switch (status) {
          'assigned' => 'Rider assigned',
          'out_for_delivery' => 'Out for delivery',
          _ => 'Delivered',
        },
        'next_status': _steps[status]?.first['status'],
        'next_action_label': _steps[status]?.first['label'],
        'actions': _steps[status] ?? const [],
        'order_number': 'ZAY-ORDER-$id',
        'shipping_address': '12 Rizal St, Calamba, Laguna',
        'recipient_name': 'Maria Santos',
        'recipient_phone': '09170000000',
        'payment_method': 'cod',
        'cash_to_collect': 549.0,
        'delivery_fee': 49.0,
        'items': [
          {'name': 'Watering Can', 'quantity': 2},
          {'name': 'Gardening Gloves', 'quantity': 1},
        ],
        'pickup': {'store_name': 'Happy Heels', 'phone': '09171110000', 'address': 'Halang, Calamba, Laguna'},
        'chat_open': status != 'delivered',
        'unread_messages': status == 'assigned' ? 1 : 0,
      };

  final chatSent = <String>[];

  @override
  Future<DeliveryChat> deliveryChat(int id) async => DeliveryChat.fromJson({
        'open': true,
        'other_name': 'Maria',
        'other_role': 'Buyer',
        'order_number': 'ZAY-ORDER-$id',
        'messages': [
          {'id': 1, 'client_id': 'c1', 'body': 'The gate is blue.', 'mine': false, 'read': true, 'sent_at': '2026-10-12T08:00:00Z'},
        ],
      });

  @override
  Future<DeliveryChatMessage> sendDeliveryMessage(int id, String body, {required String clientId}) async {
    chatSent.add(body);
    return DeliveryChatMessage(id: 2, clientId: clientId, body: body, mine: true, sentAt: DateTime(2026, 10, 12));
  }

  @override
  Future<RiderDashboard> dashboard() async => RiderDashboard.fromJson({
        'rider': {
          'name': 'Juan Dela Cruz',
          'email': 'juan@example.com',
          'phone': '09556644889',
          'gender': 'male',
          'gender_label': 'Male',
          'address': {
            'region_code': '040000000',
            'province_code': '043400000',
            'city_municipality_code': '043405000',
            'barangay_code': '043405029',
            'line1': '12 Rizal St.',
            'postal_code': '4027',
            'formatted': '12 Rizal St., Halang, City of Calamba, Laguna, CALABARZON, 4027',
          },
          'logistics_partner': 'Express Delivery',
          'vehicle_label': 'Motorcycle',
          'plate_number': 'NBC1234',
          'license_number': 'S33-45-666666',
          'license_expires_on': '2026-10-20',
          'license_days_left': 9,
          'license_expiring_soon': true,
        },
        'delivery_mode': 'logistics',
        'stats': {'available': 0, 'active': 2, 'completed': 3, 'completed_today': 1, 'earnings_today': 49, 'earnings_total': 147},
        'active': [delivery(1, 'assigned'), delivery(2, 'out_for_delivery')],
        'available': [],
        'recent': [],
      });

  @override
  Future<RiderDelivery> updateDelivery(int id, String status) async {
    updates.add('$id:$status');
    return RiderDelivery.fromJson(delivery(id, status));
  }

  @override
  Future<RiderSignUpOptions> signUpOptions() async => RiderSignUpOptions.fromJson({
        'partners': [
          {'id': 7, 'name': 'Express Delivery', 'service_area': 'Laguna'},
        ],
        'vehicle_types': ['Motorcycle', 'Bicycle', 'Car', 'Van', 'Truck'],
        'unlicensed_vehicle_types': ['Bicycle'],
      });

  final historyRequests = <RiderPeriod>[];

  @override
  Future<RiderHistoryPage> history({RiderPeriod period = RiderPeriod.all, int page = 1}) async {
    historyRequests.add(period);
    return RiderHistoryPage.fromJson({
      'summary': {'deliveries': 1, 'earnings': 49},
      'deliveries': [delivery(9, 'delivered')],
      'next_page': null,
    });
  }

  @override
  Future<RiderEarnings> earnings() async => RiderEarnings.fromJson({
        'periods': {
          'today': {'deliveries': 1, 'earnings': 49},
          'week': {'deliveries': 4, 'earnings': 196},
          'month': {'deliveries': 9, 'earnings': 441},
          'all': {'deliveries': 9, 'earnings': 441},
        },
        'last_7_days': [
          for (var day = 5; day <= 11; day++) {'date': '2026-10-${day.toString().padLeft(2, '0')}', 'deliveries': 1, 'earnings': 49},
        ],
      });

  // PSGC lookups: one path down to a barangay, like the real API's shape.
  @override
  Future<List<Location>> regions() async => const [Location(code: '040000000', name: 'CALABARZON')];

  @override
  Future<List<Location>> provinces(String regionCode) async => const [Location(code: '043400000', name: 'Laguna')];

  @override
  Future<List<Location>> regionCities(String regionCode) async => const [];

  @override
  Future<List<Location>> cities(String provinceCode) async =>
      const [Location(code: '043405000', name: 'City of Calamba')];

  @override
  Future<List<Location>> barangays(String cityCode) async => const [Location(code: '043405029', name: 'Halang')];

  int unread = 2;
  final sent = <String>[];

  @override
  Future<int> supportUnreadCount() async => unread;

  @override
  Future<List<SupportMessage>> supportMessages() async {
    unread = 0;
    return [
      SupportMessage.fromJson({'id': 1, 'client_id': 'a', 'body': 'Hi! How can we help?', 'from_staff': true}),
    ];
  }

  @override
  Future<SupportMessage> sendSupportMessage(String body, {String? clientId}) async {
    sent.add(body);
    return SupportMessage.fromJson({'id': 2, 'client_id': clientId, 'body': body, 'from_staff': false});
  }
}

Future<void> pumpApp(WidgetTester tester,
    {String? token, List<int> cart = const [], double width = 400, ThemeController? theme, FakeApi? api}) async {
  final tokens = MemoryTokenStore();
  if (token != null) await tokens.write(token);
  final state = AppState(api: api ?? FakeApi(cartIds: cart), tokens: tokens);
  // A phone-width screen tall enough that the lazily built home renders
  // every section, so absence checks are meaningful.
  tester.view.devicePixelRatio = 1;
  tester.view.physicalSize = Size(width, 4000);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(ZayloApp(state: state, theme: theme));
  await state.restore();
  await tester.pumpAndSettle(const Duration(milliseconds: 100), EnginePhase.sendSemanticsUpdate, const Duration(seconds: 2));
}

void main() {
  group('guest', () {
    testWidgets('home shows new arrivals but not the signed-in sections', (tester) async {
      await pumpApp(tester);

      expect(find.text('New Arrivals'), findsOneWidget);
      expect(find.text('Watering Can 1'), findsOneWidget);
      expect(find.text('Flash Sales'), findsNothing);
      expect(find.text('Suggested Products'), findsNothing);
    });

    testWidgets('bag tab asks the guest to sign in', (tester) async {
      await pumpApp(tester);

      await tester.tap(find.text('Bag'));
      await tester.pump();

      expect(find.text('Sign in to add products to your bag and check out.'), findsOneWidget);
      expect(find.text('SIGN IN'), findsOneWidget);
    });
  });

  group('signed in', () {
    testWidgets('restores the session and shows the dashboard sections', (tester) async {
      await pumpApp(tester, token: 'stored-token', cart: [11, 12]);

      expect(find.textContaining('Welcome back'), findsNothing, reason: 'the hero comes right after search');
      expect(find.text('Flash Sales'), findsOneWidget);
      expect(find.text('Suggested Products'), findsOneWidget);
      // Bag badge in the bottom navigation reflects the server cart.
      expect(find.descendant(of: find.byType(NavigationBar), matching: find.text('2')), findsOneWidget);
    });

    testWidgets('bag checks out only the selected items', (tester) async {
      await pumpApp(tester, token: 'stored-token', cart: [11, 12]);

      await tester.tap(find.text('Bag'));
      await tester.pump();
      expect(find.text('CHECKOUT (2)'), findsOneWidget);
      expect(find.text('Select all (2)'), findsOneWidget);

      // Uncheck the first item (index 0 is "Select all").
      await tester.tap(find.byType(Checkbox).at(1));
      await tester.pump();

      expect(find.text('CHECKOUT (1)'), findsOneWidget);
      expect(find.text('Selected items (1)'), findsOneWidget);
    });
  });

  testWidgets('home top bar hides while scrolling down and returns when the scroll stops', (tester) async {
    await pumpApp(tester, width: 400);
    tester.view.physicalSize = const Size(400, 800);
    await tester.pumpAndSettle();
    double topBarOffset() => tester
        .widget<AnimatedSlide>(find.ancestor(of: find.byTooltip('Menu'), matching: find.byType(AnimatedSlide)))
        .offset
        .dy;
    expect(topBarOffset(), 0);

    final gesture = await tester.startGesture(const Offset(200, 500));
    await gesture.moveBy(const Offset(0, -150));
    await tester.pump();
    await gesture.moveBy(const Offset(0, -150));
    await tester.pump();
    expect(topBarOffset(), -1);

    await gesture.up();
    await tester.pumpAndSettle();
    expect(topBarOffset(), 0);
  });

  testWidgets('theme toggle switches to dark mode, persists, and returns to the system setting', (tester) async {
    addTearDown(() => ZayloColors.use(Brightness.light));
    final store = MemoryThemeModeStore();
    await pumpApp(tester, theme: ThemeController(store: store));
    Color background() => tester.widget<Scaffold>(find.byType(Scaffold).first).backgroundColor ??
        Theme.of(tester.element(find.byType(Scaffold).first)).scaffoldBackgroundColor;
    expect(background(), ZayloPalette.light.cream);

    await tester.tap(find.byTooltip('Switch to dark mode'));
    await tester.pumpAndSettle();

    expect(ZayloColors.isDark, isTrue);
    expect(background(), ZayloPalette.dark.cream);
    expect(await store.read(), ThemeMode.dark);

    // The menu has the labelled toggle. The test platform reports light, so
    // switching back follows the system again, as on the web.
    await tester.tap(find.byTooltip('Menu'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Light mode'));
    await tester.pumpAndSettle();
    expect(ZayloColors.isDark, isFalse);
    expect(background(), ZayloPalette.light.cream);
    expect(await store.read(), ThemeMode.system);
  });

  testWidgets('menu opens the rider login screen', (tester) async {
    // Widget tests answer every HTTP request with 400, so the masthead's
    // network photo fails to load. That is expected here; anything else is not.
    final reportError = FlutterError.onError;
    FlutterError.onError = (details) {
      if (details.exception is! NetworkImageLoadException) reportError?.call(details);
    };
    addTearDown(() => FlutterError.onError = reportError);
    await pumpApp(tester);

    await tester.tap(find.byTooltip('Menu'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Login as a Rider'));
    await tester.pumpAndSettle();

    expect(find.text('Welcome Back, Rider'), findsOneWidget);
    expect(find.text('SIGN IN AS RIDER'), findsOneWidget);
  });

  testWidgets('rider registration collects personal details, then partner, vehicle and documents', (tester) async {
    final reportError = FlutterError.onError;
    FlutterError.onError = (details) {
      if (details.exception is! NetworkImageLoadException) reportError?.call(details);
    };
    addTearDown(() => FlutterError.onError = reportError);
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(400, 3200);
    addTearDown(tester.view.reset);
    await tester.pumpWidget(MaterialApp(home: RiderRegistrationScreen(api: FakeRiderApi())));
    await tester.pumpAndSettle();

    // Step 1 asks for name, date of birth, gender, and home address.
    expect(find.text('Register as a rider'), findsOneWidget);
    expect(find.text('Mobile number'), findsNothing);
    await tester.ensureVisible(find.text('CONTINUE TO RIDER DETAILS'));
    await tester.tap(find.text('CONTINUE TO RIDER DETAILS'));
    await tester.pumpAndSettle();
    expect(find.text('Select your date of birth.'), findsOneWidget);
    expect(find.text('Select your gender.'), findsOneWidget);
    expect(find.text('Choose your region.'), findsOneWidget);
    expect(find.text('Enter your house number and street.'), findsOneWidget);

    // Names drop numbers and symbols, cap at 50 characters, and capitalize each word.
    await tester.enterText(find.byType(TextFormField).at(0), 'ju4n# carlo');
    expect(find.text('Jun Carlo'), findsOneWidget);
    await tester.enterText(find.byType(TextFormField).at(0), 'a' * 60);
    expect(find.text('A${'a' * 49}'), findsOneWidget);
    await tester.enterText(find.byType(TextFormField).at(0), 'juan');
    expect(find.text('Juan'), findsOneWidget);
    await tester.enterText(find.byType(TextFormField).at(1), 'dela cruz');
    expect(find.text('Dela Cruz'), findsOneWidget);
    await tester.tap(find.byType(TextFormField).at(2));
    await tester.pumpAndSettle();
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(ChoiceChip, 'Female'));
    await tester.pumpAndSettle();

    // Address dropdowns cascade: region -> province -> city -> barangay.
    Future<void> choose(String field, String option) async {
      await tester.ensureVisible(find.text(field).first);
      await tester.tap(find.widgetWithText(InputDecorator, 'Choose ${field.toLowerCase()}'));
      await tester.pumpAndSettle();
      await tester.tap(find.text(option).last);
      await tester.pumpAndSettle();
    }

    await choose('Region', 'CALABARZON');
    await choose('Province', 'Laguna');
    await choose('City / Municipality', 'City of Calamba');
    await choose('Barangay', 'Halang');
    await tester.enterText(find.widgetWithText(TextFormField, 'e.g. 12 Rizal St., Purok 3'), '12 Rizal St.');
    await tester.enterText(find.widgetWithText(TextFormField, '4027'), '4027');
    await tester.ensureVisible(find.text('CONTINUE TO RIDER DETAILS'));
    await tester.tap(find.text('CONTINUE TO RIDER DETAILS'));
    await tester.pumpAndSettle();

    // Step 2: contact details, the logistics partner, vehicle, and license documents.
    expect(find.text('Your rider details'), findsOneWidget);
    for (final label in [
      'Email address',
      'Mobile number',
      'Who will you ride for?',
      "Driver's license number",
      "Driver's license (photo or PDF)",
      'License expiry date',
      'Vehicle type',
      'Plate number',
      'Vehicle OR/CR (photo or PDF)',
    ]) {
      expect(find.text(label), findsOneWidget, reason: label);
    }

    // The mobile number is rejected as soon as it starts with anything but 09.
    final mobileField = find.widgetWithText(TextFormField, '09171234567'); // located by its hint
    await tester.enterText(mobileField, '1');
    await tester.pump();
    expect(find.text('Your mobile number must start with 09.'), findsOneWidget);
    await tester.enterText(mobileField, '09171234567');
    await tester.pump();
    expect(find.text('Your mobile number must start with 09.'), findsNothing);

    // Nothing is sent until the documents are attached.
    await tester.ensureVisible(find.text('CONTINUE TO TERMS'));
    await tester.tap(find.text('CONTINUE TO TERMS'));
    await tester.pumpAndSettle();
    expect(find.text("Upload a photo or PDF of your driver's license."), findsOneWidget);
    expect(find.text('Upload a photo or PDF of your vehicle OR/CR.'), findsOneWidget);
    expect(find.text('Choose the logistics partner you are applying to.'), findsOneWidget);
    expect(find.text('Select your vehicle type.'), findsOneWidget);

    // Bicycles need no license, plate or OR/CR, so those fields go away.
    await tester.ensureVisible(find.text('Select your vehicle type'));
    await tester.tap(find.text('Select your vehicle type'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Bicycle').last);
    await tester.pumpAndSettle();
    expect(find.text("Driver's license number"), findsNothing);
    expect(find.text('Plate number'), findsNothing);
    expect(find.text("Bicycles need no driver's license, plate number or OR/CR."), findsOneWidget);
  });

  testWidgets('rider chats with the buyer of an active delivery', (tester) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(400, 4000);
    addTearDown(tester.view.reset);
    final api = FakeRiderApi();
    await tester.pumpWidget(MaterialApp(home: RiderShell(api: api)));
    await tester.pumpAndSettle();

    // Each delivery in progress offers a chat with its buyer.
    expect(find.text('Chat with Maria'), findsNWidgets(2));
    expect(find.text('Chat with buyer'), findsNothing);
    await tester.tap(find.text('Chat with Maria').first);
    await tester.pumpAndSettle();

    expect(find.text('The gate is blue.'), findsOneWidget);
    expect(find.text('Buyer · ZAY-ORDER-1'), findsOneWidget);
    await tester.enterText(find.byType(TextField), 'Arriving in 5 minutes');
    await tester.tap(find.byTooltip('Send'));
    await tester.pumpAndSettle();

    expect(api.chatSent, ['Arriving in 5 minutes']);
    expect(find.text('Arriving in 5 minutes'), findsOneWidget);
    // Leave the screen so its polling timer stops before the test ends.
    await tester.pageBack();
    await tester.pumpAndSettle();
  });

  testWidgets('rider dashboard shows assigned parcels and moves them to the next step', (tester) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(320, 4000);
    addTearDown(tester.view.reset);
    final api = FakeRiderApi();
    await tester.pumpWidget(MaterialApp(home: RiderShell(api: api)));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull, reason: 'fits a 320px phone');
    expect(find.text('Hi, Juan'), findsOneWidget);
    expect(find.text('Motorcycle · NBC1234'), findsOneWidget);
    // Sign out moved to the Account tab: no button on Home or in the top bar.
    expect(find.text('SIGN OUT'), findsNothing);
    expect(find.byTooltip('Sign out'), findsNothing);
    expect(find.textContaining('expires in 9 days'), findsOneWidget);
    expect(find.text('Earned today'), findsOneWidget);
    expect(find.text('Your active deliveries'), findsOneWidget);
    // Riders no longer claim parcels; the logistics partner assigns them.
    expect(find.text('Available deliveries'), findsNothing);
    expect(find.text('ACCEPT DELIVERY'), findsNothing);
    // Each card says where to collect the parcel and what is in it.
    expect(find.text('Happy Heels'), findsNWidgets(2));
    expect(find.text('Items to pick up (3)'), findsNWidgets(2));

    await tester.tap(find.text('START DELIVERY'));
    await tester.pumpAndSettle();
    expect(api.updates, ['1:out_for_delivery']);
    expect(find.text('Out for delivery. Drive safely!'), findsOneWidget);

    // Out for delivery: deliver it, or report that it could not be delivered.
    await tester.ensureVisible(find.text('COULD NOT DELIVER'));
    await tester.tap(find.text('COULD NOT DELIVER'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(TextButton, 'Could not deliver'));
    await tester.pumpAndSettle();
    expect(api.updates, ['1:out_for_delivery', '2:failed']);
  });

  group('rider app tabs', () {
    Future<FakeRiderApi> pumpRider(WidgetTester tester) async {
      tester.view.devicePixelRatio = 1;
      tester.view.physicalSize = const Size(400, 2400);
      addTearDown(tester.view.reset);
      final api = FakeRiderApi();
      await tester.pumpWidget(MaterialApp(home: RiderShell(api: api)));
      await tester.pumpAndSettle();
      return api;
    }

    testWidgets('the Active tile opens every delivery in progress', (tester) async {
      await pumpRider(tester);

      await tester.tap(find.text('Active'));
      await tester.pumpAndSettle();
      expect(find.text('Active deliveries'), findsOneWidget);
      expect(find.text('START DELIVERY'), findsOneWidget);
      expect(find.text('MARK AS DELIVERED'), findsOneWidget);
    });

    testWidgets('Delivered today opens history filtered to today', (tester) async {
      final api = await pumpRider(tester);

      await tester.tap(find.text('Delivered today'));
      await tester.pumpAndSettle();
      expect(find.text('Delivery history'), findsOneWidget, reason: 'History tab title');
      expect(api.historyRequests.last, RiderPeriod.today);
      expect(tester.widget<ChoiceChip>(find.widgetWithText(ChoiceChip, 'Today')).selected, isTrue);
      expect(find.text('Delivery fees earned'), findsOneWidget);
    });

    testWidgets('Earned today opens the earnings breakdown', (tester) async {
      await pumpRider(tester);

      await tester.tap(find.text('Earned today'));
      await tester.pumpAndSettle();
      expect(find.text('Earnings'), findsOneWidget);
      expect(find.text('This week'), findsOneWidget);
      expect(find.text('Last 7 days'), findsOneWidget);
    });

    testWidgets('support chat shows unread replies and sends a message', (tester) async {
      final api = await pumpRider(tester);
      expect(find.descendant(of: find.byType(NavigationBar), matching: find.text('2')), findsOneWidget,
          reason: 'unread support replies badge');

      await tester.tap(find.text('Chat'));
      await tester.pumpAndSettle();
      expect(find.text('Hi! How can we help?'), findsOneWidget);
      expect(find.descendant(of: find.byType(NavigationBar), matching: find.text('2')), findsNothing);

      await tester.enterText(find.widgetWithText(TextField, 'Message ZAYLO support'), 'The seller is closed.');
      await tester.tap(find.byTooltip('Send'));
      await tester.pumpAndSettle();
      expect(api.sent, ['The seller is closed.']);
      expect(find.text('The seller is closed.'), findsOneWidget);
    });

    testWidgets('account tab shows rider details and signs out', (tester) async {
      await pumpRider(tester);

      await tester.tap(find.text('Account'));
      await tester.pumpAndSettle();
      expect(find.text('Juan Dela Cruz'), findsOneWidget);
      expect(find.text('S33-45-666666'), findsOneWidget);
      expect(find.text('Male'), findsOneWidget);
      expect(find.text('12 Rizal St., Halang, City of Calamba, Laguna, CALABARZON, 4027'), findsOneWidget);

      // Editing the address opens the dropdowns already set to the saved place.
      await tester.tap(find.text('Home address'));
      await tester.pumpAndSettle();
      expect(find.text('SAVE ADDRESS'), findsOneWidget);
      expect(find.text('City of Calamba'), findsOneWidget);
      expect(find.text('Halang'), findsOneWidget);
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.text('Change your password'), findsOneWidget);
      expect(find.text('Cases about deliveries you handled'), findsOneWidget);
      expect(find.text('Terms & policies'), findsOneWidget);
      expect(find.text('SIGN OUT'), findsOneWidget);

      await tester.tap(find.text('SIGN OUT'));
      await tester.pumpAndSettle();
      expect(find.text('Sign out?'), findsOneWidget, reason: 'asks before signing out');
    });
  });

  group('continue with Google', () {
    late GoogleIdTokenSource original;
    setUp(() => original = GoogleAuth.source);
    tearDown(() => GoogleAuth.source = original);

    Future<(FakeApi, AppState)> openLogin(WidgetTester tester, FakeGoogleIdTokenSource google) async {
      GoogleAuth.source = google;
      final api = FakeApi();
      await pumpApp(tester, api: api);
      tester.state<NavigatorState>(find.byType(Navigator).first)
          .push(MaterialPageRoute(builder: (_) => const LoginScreen()));
      await tester.pumpAndSettle();
      final state = AppScope.read(tester.element(find.byType(LoginScreen)));
      return (api, state);
    }

    Future<void> tapGoogle(WidgetTester tester) async {
      await tester.ensureVisible(find.text('Continue with Google'));
      await tester.tap(find.text('Continue with Google'));
      await tester.pumpAndSettle();
    }

    testWidgets('signs the buyer in with the Google ID token', (tester) async {
      final (api, state) = await openLogin(tester, FakeGoogleIdTokenSource());

      await tapGoogle(tester);
      expect(api.googleTokens, ['google-id-token']);
      expect(state.signedIn, isTrue);
      expect(state.user?.email, 'maria@example.test');
      expect(find.byType(LoginScreen), findsNothing, reason: 'sign-in screens close');
    });

    testWidgets('closing Google\'s account picker changes nothing', (tester) async {
      final (api, state) = await openLogin(tester, FakeGoogleIdTokenSource(token: null));

      await tapGoogle(tester);
      expect(api.googleTokens, isEmpty);
      expect(state.signedIn, isFalse);
      expect(find.byType(LoginScreen), findsOneWidget);
    });

    testWidgets('shows why the server refused and lets the buyer pick another account', (tester) async {
      final google = FakeGoogleIdTokenSource(token: 'rejected');
      final (_, state) = await openLogin(tester, google);

      await tapGoogle(tester);
      expect(state.signedIn, isFalse);
      expect(find.textContaining('This sign-in is for buyer accounts.'), findsOneWidget);
      expect(google.signOuts, 1);
    });

    testWidgets('is hidden until Google sign-in is configured for the build', (tester) async {
      await openLogin(tester, FakeGoogleIdTokenSource(available: false));

      expect(find.text('Continue with Google'), findsNothing);
      expect(find.text('SIGN IN'), findsOneWidget);
    });
  });

  group('website features', () {
    /// Opens [screen] on top of the signed-in app.
    Future<void> open(WidgetTester tester, Widget screen) async {
      tester.state<NavigatorState>(find.byType(Navigator).first).push(MaterialPageRoute(builder: (_) => screen));
      await tester.pumpAndSettle();
    }

    testWidgets('account lists messages, support, complaints, ID verification and policies', (tester) async {
      await pumpApp(tester, token: 'stored-token');

      await tester.tap(find.text('Account'));
      await tester.pumpAndSettle();
      expect(find.text('Messages with sellers'), findsOneWidget);
      expect(find.text('3'), findsOneWidget, reason: 'unread seller messages badge');
      expect(find.text('Help & support'), findsOneWidget);
      expect(find.text('Complaints'), findsOneWidget);
      expect(find.text('ID verification'), findsOneWidget);
      expect(find.text('Terms & policies'), findsOneWidget);
    });

    testWidgets('checkout asks for an approved ID before placing an order', (tester) async {
      await pumpApp(tester, token: 'stored-token', api: FakeApi(cartIds: [11], identityVerified: false));
      await open(tester, const CheckoutScreen(cartItemIds: [11]));

      expect(find.text('Verify your ID to place orders'), findsOneWidget);
      expect(find.text('VERIFY MY ID'), findsOneWidget);
      final placeOrder = find.ancestor(of: find.text('PLACE ORDER'), matching: find.byType(FilledButton));
      expect(tester.widget<FilledButton>(placeOrder).onPressed, isNull);
    });

    testWidgets('verified buyers check out without the ID step', (tester) async {
      await pumpApp(tester, token: 'stored-token', api: FakeApi(cartIds: [11]));
      await open(tester, const CheckoutScreen(cartItemIds: [11]));

      expect(find.text('Verify your ID to place orders'), findsNothing);
      final placeOrder = find.ancestor(of: find.text('PLACE ORDER'), matching: find.byType(FilledButton));
      expect(tester.widget<FilledButton>(placeOrder).onPressed, isNotNull);
    });

    testWidgets('delivered orders offer reviews and reporting a problem', (tester) async {
      await pumpApp(tester, token: 'stored-token');
      await open(tester, const OrderDetailScreen(orderId: 9));

      expect(find.text('Rate this item'), findsOneWidget, reason: 'only the unrated item');
      expect(find.text('You rated '), findsOneWidget);
      await tester.scrollUntilVisible(find.text('Report a problem'), 200);
      expect(find.text('Report a problem'), findsOneWidget);

      await tester.tap(find.text('Rate this item'));
      await tester.pumpAndSettle();
      expect(find.text('Review your purchase'), findsOneWidget);
      await tester.tap(find.text('PUBLISH REVIEW'));
      await tester.pump();
      expect(find.text('Choose a star rating.'), findsOneWidget, reason: 'stars are required, as on the website');
    });

    testWidgets('admin announcements show on home and can be dismissed', (tester) async {
      await pumpApp(tester, api: FakeApi(announcements: [
        {'key': '1-100', 'title': 'Holiday delivery', 'body': 'Expect delays on Dec 24.'},
      ]));

      expect(find.text('Holiday delivery  Expect delays on Dec 24.', findRichText: true), findsOneWidget);
      await tester.tap(find.byTooltip('Dismiss announcement: Holiday delivery'));
      await tester.pumpAndSettle();
      expect(find.text('Holiday delivery  Expect delays on Dec 24.', findRichText: true), findsNothing);
      expect(find.byType(AnnouncementBanner, skipOffstage: false), findsOneWidget, reason: 'collapsed in place');
      expect(find.text('New Arrivals'), findsOneWidget, reason: 'still on the home screen');
    });

    test('new payloads parse in the API shapes', () {
      final product = Product.fromJson({...productJson(1), 'rating': 4.5, 'review_count': 12});
      expect(product.rating, 4.5);
      expect(product.reviewCount, 12);

      final reviews = ProductReviewPage.fromJson({
        'average': 4.5,
        'count': 2,
        'breakdown': {'5': 1, '4': 1, '3': 0, '2': 0, '1': 0},
        'reviews': [
          {'id': 1, 'rating': 5, 'buyer_name': 'Maria', 'photos': ['http://fake.test/a.jpg']},
        ],
        'page': 1,
        'last_page': 2,
      });
      expect(reviews.breakdown[5], 1);
      expect(reviews.reviews.single.photos, hasLength(1));
      expect(reviews.hasMore, isTrue);

      final identity = IdentityStatus.fromJson({
        'enabled': true,
        'verified': false,
        'submission': {'status': 'rejected', 'id_type_label': 'Passport', 'rejection_reason': 'Blurry photo'},
        'id_types': [
          {'id': 'philid', 'label': 'PhilSys National ID (PhilID)'},
        ],
      });
      expect(identity.rejected, isTrue);
      expect(identity.pending, isFalse);

      final complaint = Complaint.fromJson({
        'id': 3,
        'order_number': 'ZAY-1',
        'category_label': 'Damaged or defective item',
        'status': 'in_review',
        'status_label': 'In review',
        'closed': false,
        'can_reply': true,
        'evidence': [
          {'name': 'split.png', 'url': 'http://fake.test/e/0', 'is_image': true},
        ],
        'messages': [
          {'id': 9, 'body': 'Handed over intact.', 'mine': false, 'sender': 'Jun · Rider', 'attachment': null},
        ],
      });
      expect(complaint.evidence.single.isImage, isTrue);
      expect(complaint.messages.single.sender, 'Jun · Rider');

      final batch = SellerChatBatch.fromJson({
        'messages': [
          {'id': 4, 'body': 'Yes, 20 meters.', 'mine': false, 'created_at': '2026-10-17T08:00:00Z'},
        ],
        'has_older': false,
        'typing': true,
      });
      expect(batch.typing, isTrue);
      expect(batch.messages.single.clientId, 'server-4');
    });

    testWidgets('policy text renders headings and bullets like the website', (tester) async {
      await tester.pumpWidget(MaterialApp(
        home: Scaffold(
          body: Builder(
            builder: (context) => ListView(
              children: PolicyText.blocks(context, '## 1. Your account\n- Buyers must be at least 18.\n\nPlain paragraph.'),
            ),
          ),
        ),
      ));

      expect(find.text('1. Your account'), findsOneWidget);
      expect(find.text('Buyers must be at least 18.'), findsOneWidget);
      expect(find.text('Plain paragraph.'), findsOneWidget);
      expect(find.text('## 1. Your account'), findsNothing);
    });
  });

  group('rider terms pop-up', () {
    Future<List<bool>> openTerms(WidgetTester tester) async {
      final results = <bool>[];
      tester.view.devicePixelRatio = 1;
      tester.view.physicalSize = const Size(400, 700);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(MaterialApp(
        home: Builder(
          builder: (context) => TextButton(
            onPressed: () async => results.add(await showRiderTermsDialog(context)),
            child: const Text('open'),
          ),
        ),
      ));
      await tester.tap(find.text('open'));
      await tester.pumpAndSettle();
      return results;
    }

    testWidgets('accepts only after scrolling to the bottom', (tester) async {
      final results = await openTerms(tester);

      expect(find.text('Scroll to the bottom to read and accept the terms.'), findsOneWidget);
      final done = find.widgetWithText(FilledButton, 'SCROLL TO READ ALL');
      expect(tester.widget<FilledButton>(done).onPressed, isNull, reason: 'cannot accept unread terms');

      await tester.scrollUntilVisible(find.text('End of terms.'), 300, scrollable: find.byType(Scrollable).last);
      await tester.pumpAndSettle();
      expect(find.text("You've read the terms. They are now accepted."), findsOneWidget);

      await tester.tap(find.text('DONE'));
      await tester.pumpAndSettle();
      expect(results, [true]);
    });

    testWidgets('closing before the end does not accept', (tester) async {
      final results = await openTerms(tester);
      await tester.tap(find.byTooltip('Close'));
      await tester.pumpAndSettle();
      expect(results, [false]);
    });
  });

  testWidgets('home and bag fit a small 320px phone without overflow', (tester) async {
    await pumpApp(tester, token: 'stored-token', cart: [11, 12], width: 320);
    expect(tester.takeException(), isNull);
    expect(find.text('Flash Sales'), findsOneWidget);

    await tester.tap(find.text('Bag'));
    await tester.pump();
    expect(tester.takeException(), isNull);
    expect(find.text('CHECKOUT (2)'), findsOneWidget);
  });

  group('rules match the Laravel validators', () {
    test('StrongPassword', () {
      expect(strongPasswordError('Zaylo#2026'), isNull);
      expect(strongPasswordError('Ñandú#2026'), isNull, reason: 'Unicode letters count, as with \\p{Lu}/\\p{Ll}');
      expect(strongPasswordError('zaylo#2026'), isNotNull, reason: 'needs an uppercase letter');
      expect(strongPasswordError('Zaylo2026'), isNotNull, reason: 'needs a symbol');
      expect(strongPasswordError('Za#1'), isNotNull, reason: 'needs 8 characters');
    });

    test('PersonName accepts accented names like the server', () {
      expect(personNameError('José Ma. Dela Cruz-Peña'), isNull);
      expect(personNameError("O'Brien"), isNull);
      expect(personNameError('Juan2'), isNotNull);
      expect(personNameError(' '), isNotNull);
    });

    test('rider names allow letters and spaces only, 2 to 50 characters', () {
      expect(riderNameError('Juan'), isNull);
      expect(riderNameError('José Ñiño'), isNull);
      expect(riderNameError('Dela Cruz'), isNull);
      expect(riderNameError('Juan2'), isNotNull, reason: 'no numbers');
      expect(riderNameError("O'Brien"), isNotNull, reason: 'no symbols');
      expect(riderNameError('Ana-Marie'), isNotNull, reason: 'no symbols');
      expect(riderNameError('J'), isNotNull, reason: 'at least 2 characters');
      expect(riderNameError('Al'), isNull);
      expect(riderNameError('a' * 50), isNull);
      expect(riderNameError('a' * 51), isNotNull);
      expect(riderNameError('  '), isNotNull);
    });

    test('rider email needs a name, @, domain, and extension, with no spaces', () {
      expect(riderEmailError('juan@example.com'), isNull);
      expect(riderEmailError('juan.dela.cruz@mail.example.ph'), isNull);
      for (final bad in ['juan.example.com', 'juan@', '@example.com', 'juan@example', 'ju an@example.com', 'juan@.com', '']) {
        expect(riderEmailError(bad), isNotNull, reason: bad);
      }
    });

    test('rider mobile number is 11 digits starting with 09', () {
      expect(riderPhoneError('09171234567'), isNull);
      expect(riderPhoneError('0917123456'), 'Your mobile number must be exactly 11 digits.');
      expect(riderPhoneError('19171234567'), 'Your mobile number must start with 09.');
      // A wrong start is rejected from the first wrong digit, before the length.
      expect(riderPhoneError('1'), 'Your mobile number must start with 09.');
      expect(riderPhoneError('08'), 'Your mobile number must start with 09.');
      expect(riderPhoneError('0'), 'Your mobile number must be exactly 11 digits.');
      expect(riderPhoneError('0917'), 'Your mobile number must be exactly 11 digits.');
      expect(riderPhoneError(''), 'Enter your mobile number.');
    });

    test('rider license number uses the LTO format A00-00-000000', () {
      expect(riderLicenseNumberError('N01-23-456789'), isNull);
      expect(riderLicenseNumberError('n01-23-456789'), isNull, reason: 'the server uppercases it');
      for (final bad in ['001-23-456789', 'N01-234-56789', 'N01-23-45678', 'N0123456789']) {
        expect(riderLicenseNumberError(bad), contains('A00-00-000000'), reason: bad);
      }
    });

    test('rider plate number is 5 to 7 letters and numbers, mixed', () {
      expect(riderPlateNumberError('ABC123'), isNull);
      expect(riderPlateNumberError('123AB'), isNull);
      expect(riderPlateNumberError('NBC1234'), isNull);
      expect(riderPlateNumberError('AB12'), contains('5 to 7'));
      expect(riderPlateNumberError('NBC12345'), contains('5 to 7'));
      expect(riderPlateNumberError('ABCDEF'), contains('5 to 7'));
      expect(riderPlateNumberError('123456'), contains('5 to 7'));
    });

    test('rider documents must be JPG, PNG, or PDF up to 5.0 MB', () {
      final dir = Directory.systemTemp.createTempSync('rider_docs');
      addTearDown(() => dir.deleteSync(recursive: true));
      File make(String name, int bytes) => File('${dir.path}/$name')..writeAsBytesSync(List.filled(bytes, 0));

      expect(riderDocumentError(null, label: 'vehicle OR/CR'), 'Upload a photo or PDF of your vehicle OR/CR.');
      expect(riderDocumentError(make('a.jpg', 10), label: 'x'), isNull);
      expect(riderDocumentError(make('a.PNG', 10), label: 'x'), isNull);
      expect(riderDocumentError(make('a.pdf', riderDocumentMaxBytes), label: 'x'), isNull, reason: 'exactly 5.0 MB is allowed');
      expect(riderDocumentError(make('a.gif', 10), label: 'x'), 'Only JPG, PNG, or PDF files can be uploaded.');
      expect(riderDocumentError(make('a.heic', 10), label: 'x'), 'Only JPG, PNG, or PDF files can be uploaded.');
      expect(riderDocumentError(make('big.pdf', riderDocumentMaxBytes + 1), label: 'OR/CR'), 'The OR/CR file must be 5.0 MB or smaller.');
    });

    test('license and plate inputs format as the rider types', () {
      String type(List<TextInputFormatter> formatters, String text) => formatters
          .fold(TextEditingValue(text: text), (value, formatter) => formatter.formatEditUpdate(TextEditingValue.empty, value))
          .text;
      expect(type(riderLicenseInputFormatters, 'n0123456789'), 'N01-23-456789');
      expect(type(riderLicenseInputFormatters, 'n01'), 'N01');
      expect(type(riderLicenseInputFormatters, 'n0123'), 'N01-23');
      expect(type(riderLicenseInputFormatters, 'N01-23-4567890000'), 'N01-23-456789', reason: 'stops at 11 characters');
      expect(type(riderPlateInputFormatters, 'nbc-12345'), 'NBC1234', reason: 'stops at 7 characters');
      expect(type(riderEmailInputFormatters, 'ju an@example.com'), 'juan@example.com');
      expect(type(riderPhoneInputFormatters, '0917-123-4567'), '09171234567');
      expect(type(riderPhoneInputFormatters, '091712345678'), '09171234567', reason: 'stops at 11 digits');
    });

    test('PhoneNumber requires 09 and 11 digits', () {
      expect(phMobileError('09171234567'), isNull);
      expect(phMobileError('19171234567'), isNotNull);
      expect(phMobileError('0917123456'), isNotNull);
    });
  });

  group('formatting and models', () {
    test('peso matches the web price format', () {
      expect(peso(1299), '₱1,299.00');
      expect(peso(1234567.5), '₱1,234,567.50');
      expect(peso(599, cents: false), '₱599');
    });

    test('product discount is computed from the original price', () {
      final product = Product.fromJson(productJson(1, price: 399, original: 599));
      expect(product.discountPercent, 33);
      expect(Product.fromJson(productJson(2)).discountPercent, isNull);
    });

    test('order payload parses and labels payment', () {
      final order = Order.fromJson({
        'id': 5,
        'order_number': 'ZAY-261008-ABCDEFGH',
        'status': 'placed',
        'status_label': 'Placed',
        'status_message': 'Order placed. Waiting for the seller to confirm.',
        'can_cancel': true,
        'subtotal': 998.0,
        'shipping_fee': 0.0,
        'shipping_discount': 120.0,
        'total': 998.0,
        'payment_method': 'gcash',
        'voucher_code': 'ZAYLOSHIP',
        'placed_at': '2026-10-08T13:00:00+00:00',
        'item_count': 2,
        'items': [
          {'id': 1, 'product_name': 'Watering Can — Green', 'unit_price': 499.0, 'quantity': 2, 'line_total': 998.0},
        ],
      });
      expect(order.canCancel, isTrue);
      expect(order.paymentLabel, 'GCash');
      expect(order.items.single.quantity, 2);
      expect(order.placedAt, isNotNull);
    });

    test('API errors surface the first field message and the reason code', () {
      final error = ApiException.fromResponse(422, {
        'message': 'The given data was invalid.',
        'errors': {
          'quantity': ['Only 3 item(s) are available.'],
        },
      });
      expect(error.message, 'Only 3 item(s) are available.');
      expect(error.fieldErrors['quantity'], isNotNull);

      final unverified = ApiException.fromResponse(403, {'code': 'unverified', 'email': 'a@b.test'});
      expect(unverified.isUnverified, isTrue);
      expect(unverified.email, 'a@b.test');
    });

    test('API errors name the failing endpoint for debugging', () {
      final error = ApiException.fromResponse(422, {'message': 'Incorrect email or password.'},
          endpoint: 'POST /mobile/rider/login');
      expect(error.endpoint, 'POST /mobile/rider/login');
      expect(error.debugDescription, 'POST /mobile/rider/login (422): Incorrect email or password.');
      expect(error.toString(), 'Incorrect email or password.', reason: 'the UI still shows only the message');
    });

    test('idempotency keys are random v4 UUIDs, as the API validates', () {
      final key = BuyerApi.newIdempotencyKey();
      expect(RegExp(r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$').hasMatch(key), isTrue);
      expect(BuyerApi.newIdempotencyKey(), isNot(key));
    });
  });

  test('dark mode buttons use the web storefront colors', () {
    ZayloColors.use(Brightness.dark);
    addTearDown(() => ZayloColors.use(Brightness.light));
    const idle = <WidgetState>{};
    const pressed = {WidgetState.pressed};
    const disabled = {WidgetState.disabled};

    final primary = ZayloButtons.primary;
    expect(primary.backgroundColor!.resolve(idle), const Color(0xFFF2ECE4));
    expect(primary.foregroundColor!.resolve(idle), const Color(0xFF221E1A));
    expect(primary.backgroundColor!.resolve(pressed), const Color(0xFFC9A088));
    expect(primary.backgroundColor!.resolve(disabled)!.a, closeTo(.48, .01));

    final secondary = ZayloButtons.secondary;
    expect(secondary.backgroundColor!.resolve(idle), Colors.transparent);
    expect(secondary.side!.resolve(idle)!.color, const Color(0xFFF2ECE4));
    expect(secondary.backgroundColor!.resolve(pressed), const Color(0xFFF2ECE4));
    expect(secondary.foregroundColor!.resolve(pressed), const Color(0xFF221E1A));
  });

  testWidgets('home hero swipes, wraps around, and rotates on its own', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        // The home tab scrolls, so the hero never gets a height cap.
        body: ListView(children: [
          HomeHeroCarousel(images: const ['http://fake.test/1.jpg', 'http://fake.test/2.jpg', 'http://fake.test/3.jpg'], onShop: () {}, onNew: () {}),
        ]),
      ),
    ));
    bool showing(int image) => tester.getSemantics(find.bySemanticsLabel('Show image $image of 3')).flagsCollection.isSelected == Tristate.isTrue;

    expect(find.byTooltip('Next image'), findsNothing, reason: 'no arrow buttons');
    expect(showing(1), isTrue);

    await tester.fling(find.byType(PageView), const Offset(-300, 0), 1000);
    await tester.pumpAndSettle();
    expect(showing(2), isTrue);

    await tester.fling(find.byType(PageView), const Offset(300, 0), 1000);
    await tester.pumpAndSettle();
    await tester.fling(find.byType(PageView), const Offset(300, 0), 1000);
    await tester.pumpAndSettle();
    expect(showing(3), isTrue, reason: 'swiping back from the first image wraps to the last');

    await tester.pump(HomeHeroCarousel.interval);
    await tester.pumpAndSettle();
    expect(showing(1), isTrue, reason: 'rotates on its own');

    await tester.tap(find.bySemanticsLabel('Show image 2 of 3'));
    await tester.pumpAndSettle();
    expect(showing(2), isTrue);
  });
}
