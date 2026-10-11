import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/zaylo_theme.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/feedback.dart';
import '../../core/widgets/layout.dart';
import '../api/rider_api.dart';
import '../models/rider_activity_models.dart';
import '../models/rider_dashboard_models.dart';
import '../widgets/rider_dashboard_widgets.dart';

/// Delivery history: completed deliveries filtered by period, with totals
/// and "load more". [period] lets other screens open it on a period, such
/// as "Delivered today" on the dashboard.
class RiderHistoryTab extends StatefulWidget {
  const RiderHistoryTab({super.key, required this.api, required this.period});

  final RiderApi api;
  final ValueNotifier<RiderPeriod> period;

  @override
  State<RiderHistoryTab> createState() => _RiderHistoryTabState();
}

class _RiderHistoryTabState extends State<RiderHistoryTab> {
  final _deliveries = <RiderDelivery>[];
  RiderTotals _summary = const RiderTotals();
  int? _nextPage;
  bool _loading = false;
  Object? _error;

  /// Ignores responses from a filter the rider has already left.
  int _generation = 0;

  @override
  void initState() {
    super.initState();
    widget.period.addListener(_reload);
    _reload();
  }

  @override
  void dispose() {
    widget.period.removeListener(_reload);
    super.dispose();
  }

  Future<void> _reload() async {
    _generation++; // drop any page still loading for the previous filter
    setState(() {
      _loading = false;
      _deliveries.clear();
      _nextPage = 1;
      _error = null;
    });
    await _loadMore();
  }

  Future<void> _loadMore() async {
    final page = _nextPage;
    if (page == null || _loading) return;
    final generation = ++_generation;
    setState(() => _loading = true);
    try {
      final result = await widget.api.history(period: widget.period.value, page: page);
      if (!mounted || generation != _generation) return;
      setState(() {
        _summary = result.summary;
        _deliveries.addAll(result.deliveries);
        _nextPage = result.nextPage;
      });
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Rider history failed: ${error.debugDescription}');
      if (mounted && generation == _generation) setState(() => _error = error);
    } finally {
      if (mounted && generation == _generation) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Column(children: [
        SizedBox(
          height: 56,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.fromLTRB(20, 10, 20, 6),
            children: [
              for (final period in RiderPeriod.values)
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(period.label),
                    selected: widget.period.value == period,
                    selectedColor: ZayloColors.ink,
                    labelStyle: TextStyle(color: widget.period.value == period ? ZayloColors.white : ZayloColors.ink),
                    onSelected: (_) => widget.period.value = period,
                  ),
                ),
            ],
          ),
        ),
        Expanded(
          child: _error != null && _deliveries.isEmpty
              ? LoadError(error: _error, onRetry: _reload, title: 'Could not load your history')
              : RefreshIndicator(
                  color: ZayloColors.ink,
                  onRefresh: _reload,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: EdgeInsets.fromLTRB(20, 6, 20, 32 + MediaQuery.paddingOf(context).bottom),
                    children: [
                      SectionCard(
                        child: Row(children: [
                          Expanded(child: _Total(label: 'Deliveries', value: '${_summary.deliveries}')),
                          Expanded(child: _Total(label: 'Delivery fees earned', value: peso(_summary.earnings))),
                        ]),
                      ),
                      const SizedBox(height: 14),
                      if (_deliveries.isEmpty && !_loading)
                        RiderEmptyCard(
                          icon: Icons.history,
                          message: 'No deliveries completed ${widget.period.value == RiderPeriod.all ? 'yet' : widget.period.value.label.toLowerCase()}.',
                        ),
                      for (final delivery in _deliveries) RiderDeliveryCard(delivery: delivery),
                      if (_loading)
                        const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator()))
                      else if (_nextPage != null)
                        OutlinedButton(onPressed: _loadMore, child: const Text('Load more')),
                    ],
                  ),
                ),
        ),
      ]);
}

class _Total extends StatelessWidget {
  const _Total({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(value, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
        Text(label, style: const TextStyle(fontSize: 12, color: ZayloColors.muted)),
      ]);
}
