import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

import 'package:zaylo_mobile/api/api_client.dart';

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
class PushNotifications {
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
        _fcmToken = token;
        await _registerAll();
        final event = _registrationEvent;
        if (event != null && !event.isCompleted) event.complete();
      });
      FirebaseMessaging.instance.onUnregistered.listen((_) {
        _fcmToken = null;
      });
      final initial = await FirebaseMessaging.instance.getInitialMessage();
      final nativeInitial = await _native.invokeMethod<String>('pendingTap');
      _ready = true;
      if (initial != null) _open(initial.data);
      if (nativeInitial != null) _open(jsonDecode(nativeInitial) as Map<String, dynamic>);
      await sync();
    } catch (_) {
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
    try {
      final settings = await FirebaseMessaging.instance.requestPermission();
      _allowed = settings.authorizationStatus == AuthorizationStatus.authorized || settings.authorizationStatus == AuthorizationStatus.provisional;
      if (!_allowed) {
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
    } catch (_) {
      // Retry at the next foreground transition; sign-in remains usable offline.
    }
  }

  Future<void> _registerAll() async {
    final token = _fcmToken;
    if (token == null || !_allowed) return;
    for (final session in List.of(_sessions.values)) {
      if (session.api.token == null) continue;
      try { await session.api.registerPush(token); } catch (_) {}
    }
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

  Future<void> openSettings() async {
    if (!_ready) return;
    await _native.invokeMethod<void>('settings');
  }
}
