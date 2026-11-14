import 'package:flutter/material.dart';
import 'package:zaylo_mobile/api/api_client.dart';
import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/notifications/push_notifications.dart';
import 'package:zaylo_mobile/notifications/phone_notification_access.dart';

class PhoneNotificationsScreen extends StatefulWidget {
  const PhoneNotificationsScreen({super.key, required this.api, this.notifications});
  final ApiClient api;
  final PhoneNotificationAccess? notifications;
  @override
  State<PhoneNotificationsScreen> createState() => _PhoneNotificationsScreenState();
}

class _PhoneNotificationsScreenState extends State<PhoneNotificationsScreen> with WidgetsBindingObserver {
  String _status = 'Checking permission…';
  bool _busy = false;
  String? _connectionError;
  PhoneNotificationAccess get _notifications => widget.notifications ?? PushNotifications.instance;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _refresh();
  }
  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _refresh();
  }
  Future<void> _refresh() async {
    final status = await _notifications.permissionStatus();
    if (mounted) setState(() => _status = status);
  }
  Future<void> _test() async {
    setState(() { _busy = true; _connectionError = null; });
    try {
      await _notifications.ensureRegistered(widget.api);
      final message = await widget.api.testPush();
      if (mounted) showMessage(context, message);
    } on ApiException catch (error) {
      if (mounted) setState(() => _connectionError = error.message);
    } finally {
      await _refresh();
      if (mounted) setState(() => _busy = false);
    }
  }
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Phone notifications')),
    body: ListView(padding: const EdgeInsets.all(24), children: [
      const Icon(Icons.notifications_active_outlined, size: 48),
      const SizedBox(height: 20),
      Text(_status, textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleLarge),
      const SizedBox(height: 12),
      if (_connectionError != null) ...[
        Semantics(liveRegion: true, child: Text(_connectionError!, textAlign: TextAlign.center,
          style: TextStyle(color: Theme.of(context).colorScheme.error))),
        const SizedBox(height: 16),
      ],
      const Text('Receive order updates, assignments and messages in your phone’s notification tray. Sound and pop-up banners follow your phone settings.', textAlign: TextAlign.center),
      const SizedBox(height: 24),
      FilledButton.icon(onPressed: _busy ? null : _test, icon: const Icon(Icons.send_outlined), label: Text(_busy ? 'Sending…' : 'Send test notification')),
      const SizedBox(height: 12),
      OutlinedButton(onPressed: () async { await _notifications.openSettings(); }, child: const Text('Open phone settings')),
    ]),
  );
}
