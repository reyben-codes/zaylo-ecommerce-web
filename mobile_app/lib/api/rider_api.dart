import 'dart:io';

import 'package:zaylo_mobile/api/api_client.dart';
import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/auth/token_store.dart';
import 'package:zaylo_mobile/components/address/ph_address_section.dart';
import 'package:zaylo_mobile/components/chat/delivery_chat.dart';
import 'package:zaylo_mobile/models/auth_models.dart';
import 'package:zaylo_mobile/models/chat_media.dart';
import 'package:zaylo_mobile/models/rider/rider_activity_models.dart';
import 'package:zaylo_mobile/models/rider/rider_attendance_history.dart';
import 'package:zaylo_mobile/models/rider/rider_dashboard_models.dart';
import 'package:zaylo_mobile/models/rider/rider_models.dart';
import 'package:zaylo_mobile/models/rider/rider_wallet_models.dart';

/// Rider endpoints. A rider token lives on its own [RiderApi], separate from
/// the buyer session in AppState, so signing in as a rider never signs the
/// buyer out. Logout and forgot-password come from [ApiClient]. The token is
/// kept on the phone under its own key, so a rider stays signed in after the
/// app closes.
class RiderApi extends ApiClient {
  RiderApi({super.baseUrl, TokenStore? tokens}) : _tokens = tokens ?? SecureTokenStore(key: 'zaylo_rider_token');

  final TokenStore _tokens;

  /// Uses [token] from a sign-in and saves it for the next launch.
  Future<void> keepSignedIn(String? token) async {
    this.token = token;
    if (token != null) await _tokens.write(token);
  }

  /// Drops the token here and on the phone.
  Future<void> forget() async {
    token = null;
    await _tokens.clear();
  }

  /// Picks up a saved rider session on launch. A rejected token is forgotten;
  /// network failures keep it, so a rider who opens the app offline is not
  /// signed out.
  Future<bool> resume() async {
    final saved = await _tokens.read();
    if (saved == null) return false;
    token = saved;
    try {
      await me();
    } on ApiException catch (error) {
      if (error.statusCode == 401) {
        await forget();
        return false;
      }
    }
    return true;
  }

  /// Support chat and complaints live under the rider prefix.
  @override
  String get accountPrefix => '/mobile/rider';

  @override
  String get faqRole => 'rider';

  /// Rider accounts only; buyers and couriers are turned away by the server.
  Future<AuthResult> login(String email, String password) async =>
      AuthResult.fromJson(await send('POST', '/mobile/rider/login', body: {
        'email': email,
        'password': password,
        'device_name': deviceName,
      }));

  /// Signs in an existing rider with a Google ID token. Never creates an account:
  /// riders apply with the sign-up form first.
  Future<AuthResult> googleSignIn(String idToken) async =>
      AuthResult.fromJson(await send('POST', '/mobile/rider/google', body: {
        'id_token': idToken,
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

  Future<RiderAttendanceHistory> attendanceHistory({int page = 1, DateTime? from, DateTime? to}) async =>
      RiderAttendanceHistory.fromJson(await send('GET', '/mobile/rider/attendance', query: {
        'page': '$page',
        if (from != null) 'from': _attendanceDate(from),
        if (to != null) 'to': _attendanceDate(to),
      }));

  static String _attendanceDate(DateTime date) =>
      '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

  /// Reports a failed delivery attempt with its [reason] (and the rider's own words in [note]); the buyer and
  /// seller are told. Starting and finishing a delivery go through [scanParcel].
  Future<RiderDelivery> updateDelivery(int id, String status, {String? reason, String? note}) async =>
      RiderDelivery.fromJson((await send('PATCH', '/mobile/rider/deliveries/$id', body: {
        'status': status,
        if (reason != null) 'reason': reason,
        if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
      }))['delivery'] as Map<String, dynamic>);

  /// Confirms cash was collected for this rider's delivered COD parcel.
  Future<RiderDelivery> collectCod(int id) async =>
      RiderDelivery.fromJson((await send('POST', '/mobile/rider/deliveries/$id/collect-cod'))['delivery'] as Map<String, dynamic>);

  /// Takes a new assignment from the logistics partner.
  Future<RiderDelivery> acceptDelivery(int id) async =>
      RiderDelivery.fromJson((await send('POST', '/mobile/rider/deliveries/$id/accept'))['delivery'] as Map<String, dynamic>);

  /// Turns a new assignment down; the parcel goes back to the hub for another rider. Returns the server's message.
  Future<String> declineDelivery(int id, {required String reason, String? note}) async =>
      (await send('POST', '/mobile/rider/deliveries/$id/decline', body: {
        'reason': reason,
        if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
      }))['message'] as String;

  /// One of the rider's parcels with everything they did with it.
  Future<RiderDelivery> deliveryDetails(int id) async =>
      RiderDelivery.fromJson((await send('GET', '/mobile/rider/deliveries/$id'))['delivery'] as Map<String, dynamic>);

  /// Totals for [period]: assignments, deliveries, failed attempts and their reasons, cash and fees.
  Future<RiderReport> reports({RiderPeriod period = RiderPeriod.week}) async =>
      RiderReport.fromJson(await send('GET', '/mobile/rider/reports', query: {'period': period.value}));

  /// Sends the code read from the waybill's barcode or QR code. The parcel goes out for
  /// delivery, or is delivered if it already was; the buyer (and on delivery,
  /// the seller) is notified by the server. Delivering needs a [photo] of the
  /// parcel with the recipient or at the door: without one the server answers
  /// with a "photo" field error.
  Future<RiderScanResult> scanParcel(String code, {File? photo}) async => RiderScanResult.fromJson(photo == null
      ? await send('POST', '/mobile/rider/deliveries/scan', body: {'code': code.trim()})
      : await sendMultipart('/mobile/rider/deliveries/scan', fields: {'code': code.trim()}, files: {'photo': photo}));

  /// Sends the code on a buyer's return slip when collecting the item, with a [photo] of the item as proof of
  /// what was returned (without one the server answers with a "photo" field error); returns the server's message.
  Future<String> scanReturn(String code, {File? photo}) async => (photo == null
      ? await send('POST', '/mobile/rider/returns/scan', body: {'code': code.trim()})
      : await sendMultipart('/mobile/rider/returns/scan', fields: {'code': code.trim()}, files: {'photo': photo}))['message']
          as String;

  Future<String> scanSellerPickup(String code) async =>
      (await send('POST', '/mobile/rider/seller-pickups/scan', body: {'code': code.trim()}))['message'] as String;

  /// Pickup Rider: Assigned pickup queue
  Future<List<RiderSellerPickup>> pickupQueue() async {
    final res = await send('GET', '/mobile/rider/pickup/queue');
    final list = res['queue'] as List<dynamic>? ?? [];
    return list.map((item) => RiderSellerPickup.fromJson(item as Map<String, dynamic>)).toList();
  }

  /// Pickup Rider: Scan seller parcel at seller door
  Future<Map<String, dynamic>> scanPickup(String code) async =>
      await send('POST', '/mobile/rider/pickup/scan', body: {'code': code.trim()});

  /// Pickup Rider: Active transit manifest
  Future<List<RiderSellerPickup>> pickupManifest() async {
    final res = await send('GET', '/mobile/rider/pickup/manifest');
    final list = res['manifest'] as List<dynamic>? ?? [];
    return list.map((item) => RiderSellerPickup.fromJson(item as Map<String, dynamic>)).toList();
  }

  /// Pickup Rider: Hub dropoff list
  Future<List<RiderSellerPickup>> hubDropoff() async {
    final res = await send('GET', '/mobile/rider/pickup/dropoff');
    final list = res['dropoffs'] as List<dynamic>? ?? [];
    return list.map((item) => RiderSellerPickup.fromJson(item as Map<String, dynamic>)).toList();
  }

  /// Delivery Rider: Active delivery manifest
  Future<List<Map<String, dynamic>>> deliveryManifest() async {
    final res = await send('GET', '/mobile/rider/delivery/manifest');
    final list = res['manifest'] as List<dynamic>? ?? [];
    return list.cast<Map<String, dynamic>>();
  }

  /// Delivery Rider: Scan to start delivery or complete
  Future<Map<String, dynamic>> scanDelivery(String code, {File? photo}) async => photo == null
      ? await send('POST', '/mobile/rider/delivery/scan', body: {'code': code.trim()})
      : await sendMultipart('/mobile/rider/delivery/scan', fields: {'code': code.trim()}, files: {'photo': photo});

  /// Delivery Rider: Proof of delivery
  Future<Map<String, dynamic>> uploadPod(int shipmentId, File photo) async =>
      await sendMultipart('/mobile/rider/delivery/proof/$shipmentId', fields: const {}, files: {'photo': photo});

  /// Delivery Rider: Fail delivery
  Future<Map<String, dynamic>> failDelivery(int shipmentId, {required String reason, String? note}) async =>
      await send('POST', '/mobile/rider/delivery/fail/$shipmentId', body: {
        'reason': reason,
        if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
      });

  /// Sends the attendance QR code shown at the logistics hub: the first scan
  /// of the day times the rider in, the next one times them out.
  Future<RiderAttendanceScanResult> scanAttendance(String code) async => RiderAttendanceScanResult.fromJson(
      await send('POST', '/mobile/rider/attendance/scan', body: {'code': code.trim()}));

  /// Proof of delivery: a [photo] of the parcel at the door. Returns the server's message.
  Future<String> uploadDeliveryPhoto(int id, File photo) async =>
      (await sendMultipart('/mobile/rider/deliveries/$id/proof', fields: const {}, files: {'photo': photo}))['message']
          as String;

  /// Completed deliveries for [period], newest first; [page] starts at 1.
  /// Delivered parcels for [period], or between [from] and [to] (inclusive) when given; [search] finds a tracking or
  /// order number or a recipient. Delivered parcels are kept for good, so any past range can be searched.
  Future<RiderHistoryPage> history({
    RiderPeriod period = RiderPeriod.all,
    int page = 1,
    DateTime? from,
    DateTime? to,
    String? search,
  }) async {
    String day(DateTime date) =>
        '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
    return RiderHistoryPage.fromJson(await send('GET', '/mobile/rider/history', query: {
      'period': period.value,
      'page': '$page',
      if (from != null) 'from': day(from),
      if (to != null) 'to': day(to),
      if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
    }));
  }

  /// Chat with the buyer of delivery [id]. Opening it marks the buyer's messages as read.
  Future<DeliveryChat> deliveryChat(int id) async =>
      DeliveryChat.fromJson((await send('GET', '/mobile/rider/deliveries/$id/chat'))['chat'] as Map<String, dynamic>);

  /// [clientId] makes a retried send safe: the server stores it only once.
  /// An [attachment] (photo or video) goes with the text, or alone.
  Future<DeliveryChatMessage> sendDeliveryMessage(int id, String body,
          {required String clientId, ChatAttachment? attachment}) async =>
      DeliveryChatMessage.fromJson((await sendChatMessage('/mobile/rider/deliveries/$id/chat',
          body: body, clientId: clientId, attachment: attachment))['message'] as Map<String, dynamic>);

  Future<RiderEarnings> earnings() async => RiderEarnings.fromJson(await send('GET', '/mobile/rider/earnings'));

  /// Shares where the rider is while a parcel is out for delivery, so the buyer can follow it on a map.
  Future<void> reportLocation(double latitude, double longitude) =>
      send('POST', '/mobile/rider/location', body: {'latitude': latitude, 'longitude': longitude});

  Future<RiderWallet> wallet() async => RiderWallet.fromJson(await send('GET', '/mobile/rider/wallet'));

  /// Asks for [amount] to be sent to the rider's saved GCash account. Returns the confirmation and the updated wallet.
  Future<(String, RiderWallet)> withdraw({required double amount}) async {
    final data = await send('POST', '/mobile/rider/wallet/withdrawals', body: {'amount': amount});
    return (data['message'] as String? ?? 'Withdrawal requested.', RiderWallet.fromJson(data));
  }

  /// Saves the GCash number and name withdrawals are sent to. Returns the confirmation and the updated wallet.
  Future<(String, RiderWallet)> saveGcashAccount({required String gcashNumber, required String gcashName}) async {
    final data = await send('PUT', '/mobile/rider/wallet/gcash', body: {'gcash_number': gcashNumber, 'gcash_name': gcashName});
    return (data['message'] as String? ?? 'GCash account saved.', RiderWallet.fromJson(data));
  }

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
