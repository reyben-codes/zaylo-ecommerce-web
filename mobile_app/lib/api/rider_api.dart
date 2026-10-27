import 'dart:io';

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

  /// Reports a failed delivery attempt with its [reason] (and the rider's own words in [note]); the buyer and
  /// seller are told. Starting and finishing a delivery go through [scanParcel].
  Future<RiderDelivery> updateDelivery(int id, String status, {String? reason, String? note}) async =>
      RiderDelivery.fromJson((await send('PATCH', '/mobile/rider/deliveries/$id', body: {
        'status': status,
        if (reason != null) 'reason': reason,
        if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
      }))['delivery'] as Map<String, dynamic>);

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

  /// Proof of delivery: a [photo] of the parcel at the door. Returns the server's message.
  Future<String> uploadDeliveryPhoto(int id, File photo) async =>
      (await sendMultipart('/mobile/rider/deliveries/$id/proof', fields: const {}, files: {'photo': photo}))['message']
          as String;

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
