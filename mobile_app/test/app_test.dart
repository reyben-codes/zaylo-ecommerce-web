import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/buyer/api/buyer_api.dart';
import 'package:zaylo_mobile/buyer/models/cart_models.dart';
import 'package:zaylo_mobile/buyer/models/catalog_models.dart';
import 'package:zaylo_mobile/buyer/models/order_models.dart';
import 'package:zaylo_mobile/buyer/state/app_state.dart';
import 'package:zaylo_mobile/buyer/state/token_store.dart';
import 'package:zaylo_mobile/core/api/api_exception.dart';
import 'package:zaylo_mobile/core/models/auth_models.dart';
import 'package:zaylo_mobile/core/theme/theme_controller.dart';
import 'package:zaylo_mobile/core/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/core/utils/formatters.dart';
import 'package:zaylo_mobile/core/utils/validators.dart';
import 'package:zaylo_mobile/main.dart';
import 'package:zaylo_mobile/rider/api/rider_api.dart';
import 'package:zaylo_mobile/rider/models/rider_activity_models.dart';
import 'package:zaylo_mobile/rider/models/rider_dashboard_models.dart';
import 'package:zaylo_mobile/core/models/location.dart';
import 'package:zaylo_mobile/rider/screens/rider_home_screen.dart';
import 'package:zaylo_mobile/rider/screens/rider_registration_screen.dart';
import 'package:zaylo_mobile/rider/utils/rider_input_formatters.dart';
import 'package:zaylo_mobile/rider/widgets/rider_terms_dialog.dart';
import 'package:zaylo_mobile/shared/chat/delivery_chat.dart';
import 'package:zaylo_mobile/rider/utils/rider_validators.dart';

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
  FakeApi({this.cartIds = const []}) : super(baseUrl: 'http://fake.test/api');

  final List<int> cartIds;

  @override
  Future<StorefrontHome> home() async => StorefrontHome.fromJson({
        'categories': [
          {'id': 'fashion', 'label': 'Fashion', 'image': 'http://fake.test/fashion.jpg'},
        ],
        'hero_images': ['http://fake.test/hero.jpg'],
        'new_arrivals': [productJson(1), productJson(2)],
        if (token != null) 'flash_sales': [productJson(3, name: 'Gloves', price: 399, original: 599)],
        if (token != null) 'suggested': [productJson(4)],
      });

  @override
  Future<UserProfile> me() async =>
      UserProfile.fromJson({'id': 7, 'name': 'Maria Santos', 'email': 'maria@example.test', 'phone': '09171234567'});

  @override
  Future<CartSummary> cart() async => CartSummary.fromJson(cartJson(cartIds));

  @override
  Future<List<Product>> wishlist() async => [];
}

/// Rider API that answers from memory, in the shape MobileRiderDashboardController returns.
class FakeRiderApi extends RiderApi {
  FakeRiderApi() : super(baseUrl: 'http://fake.test/api');

  final accepted = <int>[];

  static Map<String, dynamic> delivery(int id, String status, {bool mine = false}) => {
        'id': id,
        'tracking_number': 'TRK-$id',
        'status': status,
        'status_label': status == 'ready' ? 'Ready for pickup' : 'Accepted',
        'next_status': mine ? 'picked_up' : null,
        'next_action_label': mine ? 'Mark as picked up' : null,
        'order_number': 'ZAY-ORDER-$id',
        'shipping_address': '12 Rizal St, Calamba, Laguna',
        'recipient_name': mine ? 'Maria Santos' : null,
        'recipient_phone': mine ? '09170000000' : null,
        'payment_method': 'cod',
        'cash_to_collect': 549.0,
        'delivery_fee': 49.0,
        'items': [
          {'name': 'Watering Can', 'quantity': 2},
          {'name': 'Gardening Gloves', 'quantity': 1},
        ],
        'pickup': {'store_name': 'Happy Heels', 'phone': '09171110000', 'address': 'Halang, Calamba, Laguna'},
        'chat_open': mine && status != 'delivered',
        'unread_messages': mine && status == 'assigned' ? 1 : 0,
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
          'vehicle_label': 'Tricycle',
          'plate_number': 'U7U778',
          'license_number': 'S33-45-666666',
          'license_expires_on': '2026-10-20',
          'license_days_left': 9,
          'license_expiring_soon': true,
        },
        'stats': {'available': 1, 'active': 1, 'completed': 3, 'completed_today': 1, 'earnings_today': 49, 'earnings_total': 147},
        'active': [delivery(1, 'assigned', mine: true)],
        'available': [delivery(2, 'ready')],
        'recent': [],
      });

  @override
  Future<RiderDelivery> acceptDelivery(int id) async {
    accepted.add(id);
    return RiderDelivery.fromJson(delivery(id, 'assigned', mine: true));
  }

  final historyRequests = <RiderPeriod>[];

  @override
  Future<RiderHistoryPage> history({RiderPeriod period = RiderPeriod.all, int page = 1}) async {
    historyRequests.add(period);
    return RiderHistoryPage.fromJson({
      'summary': {'deliveries': 1, 'earnings': 49},
      'deliveries': [delivery(9, 'delivered', mine: true)..['status_label'] = 'Delivered'],
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
  Future<List<RiderSupportMessage>> supportMessages() async {
    unread = 0;
    return [
      RiderSupportMessage.fromJson({'id': 1, 'client_id': 'a', 'body': 'Hi! How can we help?', 'from_staff': true}),
    ];
  }

  @override
  Future<RiderSupportMessage> sendSupportMessage(String body, {String? clientId}) async {
    sent.add(body);
    return RiderSupportMessage.fromJson({'id': 2, 'client_id': clientId, 'body': body, 'from_staff': false});
  }
}

Future<void> pumpApp(WidgetTester tester,
    {String? token, List<int> cart = const [], double width = 400, ThemeController? theme}) async {
  final tokens = MemoryTokenStore();
  if (token != null) await tokens.write(token);
  final state = AppState(api: FakeApi(cartIds: cart), tokens: tokens);
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

      expect(find.text('Welcome back, Maria'), findsOneWidget);
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

  testWidgets('appearance switches to dark mode, persists, and returns to the system setting', (tester) async {
    addTearDown(() => ZayloColors.use(Brightness.light));
    final store = MemoryThemeModeStore();
    await pumpApp(tester, theme: ThemeController(store: store));
    Color background() => tester.widget<Scaffold>(find.byType(Scaffold).first).backgroundColor ??
        Theme.of(tester.element(find.byType(Scaffold).first)).scaffoldBackgroundColor;
    expect(background(), ZayloPalette.light.cream);

    await tester.tap(find.byTooltip('Menu'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Dark'));
    await tester.pumpAndSettle();

    expect(ZayloColors.isDark, isTrue);
    expect(background(), ZayloPalette.dark.cream);
    expect(await store.read(), ThemeMode.dark);

    // The test platform reports light, so System goes back to the light palette.
    await tester.tap(find.text('System'));
    await tester.pumpAndSettle();
    expect(ZayloColors.isDark, isFalse);
    expect(background(), ZayloPalette.light.cream);
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

  testWidgets('rider registration collects personal details, then license and vehicle documents', (tester) async {
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

    // Step 2: contact details, license, and vehicle documents.
    expect(find.text('Your rider details'), findsOneWidget);
    for (final label in [
      'Email address',
      'Mobile number',
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
    expect(find.text('Select your vehicle type.'), findsOneWidget);
  });

  testWidgets('rider chats with the buyer of an active delivery', (tester) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(400, 4000);
    addTearDown(tester.view.reset);
    final api = FakeRiderApi();
    await tester.pumpWidget(MaterialApp(home: RiderHomeScreen(api: api)));
    await tester.pumpAndSettle();

    // Only the rider's own delivery offers the chat, with its unread badge.
    expect(find.text('Chat with Maria'), findsOneWidget);
    expect(find.text('Chat with buyer'), findsNothing);
    await tester.tap(find.text('Chat with Maria'));
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

  testWidgets('rider dashboard shows stats, deliveries, and accepts a delivery', (tester) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(320, 4000);
    addTearDown(tester.view.reset);
    final api = FakeRiderApi();
    await tester.pumpWidget(MaterialApp(home: RiderHomeScreen(api: api)));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull, reason: 'fits a 320px phone');
    expect(find.text('Hi, Juan'), findsOneWidget);
    expect(find.text('Tricycle · U7U778'), findsOneWidget);
    // Sign out moved to the Account tab: no button on Home or in the top bar.
    expect(find.text('SIGN OUT'), findsNothing);
    expect(find.byTooltip('Sign out'), findsNothing);
    expect(find.textContaining('expires in 9 days'), findsOneWidget);
    expect(find.text('Earned today'), findsOneWidget);
    expect(find.text('Your active deliveries'), findsOneWidget);
    expect(find.text('MARK AS PICKED UP'), findsOneWidget);
    // The recipient is shown only on the delivery the rider accepted.
    expect(find.text('Maria Santos'), findsOneWidget);
    expect(find.text('Recipient shown after you accept'), findsOneWidget);
    // Each card says where to collect the parcel and what is in it.
    expect(find.text('Happy Heels'), findsNWidgets(2));
    expect(find.text('Items to pick up (3)'), findsNWidgets(2));
    expect(find.text('Watering Can'), findsNWidgets(2));

    await tester.tap(find.text('ACCEPT DELIVERY'));
    await tester.pumpAndSettle();
    expect(api.accepted, [2]);
    expect(find.text('Delivery accepted. Head to the pickup point.'), findsOneWidget);
  });

  group('rider app tabs', () {
    Future<FakeRiderApi> pumpRider(WidgetTester tester) async {
      tester.view.devicePixelRatio = 1;
      tester.view.physicalSize = const Size(400, 2400);
      addTearDown(tester.view.reset);
      final api = FakeRiderApi();
      await tester.pumpWidget(MaterialApp(home: RiderHomeScreen(api: api)));
      await tester.pumpAndSettle();
      return api;
    }

    testWidgets('Available and Active tiles open their full lists', (tester) async {
      await pumpRider(tester);

      await tester.tap(find.text('Available'));
      await tester.pumpAndSettle();
      expect(find.text('Available deliveries'), findsOneWidget);
      expect(find.text('ACCEPT DELIVERY'), findsOneWidget);
      await tester.pageBack();
      await tester.pumpAndSettle();

      await tester.tap(find.text('Active'));
      await tester.pumpAndSettle();
      expect(find.text('Active deliveries'), findsOneWidget);
      expect(find.text('MARK AS PICKED UP'), findsOneWidget);
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
      expect(find.text('SIGN OUT'), findsOneWidget);

      await tester.tap(find.text('SIGN OUT'));
      await tester.pumpAndSettle();
      expect(find.text('Sign out?'), findsOneWidget, reason: 'asks before signing out');
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

    test('rider plate number is 6 letters and numbers, mixed', () {
      expect(riderPlateNumberError('ABC123'), isNull);
      expect(riderPlateNumberError('1AB234'), isNull);
      expect(riderPlateNumberError('ABC12'), contains('exactly 6'));
      expect(riderPlateNumberError('ABCDEF'), contains('at least one of each'));
      expect(riderPlateNumberError('123456'), contains('at least one of each'));
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
      expect(type(riderPlateInputFormatters, 'abc-1234'), 'ABC123');
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
}
