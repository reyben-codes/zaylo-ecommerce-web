import 'package:flutter/material.dart';
import 'package:zaylo_mobile/api/api_client.dart';
import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/notifications/push_notifications.dart';

class PhoneNotificationsScreen extends StatefulWidget {
  const PhoneNotificationsScreen({super.key, required this.api});
  final ApiClient api;
  @override
  State<PhoneNotificationsScreen> createState() => _PhoneNotificationsScreenState();
}

class _PhoneNotificationsScreenState extends State<PhoneNotificationsScreen> with WidgetsBindingObserver {
  String _status = 'Checking permission…';
  bool _busy = false;
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
    final status = await PushNotifications.instance.permissionStatus();
    if (mounted) setState(() => _status = status);
  }
  Future<void> _test() async {
    setState(() => _busy = true);
    try {
      await PushNotifications.instance.sync();
      final message = await widget.api.testPush();
      if (mounted) showMessage(context, message);
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
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
      const Text('Receive order updates, assignments and messages in your phone’s notification tray. Sound and pop-up banners follow your phone settings.', textAlign: TextAlign.center),
      const SizedBox(height: 24),
      FilledButton.icon(onPressed: _busy ? null : _test, icon: const Icon(Icons.send_outlined), label: Text(_busy ? 'Sending…' : 'Send test notification')),
      const SizedBox(height: 12),
      OutlinedButton(onPressed: () async { await PushNotifications.instance.openSettings(); }, child: const Text('Open phone settings')),
    ]),
  );
}
