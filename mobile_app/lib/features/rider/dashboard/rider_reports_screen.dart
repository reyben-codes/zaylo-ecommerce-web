import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/models/rider/rider_activity_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

/// The rider's reports for a period: success rate, deliveries and failed attempts, assignments accepted and
/// declined, cash collected and delivery fees, why deliveries failed or were declined, and the last 7 days.
class RiderReportsScreen extends StatefulWidget {
  const RiderReportsScreen({super.key, required this.api});

  final RiderApi api;

  @override
  State<RiderReportsScreen> createState() => _RiderReportsScreenState();
}

class _RiderReportsScreenState extends State<RiderReportsScreen> {
  RiderPeriod _period = RiderPeriod.week;
  RiderReport? _report;
  Object? _error;
  bool _loading = false;

  /// Ignores a reply for a period the rider has already left.
  int _generation = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final generation = ++_generation;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final report = await widget.api.reports(period: _period);
      if (mounted && generation == _generation) setState(() => _report = report);
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Rider reports failed: ${error.debugDescription}');
      if (mounted && generation == _generation) setState(() => _error = error);
    } finally {
      if (mounted && generation == _generation) setState(() => _loading = false);
    }
  }

  void _choose(RiderPeriod period) {
    if (period == _period) return;
    setState(() => _period = period);
    _load();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: ZayloColors.white,
        appBar: AppBar(backgroundColor: ZayloColors.white, title: const Text('Reports')),
        body: Column(children: [
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
                      selected: _period == period,
                      selectedColor: ZayloColors.ink,
                      labelStyle: TextStyle(color: _period == period ? ZayloColors.white : ZayloColors.ink),
                      onSelected: (_) => _choose(period),
                    ),
                  ),
              ],
            ),
          ),
          if (_loading && _report != null) LinearProgressIndicator(minHeight: 2, color: ZayloColors.ink),
          Expanded(child: _body()),
        ]),
      );

  Widget _body() {
    final report = _report;
    if (report == null) {
      return _error != null
          ? LoadError(error: _error, onRetry: _load, title: 'Could not load your reports')
          : const Loader();
    }
    final rate = report.successRate;
    return RefreshIndicator(
      color: ZayloColors.ink,
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(color: ZayloColors.ink, borderRadius: BorderRadius.circular(18)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Success rate · ${report.period.label.toLowerCase()}', style: TextStyle(color: ZayloColors.onInkMuted)),
              const SizedBox(height: 6),
              Text(rate == null ? '—' : '${rate % 1 == 0 ? rate.toInt() : rate}%',
                  style: TextStyle(color: ZayloColors.white, fontSize: 30, fontWeight: FontWeight.w800)),
              const SizedBox(height: 4),
              Text(
                rate == null
                    ? 'No finished delivery attempts yet.'
                    : '${report.delivered} delivered out of ${report.delivered + report.failedAttempts} attempts',
                style: TextStyle(color: ZayloColors.white),
              ),
            ]),
          ),
          const SizedBox(height: 16),
          _TileRow(tiles: [
            _Tile(icon: Icons.task_alt, label: 'Delivered', value: '${report.delivered}'),
            _Tile(icon: Icons.error_outline, label: 'Failed attempts', value: '${report.failedAttempts}'),
          ]),
          const SizedBox(height: 12),
          _TileRow(tiles: [
            _Tile(icon: Icons.check_circle_outline, label: 'Accepted', value: '${report.accepted}'),
            _Tile(icon: Icons.do_not_disturb_on_outlined, label: 'Declined', value: '${report.declined}'),
          ]),
          const SizedBox(height: 12),
          _TileRow(tiles: [
            _Tile(icon: Icons.payments_outlined, label: 'Cash collected', value: peso(report.cashCollected, cents: false)),
            _Tile(icon: Icons.account_balance_wallet_outlined, label: 'Delivery fees', value: peso(report.deliveryFees, cents: false)),
          ]),
          _ReasonSection(title: 'Why deliveries failed', reasons: report.failedReasons, empty: 'No failed attempts.'),
          _ReasonSection(title: 'Why you declined', reasons: report.declinedReasons, empty: 'No declined assignments.'),
          const SizedBox(height: 22),
          Text('Last 7 days', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 12),
          SectionCard(
            child: Column(children: [
              for (final day in report.lastSevenDays.reversed) _DayRow(day: day),
            ]),
          ),
        ],
      ),
    );
  }
}

class _TileRow extends StatelessWidget {
  const _TileRow({required this.tiles});

  final List<Widget> tiles;

  @override
  Widget build(BuildContext context) => Row(children: [
        Expanded(child: tiles[0]),
        const SizedBox(width: 12),
        Expanded(child: tiles[1]),
      ]);
}

class _Tile extends StatelessWidget {
  const _Tile({required this.icon, required this.label, required this.value});

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Semantics(
        label: '$label: $value',
        excludeSemantics: true,
        child: SectionCard(
          child: Row(children: [
            CircleAvatar(radius: 18, backgroundColor: ZayloColors.sand, child: Icon(icon, size: 18, color: ZayloColors.ink)),
            const SizedBox(width: 10),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(value, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                ),
                Text(label,
                    maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
              ]),
            ),
          ]),
        ),
      );
}

/// How often each reason came up, largest first, as bars.
class _ReasonSection extends StatelessWidget {
  const _ReasonSection({required this.title, required this.reasons, required this.empty});

  final String title;
  final List<RiderReasonCount> reasons;
  final String empty;

  @override
  Widget build(BuildContext context) {
    final most = reasons.fold<int>(0, (max, reason) => reason.count > max ? reason.count : max);
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const SizedBox(height: 22),
      Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
      const SizedBox(height: 12),
      SectionCard(
        child: reasons.isEmpty
            ? Text(empty, style: TextStyle(color: ZayloColors.muted))
            : Column(children: [
                for (final reason in reasons)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Row(children: [
                        Expanded(child: Text(reason.label)),
                        Text('${reason.count}', style: const TextStyle(fontWeight: FontWeight.w700)),
                      ]),
                      const SizedBox(height: 6),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(6),
                        child: LinearProgressIndicator(
                          value: most == 0 ? 0 : reason.count / most,
                          minHeight: 8,
                          backgroundColor: ZayloColors.line,
                          color: ZayloColors.ink,
                          semanticsLabel: '${reason.label}: ${reason.count}',
                        ),
                      ),
                    ]),
                  ),
              ]),
      ),
    ]);
  }
}

class _DayRow extends StatelessWidget {
  const _DayRow({required this.day});

  static const _weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

  final RiderReportDay day;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(children: [
          SizedBox(
            width: 74,
            child: Text('${_weekdays[day.date.weekday - 1]} ${day.date.day}',
                style: const TextStyle(fontWeight: FontWeight.w600)),
          ),
          Expanded(child: Text('${day.delivered} delivered', style: TextStyle(color: ZayloColors.success))),
          Text('${day.failed} failed', style: TextStyle(color: day.failed > 0 ? ZayloColors.danger : ZayloColors.muted)),
        ]),
      );
}
