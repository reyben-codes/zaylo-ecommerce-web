import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/features/rider/dashboard/rider_delivery_detail_screen.dart';
import 'package:zaylo_mobile/features/rider/history/rider_attendance_screen.dart';
import 'package:zaylo_mobile/features/rider/dashboard/widgets/rider_dashboard_widgets.dart';
import 'package:zaylo_mobile/models/rider/rider_activity_models.dart';
import 'package:zaylo_mobile/models/rider/rider_dashboard_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

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

  /// A date range the rider picked replaces the period chips.
  DateTimeRange? _range;
  final _search = TextEditingController();

  Future<void> _pickRange() async {
    final now = DateTime.now();
    final picked = await showDateRangePicker(
      context: context,
      firstDate: DateTime(now.year - 3),
      lastDate: now,
      initialDateRange: _range,
      helpText: 'Delivered between',
    );
    if (picked == null || !mounted) return;
    setState(() => _range = picked);
    _reload();
  }

  void _clearRange() {
    setState(() => _range = null);
    _reload();
  }

  @override
  void initState() {
    super.initState();
    widget.period.addListener(_reload);
    _reload();
  }

  @override
  void dispose() {
    widget.period.removeListener(_reload);
    _search.dispose();
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
      final result = await widget.api.history(
        period: widget.period.value,
        page: page,
        from: _range?.start,
        to: _range?.end,
        search: _search.text,
      );
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
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 0),
          child: OutlinedButton.icon(
            icon: const Icon(Icons.event_available_outlined),
            label: const Text('Attendance & schedule'),
            onPressed: () => Navigator.of(context).push(MaterialPageRoute(
              builder: (_) => RiderAttendanceScreen(api: widget.api),
            )),
          ),
        ),
        SizedBox(
          height: 56,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.fromLTRB(20, 10, 20, 6),
            children: [
              if (_range case final range?)
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: InputChip(
                    avatar: Icon(Icons.date_range, size: 18, color: ZayloColors.white),
                    label: Text('${shortDate(range.start)} – ${shortDate(range.end)}'),
                    selected: true,
                    showCheckmark: false,
                    selectedColor: ZayloColors.ink,
                    labelStyle: TextStyle(color: ZayloColors.white),
                    deleteIconColor: ZayloColors.white,
                    onPressed: _pickRange,
                    onDeleted: _clearRange,
                    deleteButtonTooltipMessage: 'Clear dates',
                  ),
                )
              else
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ActionChip(
                    avatar: const Icon(Icons.date_range, size: 18),
                    label: const Text('Dates'),
                    onPressed: _pickRange,
                  ),
                ),
              for (final period in RiderPeriod.values)
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(period.label),
                    selected: _range == null && widget.period.value == period,
                    selectedColor: ZayloColors.ink,
                    labelStyle: TextStyle(
                        color: _range == null && widget.period.value == period ? ZayloColors.white : ZayloColors.ink),
                    onSelected: (_) {
                      if (_range != null) {
                        _range = null;
                        if (widget.period.value == period) _reload();
                      }
                      widget.period.value = period;
                    },
                  ),
                ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 4),
          child: TextField(
            controller: _search,
            textInputAction: TextInputAction.search,
            onSubmitted: (_) => _reload(),
            decoration: InputDecoration(
              isDense: true,
              prefixIcon: const Icon(Icons.search),
              hintText: 'Tracking no., order no. or recipient',
              suffixIcon: IconButton(
                tooltip: 'Clear search',
                icon: const Icon(Icons.close),
                onPressed: () {
                  _search.clear();
                  _reload();
                },
              ),
            ),
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
                          message: _range != null || _search.text.trim().isNotEmpty
                              ? 'No deliveries match these dates or search.'
                              : 'No deliveries completed ${widget.period.value == RiderPeriod.all ? 'yet' : widget.period.value.label.toLowerCase()}.',
                        ),
                      for (final delivery in _deliveries)
                        RiderDeliveryCard(
                          delivery: delivery,
                          onTap: () => Navigator.of(context).push(MaterialPageRoute(
                            builder: (_) => RiderDeliveryDetailScreen(
                                api: widget.api, deliveryId: delivery.id, preview: delivery),
                          )),
                        ),
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
        Text(label, style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
      ]);
}
