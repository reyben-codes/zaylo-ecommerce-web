import '../../core/api/api_client.dart';
import '../../core/models/auth_models.dart';
import '../../core/utils/ids.dart';
import '../../shared/address/ph_address_section.dart';
import '../models/rider_activity_models.dart';
import '../models/rider_dashboard_models.dart';
import '../models/rider_models.dart';

/// Rider endpoints. A rider token lives on its own [RiderApi], separate from
/// the buyer session in AppState, so signing in as a rider never signs the
/// buyer out. Logout and forgot-password come from [ApiClient].
class RiderApi extends ApiClient {
  RiderApi({super.baseUrl});

  /// Rider accounts only; buyers are turned away by the server.
  Future<AuthResult> login(String email, String password) async =>
      AuthResult.fromJson(await send('POST', '/mobile/rider/login', body: {
        'email': email,
        'password': password,
        'device_name': deviceName,
      }));

  /// Validates every field except the uploads, so problems such as an email
  /// or plate number already registered show before the documents upload.
  Future<void> checkRegistration(RiderRegistration registration) =>
      send('POST', '/mobile/rider/register/check', body: registration.fields);

  // ------------------------------------------- Signed-in rider (needs token)

  Future<RiderDashboard> dashboard() async => RiderDashboard.fromJson(await send('GET', '/mobile/rider/dashboard'));

  /// Accepts an available delivery; fails if another rider took it first.
  Future<RiderDelivery> acceptDelivery(int id) async => RiderDelivery.fromJson(
      (await send('POST', '/mobile/rider/deliveries/$id/claim'))['delivery'] as Map<String, dynamic>);

  /// Moves a delivery to its next status (picked_up, in_transit, delivered).
  Future<RiderDelivery> updateDelivery(int id, String status) async => RiderDelivery.fromJson(
      (await send('PATCH', '/mobile/rider/deliveries/$id', body: {'status': status}))['delivery']
          as Map<String, dynamic>);

  /// Completed deliveries for [period], newest first; [page] starts at 1.
  Future<RiderHistoryPage> history({RiderPeriod period = RiderPeriod.all, int page = 1}) async =>
      RiderHistoryPage.fromJson(
          await send('GET', '/mobile/rider/history', query: {'period': period.value, 'page': '$page'}));

  Future<RiderEarnings> earnings() async => RiderEarnings.fromJson(await send('GET', '/mobile/rider/earnings'));

  // ------------------------------------------------------- Account settings

  /// Returns the saved number.
  Future<String> updatePhone(String phone) async =>
      (await send('PATCH', '/mobile/rider/account/phone', body: {'phone': phone}))['phone'] as String;

  Future<void> updateGender(String gender) => send('PATCH', '/mobile/rider/account/gender', body: {'gender': gender});

  /// Saves the rider's home address; returns it formatted as one line.
  Future<String> updateAddress(PhAddressValue address) async =>
      ((await send('PUT', '/mobile/rider/account/address', body: address.toFields()))['address']
          as Map<String, dynamic>)['formatted'] as String;

  Future<String> updatePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) async =>
      (await send('PUT', '/mobile/rider/account/password', body: {
        'current_password': currentPassword,
        'password': password,
        'password_confirmation': passwordConfirmation,
      }))['message'] as String;

  // ---------------------------------------------------- ZAYLO support chat

  /// The whole thread, oldest first. Opening it marks support replies as read.
  Future<List<RiderSupportMessage>> supportMessages() async =>
      ((await send('GET', '/mobile/rider/support'))['messages'] as List<dynamic>)
          .map((item) => RiderSupportMessage.fromJson(item as Map<String, dynamic>))
          .toList();

  /// [clientId] makes a retried send safe: the server stores it only once.
  Future<RiderSupportMessage> sendSupportMessage(String body, {String? clientId}) async =>
      RiderSupportMessage.fromJson((await send('POST', '/mobile/rider/support/messages', body: {
        'body': body,
        'client_id': clientId ?? newUuidV4(),
      }))['message'] as Map<String, dynamic>);

  /// Support replies the rider has not opened yet.
  Future<int> supportUnreadCount() async => (await send('GET', '/mobile/rider/support/unread'))['unread'] as int? ?? 0;

  /// Creates a pending rider account and emails a verification code. Returns
  /// the server's `{email, verification_sent, message}`.
  Future<Map<String, dynamic>> register(RiderRegistration registration) =>
      sendMultipart('/mobile/rider/register', fields: registration.fields, files: registration.files);
}
