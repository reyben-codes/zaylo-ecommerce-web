import 'dart:io';

import 'package:zaylo_mobile/api/api_client.dart';
import 'package:zaylo_mobile/components/chat/delivery_chat.dart';
import 'package:zaylo_mobile/models/auth_models.dart';
import 'package:zaylo_mobile/models/buyer/account_models.dart';
import 'package:zaylo_mobile/models/buyer/address_models.dart';
import 'package:zaylo_mobile/models/buyer/cart_models.dart';
import 'package:zaylo_mobile/models/buyer/catalog_models.dart';
import 'package:zaylo_mobile/models/buyer/order_models.dart';
import 'package:zaylo_mobile/models/buyer/seller_chat_models.dart';
import 'package:zaylo_mobile/models/support_models.dart';
import 'package:zaylo_mobile/utils/ids.dart';

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
    double? minPrice,
    double? maxPrice,
    bool onSale = false,
    int page = 1,
  }) async {
    final data = await send('GET', '/mobile/products', query: {
      if (category != null) 'category': category,
      if (gender != null) 'gender': gender,
      if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
      if (sort != null) 'sort': sort,
      if (minPrice != null) 'min_price': minPrice.toStringAsFixed(2),
      if (maxPrice != null) 'max_price': maxPrice.toStringAsFixed(2),
      if (onSale) 'on_sale': '1',
      'page': '$page',
    });
    return ProductPage.fromJson(data['products'] as Map<String, dynamic>);
  }

  Future<Product> product(String slug) async {
    final data = await send('GET', '/mobile/products/${Uri.encodeComponent(slug)}');
    return Product.fromJson(data['product'] as Map<String, dynamic>);
  }

  Future<ProductReviewPage> productReviews(String slug, {int page = 1}) async => ProductReviewPage.fromJson(
      await send('GET', '/mobile/products/${Uri.encodeComponent(slug)}/reviews', query: {'page': '$page'}));

  // ------------------------------------------------------------------- Auth

  Future<AuthResult> login(String email, String password) async =>
      AuthResult.fromJson(await send('POST', '/mobile/login', body: {
        'email': email,
        'password': password,
        'device_name': deviceName,
      }));

  /// Signs in (or signs up) with a Google ID token from the phone's Google sign-in.
  Future<AuthResult> googleSignIn(String idToken) async =>
      AuthResult.fromJson(await send('POST', '/mobile/google', body: {
        'id_token': idToken,
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

  /// Rates a delivered item, with up to three photos.
  Future<String> reviewOrderItem(int itemId, {required int rating, String? comment, List<File> photos = const []}) async =>
      (await sendMultipart('/mobile/order-items/$itemId/review', fields: {
        'rating': '$rating',
        if (comment != null && comment.trim().isNotEmpty) 'comment': comment.trim(),
      }, files: {
        for (final (index, photo) in photos.indexed) 'photos[$index]': photo,
      }))['message'] as String;

  Future<List<ComplaintCategory>> complaintCategories() async =>
      ((await send('GET', '/mobile/complaints/categories'))['categories'] as List<dynamic>)
          .map((item) => ComplaintCategory.fromJson(item as Map<String, dynamic>))
          .toList();

  /// Files a complaint about order [orderId], with up to four photos or PDFs.
  Future<Complaint> fileComplaint(int orderId,
          {required String category, required String details, List<File> evidence = const []}) async =>
      Complaint.fromJson((await sendMultipart('/mobile/orders/$orderId/complaint', fields: {
        'category': category,
        'details': details.trim(),
      }, files: {
        for (final (index, file) in evidence.indexed) 'evidence[$index]': file,
      }))['complaint'] as Map<String, dynamic>);

  // ------------------------------------------------------ Chat with sellers

  Future<List<SellerConversation>> sellerConversations() async =>
      ((await send('GET', '/mobile/messages'))['conversations'] as List<dynamic>)
          .map((item) => SellerConversation.fromJson(item as Map<String, dynamic>))
          .toList();

  Future<int> sellerUnreadCount() async => (await send('GET', '/mobile/messages/unread'))['unread'] as int? ?? 0;

  /// Opens (or creates) the conversation with the seller of a product.
  Future<SellerConversation> startSellerChat(String slug) async => SellerConversation.fromJson(
      (await send('POST', '/mobile/products/${Uri.encodeComponent(slug)}/chat'))['conversation'] as Map<String, dynamic>);

  /// The latest messages, or only those after message id [after] when polling.
  Future<SellerChatBatch> sellerMessages(int conversationId, {int? after, int? before}) async =>
      SellerChatBatch.fromJson(await send('GET', '/mobile/messages/$conversationId', query: {
        if (after != null) 'after': '$after',
        if (before != null) 'before': '$before',
      }));

  /// [clientId] makes a retried send safe: the server stores it only once.
  Future<SellerChatMessage> sendSellerMessage(int conversationId, String body, {required String clientId}) async =>
      SellerChatMessage.fromJson(
        (await send('POST', '/mobile/messages/$conversationId', body: {'body': body, 'client_id': clientId}))['message']
            as Map<String, dynamic>,
        clientId: clientId,
      );

  /// Marks the seller's messages up to [through] as read.
  Future<void> markSellerChatRead(int conversationId, int through) =>
      send('POST', '/mobile/messages/$conversationId/read', body: {'through': through});

  /// Shows (or clears) "typing…" on the seller's side for a few seconds.
  Future<void> sendTyping(int conversationId, bool typing) =>
      send('POST', '/mobile/messages/$conversationId/typing', body: {'typing': typing});

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

  Future<IdentityStatus> identity() async =>
      IdentityStatus.fromJson((await send('GET', '/mobile/account/identity'))['identity'] as Map<String, dynamic>);

  /// Uploads a photo or PDF of a government ID for an administrator to review.
  Future<IdentityStatus> submitIdentity({required String idType, required File document}) async =>
      IdentityStatus.fromJson((await sendMultipart('/mobile/account/identity',
          fields: {'id_type': idType}, files: {'document': document}))['identity'] as Map<String, dynamic>);

  // ---------------------------------------------------------------- Helpers

  static String newIdempotencyKey() => newUuidV4();

  List<Address> _addresses(Map<String, dynamic> data) =>
      (data['addresses'] as List<dynamic>).map((item) => Address.fromJson(item as Map<String, dynamic>)).toList();
}
