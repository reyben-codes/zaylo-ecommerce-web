import 'dart:async';
import 'package:flutter/material.dart';
import 'package:zaylo_mobile/api/api_client.dart';

/// Counts belong to this API session; no badge data is shared between buyer and rider logins.
class AccountUpdateBadge extends StatefulWidget {
  const AccountUpdateBadge({super.key, required this.api, required this.child, this.section});
  final ApiClient api;
  final Widget child;
  final String? section;
  @override
  State<AccountUpdateBadge> createState() => _AccountUpdateBadgeState();
}

class _AccountUpdateBadgeState extends State<AccountUpdateBadge> {
  Timer? _timer;
  int _count = 0;
  bool _busy = false;
  @override
  void initState() {
    super.initState();
    _refresh();
    _timer = Timer.periodic(const Duration(seconds: 15), (_) => _refresh());
  }
  @override
  void didUpdateWidget(AccountUpdateBadge oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.api != widget.api || oldWidget.section != widget.section) { _count = 0; _refresh(); }
  }
  Future<void> _refresh() async {
    if (_busy || widget.api.token == null || WidgetsBinding.instance.lifecycleState == AppLifecycleState.paused) return;
    _busy = true;
    final api = widget.api;
    final token = api.token;
    try {
      final data = await api.accountUpdates();
      if (mounted && identical(api, widget.api) && api.token == token) {
        setState(() => _count = (widget.section == null ? data['unread'] : (data['counts'] as Map?)?[widget.section]) as int? ?? 0);
      }
    } catch (_) { /* Retry while this session is still active. */ }
    finally { _busy = false; }
  }
  @override
  void dispose() { _timer?.cancel(); super.dispose(); }
  @override
  Widget build(BuildContext context) => Badge(isLabelVisible: _count > 0,
      label: Text(_count > 99 ? '99+' : '$_count'), child: widget.child);
}
