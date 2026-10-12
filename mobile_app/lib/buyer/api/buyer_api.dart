import '../../core/api/api_client.dart';
import '../../core/models/auth_models.dart';
import '../../core/utils/ids.dart';
import '../models/account_models.dart';
import '../models/address_models.dart';
import '../models/cart_models.dart';
import '../models/catalog_models.dart';
import '../models/order_models.dart';
import '../../shared/chat/delivery_chat.dart';

/// Buyer endpoints: catalog, sign-in, bag, checkout, orders and account.
/// The token is set by [AppState] after sign-in and cleared on sign-out.
class BuyerApi extends ApiClient {
  BuyerApi({super.baseUrl});

  // ---------------------------------------------------------------- Catalog

  Future<StorefrontHome> home() async => StorefrontHome.fromJson(await send('GET', '/mobile/home'));

  Future<ProductPage> products({
    String? category,
    String? search,
    String? sort,
    String? gender,
    int page = 1,
  }) async {
    final data = await send('GET', '/mobile/products', query: {
      if (category != null) 'category': category,
      if (gender != null) 'gender': gender,
      if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
      if (sort != null) 'sort': sort,
      'page': '$page',
    });
    return ProductPage.fromJson(data['products'] as Map<String, dynamic>);
  }

  Future<Product> product(String slug) async {
    final data = await send('GET', '/mobile/products/${Uri.encodeComponent(slug)}');
    return Product.fromJson(data['product'] as Map<String, dynamic>);
  }

  // ------------------------------------------------------------------- Auth

  Future<AuthResult> login(String email, String password) async =>
      AuthResult.fromJson(await send('POST', '/mobile/login', body: {
        'email': email,
        'password': password,
        'device_name': deviceName,
      }));

  Future<UserProfile> me() async =>
      UserProfile.fromJson((await send('GET', '/mobile/me'))['user'] as Map<String, dynamic>);

  Future<Map<String, dynamic>> register({
    required String firstName,
    required String lastName,
    required String phone,
    required String dateOfBirth,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) =>
      send('POST', '/mobile/register', body: {
        'first_name': firstName,
        'last_name': lastName,
        'phone': phone,
        'date_of_birth': dateOfBirth,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
      });

  // ---------------------------------------------------------- Saved & bag

  Future<List<Product>> wishlist() async => ((await send('GET', '/mobile/wishlist'))['products'] as List<dynamic>)
      .map((item) => Product.fromJson(item as Map<String, dynamic>))
      .toList();

  Future<bool> toggleWishlist(String slug) async =>
      (await send('POST', '/mobile/wishlist/${Uri.encodeComponent(slug)}'))['saved'] as bool;

  Future<CartSummary> cart() async => CartSummary.fromJson(await send('GET', '/mobile/cart'));

  Future<CartSummary> addToCart(String slug, {required int quantity, int? variantId}) async =>
      CartSummary.fromJson(await send('POST', '/mobile/cart/${Uri.encodeComponent(slug)}', body: {
        'quantity': quantity,
        if (variantId != null) 'product_variant_id': variantId,
      }));

  Future<CartSummary> updateCartItem(int id, int quantity) async =>
      CartSummary.fromJson(await send('PATCH', '/mobile/cart/items/$id', body: {'quantity': quantity}));

  Future<CartSummary> removeCartItem(int id) async =>
      CartSummary.fromJson(await send('DELETE', '/mobile/cart/items/$id'));

  Future<CartSummary> removeCartItems(List<int> ids) async =>
      CartSummary.fromJson(await send('POST', '/mobile/cart/remove', body: {'cart_item_ids': ids}));

  // --------------------------------------------------------------- Checkout

  Future<CheckoutQuote> quote(List<int> cartItemIds, {String? voucherCode}) async =>
      CheckoutQuote.fromJson(await send('POST', '/mobile/checkout/quote', body: {
        'cart_item_ids': cartItemIds,
        if (voucherCode != null && voucherCode.trim().isNotEmpty) 'voucher_code': voucherCode.trim(),
      }));

  Future<List<Order>> placeOrder({
    required List<int> cartItemIds,
    required int addressId,
    required String paymentMethod,
    required String idempotencyKey,
    String? voucherCode,
    String? notes,
  }) async {
    final data = await send('POST', '/mobile/checkout', body: {
      'cart_item_ids': cartItemIds,
      'address_id': addressId,
      'payment_method': paymentMethod,
      'idempotency_key': idempotencyKey,
      if (voucherCode != null && voucherCode.isNotEmpty) 'voucher_code': voucherCode,
      if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
    });
    return (data['orders'] as List<dynamic>).map((item) => Order.fromJson(item as Map<String, dynamic>)).toList();
  }

  // ----------------------------------------------------------------- Orders

  Future<OrderPage> orders({String status = 'all', int page = 1}) async =>
      OrderPage.fromJson(await send('GET', '/mobile/orders', query: {'status': status, 'page': '$page'}));

  Future<Order> order(int id) async =>
      Order.fromJson((await send('GET', '/mobile/orders/$id'))['order'] as Map<String, dynamic>);

  Future<Order> cancelOrder(int id) async =>
      Order.fromJson((await send('POST', '/mobile/orders/$id/cancel'))['order'] as Map<String, dynamic>);

  /// Chat with the rider delivering order [id]. Opening it marks the rider's messages as read.
  Future<DeliveryChat> orderChat(int id) async =>
      DeliveryChat.fromJson((await send('GET', '/mobile/orders/$id/chat'))['chat'] as Map<String, dynamic>);

  /// [clientId] makes a retried send safe: the server stores it only once.
  Future<DeliveryChatMessage> sendOrderMessage(int id, String body, {required String clientId}) async =>
      DeliveryChatMessage.fromJson((await send('POST', '/mobile/orders/$id/chat', body: {
        'body': body,
        'client_id': clientId,
      }))['message'] as Map<String, dynamic>);

  // ---------------------------------------------------------------- Account

  Future<ProfileUpdate> updateProfile({
    required String name,
    required String email,
    required String phone,
    required String dateOfBirth,
  }) async {
    final data = await send('PATCH', '/mobile/account/profile', body: {
      'name': name,
      'email': email,
      'phone': phone,
      'date_of_birth': dateOfBirth,
    });
    return ProfileUpdate(
      message: data['message'] as String,
      emailVerificationRequired: data['email_verification_required'] as bool,
      user: UserProfile.fromJson(data['user'] as Map<String, dynamic>),
    );
  }

  Future<String> updatePassword({
    String? currentPassword,
    required String password,
    required String passwordConfirmation,
  }) async =>
      (await send('PUT', '/mobile/account/password', body: {
        if (currentPassword != null) 'current_password': currentPassword,
        'password': password,
        'password_confirmation': passwordConfirmation,
      }))['message'] as String;

  Future<List<Address>> addresses() async => _addresses(await send('GET', '/mobile/addresses'));

  Future<Address> saveAddress(AddressInput input, {int? id}) async {
    final data = id == null
        ? await send('POST', '/mobile/addresses', body: input.toJson())
        : await send('PUT', '/mobile/addresses/$id', body: input.toJson());
    return Address.fromJson(data['address'] as Map<String, dynamic>);
  }

  Future<List<Address>> makeDefaultAddress(int id) async =>
      _addresses(await send('PATCH', '/mobile/addresses/$id/default'));

  Future<List<Address>> deleteAddress(int id) async => _addresses(await send('DELETE', '/mobile/addresses/$id'));

  Future<NotificationFeed> notifications() async =>
      NotificationFeed.fromJson(await send('GET', '/mobile/notifications'));

  Future<NotificationFeed> markNotificationsRead({String? key}) async =>
      NotificationFeed.fromJson(await send('POST', '/mobile/notifications/read', body: {
        if (key != null) 'key': key,
      }));

  // ---------------------------------------------------------------- Helpers

  static String newIdempotencyKey() => newUuidV4();

  List<Address> _addresses(Map<String, dynamic> data) =>
      (data['addresses'] as List<dynamic>).map((item) => Address.fromJson(item as Map<String, dynamic>)).toList();
}
