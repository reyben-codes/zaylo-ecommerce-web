import 'package:zaylo_mobile/api/api_client.dart';
import 'package:zaylo_mobile/components/address/ph_address_section.dart';
import 'package:zaylo_mobile/components/chat/delivery_chat.dart';
import 'package:zaylo_mobile/models/auth_models.dart';
import 'package:zaylo_mobile/models/rider/rider_activity_models.dart';
import 'package:zaylo_mobile/models/rider/rider_dashboard_models.dart';
import 'package:zaylo_mobile/models/rider/rider_models.dart';

/// Rider endpoints. A rider token lives on its own [RiderApi], separate from
/// the buyer session in AppState, so signing in as a rider never signs the
/// buyer out. Logout and forgot-password come from [ApiClient].
class RiderApi extends ApiClient {
  RiderApi({super.baseUrl});

  /// Support chat and complaints live under the rider prefix.
  @override
  String get accountPrefix => '/mobile/rider';

  /// Rider accounts only; buyers are turned away by the server.
  Future<AuthResult> login(String email, String password) async =>
      AuthResult.fromJson(await send('POST', '/mobile/rider/login', body: {
        'email': email,
        'password': password,
        'device_name': deviceName,
      }));

  /// Logistics partners accepting riders, and the vehicle types, for sign-up.
  Future<RiderSignUpOptions> signUpOptions() async =>
      RiderSignUpOptions.fromJson(await send('GET', '/mobile/rider/logistics-partners'));

  /// Validates every field except the uploads, so problems such as an email
  /// or plate number already registered show before the documents upload.
  Future<void> checkRegistration(RiderRegistration registration) =>
      send('POST', '/mobile/rider/register/check', body: registration.fields);

  // ------------------------------------------- Signed-in rider (needs token)

  Future<RiderDashboard> dashboard() async => RiderDashboard.fromJson(await send('GET', '/mobile/rider/dashboard'));

  /// Reports a failed delivery attempt; starting and finishing a delivery go through [scanParcel].
  Future<RiderDelivery> updateDelivery(int id, String status) async =>
      RiderDelivery.fromJson((await send('PATCH', '/mobile/rider/deliveries/$id', body: {'status': status}))['delivery']
          as Map<String, dynamic>);

  /// Sends the code read from the waybill's barcode or QR code. The parcel goes out for
  /// delivery, or is delivered if it already was; the buyer (and on delivery,
  /// the seller) is notified by the server.
  Future<RiderScanResult> scanParcel(String code) async =>
      RiderScanResult.fromJson(await send('POST', '/mobile/rider/deliveries/scan', body: {'code': code.trim()}));

  /// Completed deliveries for [period], newest first; [page] starts at 1.
  Future<RiderHistoryPage> history({RiderPeriod period = RiderPeriod.all, int page = 1}) async =>
      RiderHistoryPage.fromJson(
          await send('GET', '/mobile/rider/history', query: {'period': period.value, 'page': '$page'}));

  /// Chat with the buyer of delivery [id]. Opening it marks the buyer's messages as read.
  Future<DeliveryChat> deliveryChat(int id) async =>
      DeliveryChat.fromJson((await send('GET', '/mobile/rider/deliveries/$id/chat'))['chat'] as Map<String, dynamic>);

  /// [clientId] makes a retried send safe: the server stores it only once.
  Future<DeliveryChatMessage> sendDeliveryMessage(int id, String body, {required String clientId}) async =>
      DeliveryChatMessage.fromJson((await send('POST', '/mobile/rider/deliveries/$id/chat', body: {
        'body': body,
        'client_id': clientId,
      }))['message'] as Map<String, dynamic>);

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

  /// Creates a pending rider account and emails a verification code. Returns
  /// the server's `{email, verification_sent, message}`.
  Future<Map<String, dynamic>> register(RiderRegistration registration) =>
      sendMultipart('/mobile/rider/register', fields: registration.fields, files: registration.files);
}
