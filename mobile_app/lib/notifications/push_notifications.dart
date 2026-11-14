import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

import 'package:zaylo_mobile/api/api_client.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/notifications/phone_notification_access.dart';

/// One Android installation can retain separate buyer and rider sessions.
class PushSession {
  const PushSession(this.api, this.userId, {required this.rider});
  final ApiClient api;
  final int userId;
  final bool rider;
}

class PushTap {
  const PushTap(this.session, this.kind, this.entityId);
  final PushSession session;
  final String kind;
  final int entityId;
}

/// FCM displays background notifications. Android's small native bridge handles foreground display.
class PushNotifications implements PhoneNotificationAccess {
  PushNotifications._();
  static final instance = PushNotifications._();
  static const _native = MethodChannel('shop.zaylo/notifications');
  final _sessions = <ApiClient, PushSession>{};
  final _taps = StreamController<PushTap>.broadcast();
  Stream<PushTap> get taps => _taps.stream;
  Map<String, dynamic>? _pendingTap;
  bool _ready = false;
  bool _initializing = false;
  bool _navigationReady = false;
  String? _fcmToken;
  Future<void>? _registration;
  Completer<void>? _registrationEvent;
  bool _allowed = false;
  bool _syncAgain = false;
  ApiException? _registrationError;
  final _uploadErrors = <ApiClient, ApiException>{};
  final _registeredTokens = <ApiClient, String?>{};

  Future<void> initialize() async {
    if (_ready || _initializing || kIsWeb || !Platform.isAndroid) return;
    _initializing = true;
    try {
      await Firebase.initializeApp(); // Android uses google-services.json; no server key enters the app.
      await _native.invokeMethod<void>('createChannel');
      _native.setMethodCallHandler((call) async {
        if (call.method == 'notificationTapped' && call.arguments is String) {
          _open(jsonDecode(call.arguments as String) as Map<String, dynamic>);
        }
      });
      FirebaseMessaging.onMessageOpenedApp.listen((message) => _open(message.data));
      FirebaseMessaging.onMessage.listen((message) async {
        final session = _sessionFor(message.data);
        if (session == null || message.notification == null) return;
        try {
          await _native.invokeMethod<void>('show', {
            'title': message.notification!.title ?? 'ZAYLO',
            'body': message.notification!.body ?? 'You have an update in ZAYLO.',
            'payload': jsonEncode(message.data),
            'tag': message.data['notification_id'] ?? message.messageId ?? jsonEncode(message.data),
          });
        } catch (_) {
          // Notification permissions/settings must never interrupt the app.
        }
      });
      FirebaseMessaging.instance.onRegistered.listen((token) async {
        if (_fcmToken != token) _registeredTokens.clear();
        _fcmToken = token;
        await _registerAll();
        final event = _registrationEvent;
        if (event != null && !event.isCompleted) event.complete();
      });
      FirebaseMessaging.instance.onUnregistered.listen((_) {
        _fcmToken = null;
        _registeredTokens.clear();
      });
      final initial = await FirebaseMessaging.instance.getInitialMessage();
      final nativeInitial = await _native.invokeMethod<String>('pendingTap');
      _ready = true;
      if (initial != null) _open(initial.data);
      if (nativeInitial != null) _open(jsonDecode(nativeInitial) as Map<String, dynamic>);
      await sync();
    } catch (error) {
      _registrationError = _registrationFailure(error);
      // A phone without Google Play services, or a missing debug config, can still use ZAYLO.
      if (kDebugMode) debugPrint('[ZAYLO] Push notification initialization is unavailable.');
    } finally {
      _initializing = false;
    }
  }

  void bind(ApiClient api, int userId, {bool rider = false}) {
    _sessions[api] = PushSession(api, userId, rider: rider);
    unawaited(sync());
    _flushTap();
  }

  Future<void> unbind(ApiClient api, {bool unregister = true}) async {
    _sessions.remove(api);
    _uploadErrors.remove(api);
    _registeredTokens.remove(api);
    // Clear previously displayed notifications on local logout, even if the network is unavailable.
    if (!_ready) return;
    try { await _native.invokeMethod<void>('clear'); } catch (_) {}
    if (unregister) {
      try { await api.unregisterPush(); } catch (_) {}
    }
    if (_sessions.isEmpty) {
      try { await FirebaseMessaging.instance.unregister(); } catch (_) {}
      _fcmToken = null;
    }
  }

  /// Called on resume so changes in Android notification settings and offline registrations catch up.
  Future<void> sync() async {
    if (!_ready || _sessions.isEmpty) return;
    if (_registration != null) {
      _syncAgain = true;
      return _registration;
    }
    final task = _syncLoop();
    _registration = task;
    try { await task; } finally { _registration = null; }
  }

  Future<void> _syncLoop() async {
    do {
      _syncAgain = false;
      await _sync();
    } while (_syncAgain && _sessions.isNotEmpty);
  }

  Future<void> _sync() async {
    _registrationError = null;
    try {
      final settings = await FirebaseMessaging.instance.requestPermission();
      _allowed = settings.authorizationStatus == AuthorizationStatus.authorized || settings.authorizationStatus == AuthorizationStatus.provisional;
      if (!_allowed) {
        _registeredTokens.clear();
        for (final session in List.of(_sessions.values)) {
          try { await session.api.unregisterPush(); } catch (_) {}
        }
        return;
      }
      if (_fcmToken != null) {
        await _registerAll();
      } else {
        final event = Completer<void>();
        _registrationEvent = event;
        try {
          await FirebaseMessaging.instance.register();
          await event.future.timeout(const Duration(seconds: 20));
        } finally {
          if (identical(_registrationEvent, event)) _registrationEvent = null;
        }
      }
    } catch (error) {
      _registrationError = _registrationFailure(error);
      // Retry at the next foreground transition; sign-in remains usable offline.
    }
  }

  Future<void> _registerAll() async {
    final token = _fcmToken;
    if (token == null || !_allowed) return;
    for (final session in List.of(_sessions.values)) {
      if (session.api.token == null) continue;
      final accessToken = session.api.token;
      try {
        await session.api.registerPush(token);
        if (identical(_sessions[session.api], session) && session.api.token == accessToken && _fcmToken == token) {
          _registeredTokens[session.api] = accessToken;
          _uploadErrors.remove(session.api);
        }
      } on ApiException catch (error) {
        _registeredTokens.remove(session.api);
        _uploadErrors[session.api] = error;
      } catch (_) {
        _registeredTokens.remove(session.api);
        _uploadErrors[session.api] = const ApiException('Could not connect this phone to ZAYLO. Check your connection and try again.');
      }
    }
  }

  @override
  Future<void> ensureRegistered(ApiClient api) async {
    if (api.token == null) throw const ApiException('Please sign in again to connect phone notifications.');
    // A restored offline session might not have been bound during launch. Retry it explicitly here.
    if (!_sessions.containsKey(api)) {
      final user = await api.me();
      _sessions[api] = PushSession(api, user.id, rider: api is RiderApi);
    }
    if (!_ready) await initialize();
    if (!_ready) throw _registrationError ?? const ApiException('Phone notifications are unavailable on this device. Check Google Play services and try again.');
    await sync();
    if (!_allowed) throw _registrationError ?? const ApiException('Notification permission is disabled. Enable it in phone settings.');
    if (_uploadErrors[api] case final error?) throw error;
    if (_registrationError != null) throw _registrationError!;
    if (_registeredTokens[api] != api.token) {
      throw const ApiException('This phone has not connected to ZAYLO notifications yet. Check your connection and try again.');
    }
  }

  ApiException _registrationFailure(Object error) {
    if (error is TimeoutException) return const ApiException('Firebase registration timed out. Check your connection and try again.');
    if (error is FirebaseException) {
      if ((error.message ?? '').contains('firebase_messaging_installation_id_enabled')) {
        return const ApiException('This app version needs a notification setup update. Install the latest ZAYLO APK.');
      }
      return const ApiException('Could not register with Firebase. Check your internet connection and Google Play services, then try again.');
    }
    return const ApiException('Phone notification setup failed. Restart ZAYLO and try again.');
  }

  PushSession? _sessionFor(Map<String, dynamic> data) {
    final userId = int.tryParse('${data['user_id']}');
    for (final session in _sessions.values) {
      if (session.userId == userId && session.api.token != null) return session;
    }
    return null;
  }

  void _open(Map<String, dynamic> data) {
    // Never open another account's notification or arbitrary external links from a payload.
    _pendingTap = data;
    _flushTap();
  }

  void _flushTap() {
    final data = _pendingTap;
    if (data == null || !_taps.hasListener || !_navigationReady) return;
    final session = _sessionFor(data);
    if (session == null) return; // Retain only until this account's session has restored.
    _pendingTap = null;
    _taps.add(PushTap(session, '${data['kind']}', int.tryParse('${data['entity_id']}') ?? 0));
  }

  void flushPendingTap() => _flushTap();

  void navigationReady() {
    _navigationReady = true;
    _flushTap();
  }

  @override
  Future<String> permissionStatus() async {
    if (!_ready) return 'Unavailable on this device';
    try {
      final settings = await FirebaseMessaging.instance.getNotificationSettings();
      return switch (settings.authorizationStatus) {
        AuthorizationStatus.authorized || AuthorizationStatus.provisional => 'Allowed',
        AuthorizationStatus.denied => 'Disabled in phone settings',
        _ => 'Permission not requested yet',
      };
    } catch (_) { return 'Unavailable on this device'; }
  }

  @override
  Future<void> openSettings() async {
    if (!_ready) return;
    await _native.invokeMethod<void>('settings');
  }
}
