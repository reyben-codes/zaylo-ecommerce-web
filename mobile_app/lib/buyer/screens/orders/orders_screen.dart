import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/zaylo_theme.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/layout.dart';
import '../../models/order_models.dart';
import '../../state/app_state.dart';
import '../../widgets/shop_actions.dart';
import 'order_detail_screen.dart';
import 'status_pill.dart';

/// Same groups as the web "My Orders" tabs.
const _orderTabs = {
  'all': 'All',
  'to_ship': 'To ship',
  'shipped': 'Shipped',
  'delivered': 'Delivered',
  'cancelled': 'Cancelled',
};

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key, this.initialTab = 'all'});

  final String initialTab;

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  late String _tab = widget.initialTab;
  final _scroll = ScrollController();
  final List<Order> _orders = [];
  Map<String, int> _counts = {};
  int _page = 0;
  bool _hasMore = true;
  bool _loading = false;
  Object? _error;
  int _generation = 0;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.extentAfter < 400) _loadMore();
    });
    WidgetsBinding.instance.addPostFrameCallback((_) => _reload());
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _reload() async {
    _generation++;
    setState(() {
      _orders.clear();
      _page = 0;
      _hasMore = true;
      _loading = false;
      _error = null;
    });
    await _loadMore();
  }

  Future<void> _loadMore() async {
    if (_loading || !_hasMore) return;
    final generation = _generation;
    setState(() => _loading = true);
    try {
      final page = await AppScope.read(context).api.orders(status: _tab, page: _page + 1);
      if (!mounted || generation != _generation) return;
      setState(() {
        _orders.addAll(page.orders);
        _counts = page.counts;
        _page = page.currentPage;
        _hasMore = page.hasMore;
      });
    } on ApiException catch (error) {
      if (mounted && generation == _generation) setState(() => _error = error);
    } finally {
      if (mounted && generation == _generation) setState(() => _loading = false);
    }
  }

  Future<void> _open(Order order) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => OrderDetailScreen(orderId: order.id)),
    );
    if (changed == true) _reload();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('My orders')),
        body: Column(children: [
          SizedBox(
            height: 48,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: ZayloSpace.page, vertical: 6),
              children: [
                for (final entry in _orderTabs.entries)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(_counts[entry.key] == null ? entry.value : '${entry.value} (${_counts[entry.key]})'),
                      selected: _tab == entry.key,
                      showCheckmark: false,
                      selectedColor: ZayloColors.ink,
                      backgroundColor: ZayloColors.white,
                      side: const BorderSide(color: ZayloColors.line),
                      labelStyle: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: _tab == entry.key ? ZayloColors.white : ZayloColors.ink,
                      ),
                      onSelected: (_) {
                        if (_tab == entry.key) return;
                        _tab = entry.key;
                        _reload();
                      },
                    ),
                  ),
              ],
            ),
          ),
          Expanded(child: _list()),
        ]),
      );

  Widget _list() {
    if (_orders.isEmpty) {
      if (_error != null) return LoadError(error: _error, onRetry: _reload, title: 'Could not load orders');
      if (_loading || _hasMore) return const Loader();
      return EmptyState(
        icon: Icons.inventory_2_outlined,
        title: _tab == 'all' ? 'No orders yet' : 'Nothing here',
        message: _tab == 'all'
            ? 'When you place an order, you can follow it here from checkout to delivery.'
            : 'You have no orders in "${_orderTabs[_tab]}".',
        actionLabel: _tab == 'all' ? 'Start shopping' : null,
        onAction: () => ShopActions.showTab(context, 0),
      );
    }
    return RefreshIndicator(
      color: ZayloColors.ink,
      onRefresh: _reload,
      child: ListView.separated(
        controller: _scroll,
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 8, ZayloSpace.page, 28),
        itemCount: _orders.length + 1,
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (context, index) {
          if (index == _orders.length) {
            if (_error != null) {
              return Center(child: TextButton(onPressed: _loadMore, child: const Text('Could not load more. Retry')));
            }
            return _hasMore ? const Padding(padding: EdgeInsets.all(12), child: Loader()) : const SizedBox.shrink();
          }
          return _OrderCard(order: _orders[index], onTap: () => _open(_orders[index]));
        },
      ),
    );
  }
}

class _OrderCard extends StatelessWidget {
  const _OrderCard({required this.order, required this.onTap});

  final Order order;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
        color: ZayloColors.paper,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: const BorderSide(color: ZayloColors.line),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Expanded(
                  child: Text(order.orderNumber,
                      style: const TextStyle(fontWeight: FontWeight.w800, color: ZayloColors.ink)),
                ),
                StatusPill(order),
              ]),
              const SizedBox(height: 2),
              Text('Placed ${dateTimeLabel(order.placedAt)}', style: const TextStyle(fontSize: 12)),
              const SizedBox(height: 12),
              Row(children: [
                for (final item in order.items.take(4))
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: SizedBox(width: 50, height: 50, child: NetworkImageBox(url: item.imageUrl)),
                    ),
                  ),
                if (order.items.length > 4)
                  Text('+${order.items.length - 4}', style: const TextStyle(fontWeight: FontWeight.w700)),
              ]),
              if (order.items.length == 1) ...[
                const SizedBox(height: 8),
                Text(order.items.first.productName,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 13, color: ZayloColors.ink)),
              ],
              const SizedBox(height: 10),
              Text(order.statusMessage, style: const TextStyle(fontSize: 12)),
              const Divider(color: ZayloColors.line, height: 22),
              Row(children: [
                Text('${order.itemCount} ${order.itemCount == 1 ? 'item' : 'items'}',
                    style: const TextStyle(fontSize: 12)),
                const Spacer(),
                const Text('Total  ', style: TextStyle(fontSize: 12)),
                Text(peso(order.total), style: const TextStyle(fontWeight: FontWeight.w800, color: ZayloColors.ink)),
              ]),
            ]),
          ),
        ),
      );
}
