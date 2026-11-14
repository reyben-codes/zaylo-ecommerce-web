import 'package:zaylo_mobile/api/api_client.dart';

/// Permission and registration are separate: Android permission alone cannot receive a server push.
abstract interface class PhoneNotificationAccess {
  Future<String> permissionStatus();
  Future<void> ensureRegistered(ApiClient api);
  Future<void> openSettings();
}
