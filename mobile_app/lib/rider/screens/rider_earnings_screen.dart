import 'package:flutter/material.dart';

import '../../core/theme/zaylo_theme.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/feedback.dart';
import '../../core/widgets/layout.dart';
import '../api/rider_api.dart';
import '../models/rider_activity_models.dart';

/// Earnings from delivery fees: today, this week, this month, all time, and
/// a day-by-day view of the last 7 days. Opened from the "Earned today" tile.
class RiderEarningsScreen extends StatefulWidget {
  const RiderEarningsScreen({super.key, required this.api, this.onOpenHistory});

  final RiderApi api;

  /// Opens the History tab for a period, so the rider can see which deliveries earned it.
  final ValueChanged<RiderPeriod>? onOpenHistory;

  @override
  State<RiderEarningsScreen> createState() => _RiderEarningsScreenState();
}

class _RiderEarningsScreenState extends State<RiderEarningsScreen> {
  late Future<RiderEarnings> _earnings = widget.api.earnings();

  Future<void> _reload() async {
    final earnings = widget.api.earnings();
    setState(() {
      _earnings = earnings;
    });
    try {
      await earnings;
    } on Exception {
      // Shown by the FutureBuilder's error state.
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: ZayloColors.white,
        appBar: AppBar(backgroundColor: ZayloColors.white, title: const Text('Earnings')),
        body: FutureBuilder<RiderEarnings>(
          future: _earnings,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done && !snapshot.hasData) return const Loader();
            if (snapshot.hasError) {
              return LoadError(error: snapshot.error, onRetry: _reload, title: 'Could not load your earnings');
            }
            final data = snapshot.requireData;
            final today = data.periods[RiderPeriod.today]!;
            final best = data.lastSevenDays.fold<double>(0, (max, day) => day.totals.earnings > max ? day.totals.earnings : max);
            return RefreshIndicator(
              color: ZayloColors.ink,
              onRefresh: _reload,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
                children: [
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(color: ZayloColors.ink, borderRadius: BorderRadius.circular(18)),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      const Text('Earned today', style: TextStyle(color: Color(0xFFD7CCC0))),
                      const SizedBox(height: 6),
                      Text(peso(today.earnings),
                          style: const TextStyle(color: ZayloColors.white, fontSize: 30, fontWeight: FontWeight.w800)),
                      const SizedBox(height: 4),
                      Text('${today.deliveries} deliver${today.deliveries == 1 ? 'y' : 'ies'} completed',
                          style: const TextStyle(color: ZayloColors.white)),
                    ]),
                  ),
                  const SizedBox(height: 16),
                  for (final period in [RiderPeriod.week, RiderPeriod.month, RiderPeriod.all])
                    Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: SectionCard(
                        padding: EdgeInsets.zero,
                        child: ListTile(
                          title: Text(period.label, style: const TextStyle(fontWeight: FontWeight.w600)),
                          subtitle: Text('${data.periods[period]!.deliveries} deliveries'),
                          trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                            Text(peso(data.periods[period]!.earnings), style: const TextStyle(fontWeight: FontWeight.w800)),
                            if (widget.onOpenHistory != null) const Icon(Icons.chevron_right),
                          ]),
                          onTap: widget.onOpenHistory == null ? null : () => widget.onOpenHistory!(period),
                        ),
                      ),
                    ),
                  const SizedBox(height: 14),
                  Text('Last 7 days', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 12),
                  SectionCard(
                    child: Column(children: [
                      for (final day in data.lastSevenDays.reversed) _DayBar(day: day, best: best),
                    ]),
                  ),
                  if (data.note != null) ...[
                    const SizedBox(height: 14),
                    Text(data.note!, style: const TextStyle(fontSize: 12, color: ZayloColors.muted)),
                  ],
                ],
              ),
            );
          },
        ),
      );
}

class _DayBar extends StatelessWidget {
  const _DayBar({required this.day, required this.best});

  static const _weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

  final RiderEarningsDay day;
  final double best;

  @override
  Widget build(BuildContext context) {
    final share = best == 0 ? 0.0 : day.totals.earnings / best;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(children: [
        SizedBox(
          width: 74,
          child: Text('${_weekdays[day.date.weekday - 1]} ${day.date.day}',
              style: const TextStyle(fontWeight: FontWeight.w600)),
        ),
        Expanded(
          child: ClipRRect(
            borderRadius: BorderRadius.circular(6),
            child: LinearProgressIndicator(
              value: share,
              minHeight: 10,
              backgroundColor: ZayloColors.line,
              color: ZayloColors.ink,
              semanticsLabel: 'Earnings on ${shortDate(day.date)}',
            ),
          ),
        ),
        const SizedBox(width: 12),
        SizedBox(
          width: 72,
          child: Text(peso(day.totals.earnings, cents: false),
              textAlign: TextAlign.right, style: const TextStyle(fontWeight: FontWeight.w700)),
        ),
      ]),
    );
  }
}
