import 'package:flutter/material.dart';
import 'package:zaylo_mobile/api/api_client.dart';
import 'package:zaylo_mobile/components/feedback.dart';

class AccountUpdatesScreen extends StatefulWidget {
  const AccountUpdatesScreen({super.key, required this.api});
  final ApiClient api;
  @override
  State<AccountUpdatesScreen> createState() => _AccountUpdatesScreenState();
}

class _AccountUpdatesScreenState extends State<AccountUpdatesScreen> {
  late Future<Map<String, dynamic>> _feed = widget.api.accountUpdates();
  Future<void> _reload() async {
    final future = widget.api.accountUpdates();
    setState(() => _feed = future);
    try { await future; } catch (_) { /* FutureBuilder shows retry. */ }
  }
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Notifications')),
    body: FutureBuilder<Map<String, dynamic>>(future: _feed, builder: (context, snapshot) {
      if (snapshot.hasError) return Center(child: TextButton(onPressed: _reload, child: const Text('Could not load updates. Retry')));
      if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
      final data = snapshot.requireData;
      final items = data['updates'] as List? ?? [];
      return RefreshIndicator(onRefresh: _reload, child: ListView(physics: const AlwaysScrollableScrollPhysics(), children: [
        if (items.isEmpty) const ListTile(title: Text('No updates yet')),
        if (items.isNotEmpty) TextButton(onPressed: () async {
          try {
            await widget.api.readAccountUpdates(through: data['through'] as int);
            if (mounted) await _reload();
          } catch (_) { if (context.mounted) showMessage(context, 'Could not mark updates read. Please retry.'); }
        }, child: const Text('Mark all as read')),
        for (final item in items) ListTile(
          leading: Icon(item['read'] == true ? Icons.notifications_none : Icons.notifications_active),
          title: Text(item['title'] as String), subtitle: Text(item['body'] as String),
        ),
      ]));
    }),
  );
}
