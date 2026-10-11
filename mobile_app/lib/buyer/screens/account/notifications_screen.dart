import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/zaylo_theme.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/feedback.dart';
import '../../api/buyer_api.dart';
import '../../models/account_models.dart';
import '../../models/catalog_models.dart';
import '../../state/app_state.dart';
import '../../widgets/shop_actions.dart';
import '../orders/orders_screen.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  NotificationFeed? _feed;
  Object? _error;

  BuyerApi get _api => AppScope.read(context).api;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    try {
      final feed = await _api.notifications();
      if (mounted) {
        setState(() {
          _feed = feed;
          _error = null;
        });
      }
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error);
    }
  }

  Future<void> _markAllRead() async {
    try {
      final feed = await _api.markNotificationsRead();
      if (!mounted) return;
      setState(() => _feed = feed);
      showMessage(context, 'All notifications marked as read.');
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    }
  }

  /// Marks one read, then opens what it is about, like the web's "open" action.
  Future<void> _open(AppNotification item) async {
    if (!item.read) {
      _api.markNotificationsRead(key: item.key).then((feed) {
        if (mounted) setState(() => _feed = feed);
      }).catchError((_) {});
    }
    switch (item.type) {
      case 'product' when item.productSlug != null:
        ShopActions.openProduct(
          context,
          Product(id: 0, slug: item.productSlug!, name: item.title, categoryLabel: 'ZAYLO', price: 0),
        );
      case 'voucher':
        ShopActions.showTab(context, 2);
      default:
        Navigator.of(context).push(MaterialPageRoute(builder: (_) => const OrdersScreen()));
    }
  }

  @override
  Widget build(BuildContext context) {
    final feed = _feed;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          if (feed != null && feed.unreadCount > 0)
            TextButton(onPressed: _markAllRead, child: const Text('Mark all read')),
        ],
      ),
      body: feed == null
          ? (_error != null ? LoadError(error: _error, onRetry: _load) : const Loader())
          : feed.notifications.isEmpty
              ? const EmptyState(
                  icon: Icons.notifications_none,
                  title: 'You are all caught up',
                  message: 'Order updates, new arrivals and vouchers from the last 30 days appear here.',
                )
              : RefreshIndicator(
                  color: ZayloColors.ink,
                  onRefresh: _load,
                  child: ListView.separated(
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    itemCount: feed.notifications.length,
                    separatorBuilder: (_, __) => const Divider(height: 1, color: ZayloColors.line),
                    itemBuilder: (context, index) {
                      final item = feed.notifications[index];
                      return ListTile(
                        onTap: () => _open(item),
                        tileColor: item.read ? null : ZayloColors.sand.withValues(alpha: .45),
                        contentPadding: const EdgeInsets.symmetric(horizontal: ZayloSpace.page, vertical: 6),
                        leading: CircleAvatar(
                          backgroundColor: ZayloColors.ink,
                          foregroundColor: ZayloColors.white,
                          child: Icon(switch (item.type) {
                            'voucher' => Icons.confirmation_number_outlined,
                            'product' => Icons.new_releases_outlined,
                            _ => Icons.local_shipping_outlined,
                          }, size: 20),
                        ),
                        title: Text(item.title,
                            style: TextStyle(
                                fontWeight: item.read ? FontWeight.w500 : FontWeight.w800,
                                color: ZayloColors.ink,
                                fontSize: 14)),
                        subtitle: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          const SizedBox(height: 2),
                          Text(item.message, style: const TextStyle(fontSize: 12)),
                          const SizedBox(height: 4),
                          Text('${relativeTime(item.at)} · ${item.action}',
                              style: const TextStyle(fontSize: 11, color: ZayloColors.taupe)),
                        ]),
                        trailing: item.read
                            ? null
                            : const CircleAvatar(radius: 4, backgroundColor: Colors.redAccent),
                      );
                    },
                  ),
                ),
    );
  }
}
