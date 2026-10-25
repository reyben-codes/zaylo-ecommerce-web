/// History, earnings, and support-chat shapes from the rider API.
library;

import 'package:zaylo_mobile/models/rider/rider_dashboard_models.dart';

double _money(Object? value) => (value as num?)?.toDouble() ?? 0;

/// Filters shared by the History and Earnings screens; values match the API.
enum RiderPeriod {
  today('today', 'Today'),
  week('week', 'This week'),
  month('month', 'This month'),
  all('all', 'All time');

  const RiderPeriod(this.value, this.label);

  final String value;
  final String label;
}

class RiderTotals {
  const RiderTotals({this.deliveries = 0, this.earnings = 0});

  final int deliveries;
  final double earnings;

  factory RiderTotals.fromJson(Map<String, dynamic>? json) => RiderTotals(
        deliveries: json?['deliveries'] as int? ?? 0,
        earnings: _money(json?['earnings']),
      );
}

/// One page of GET /mobile/rider/history.
class RiderHistoryPage {
  const RiderHistoryPage({required this.summary, required this.deliveries, this.nextPage});

  /// Totals for the whole period, not just this page.
  final RiderTotals summary;
  final List<RiderDelivery> deliveries;
  final int? nextPage;

  factory RiderHistoryPage.fromJson(Map<String, dynamic> json) => RiderHistoryPage(
        summary: RiderTotals.fromJson(json['summary'] as Map<String, dynamic>?),
        deliveries: (json['deliveries'] as List<dynamic>? ?? const [])
            .map((item) => RiderDelivery.fromJson(item as Map<String, dynamic>))
            .toList(),
        nextPage: json['next_page'] as int?,
      );
}

class RiderEarningsDay {
  const RiderEarningsDay({required this.date, required this.totals});

  final DateTime date;
  final RiderTotals totals;
}

/// GET /mobile/rider/earnings.
class RiderEarnings {
  const RiderEarnings({required this.periods, required this.lastSevenDays, this.note});

  final Map<RiderPeriod, RiderTotals> periods;
  final List<RiderEarningsDay> lastSevenDays;
  final String? note;

  factory RiderEarnings.fromJson(Map<String, dynamic> json) {
    final periods = json['periods'] as Map<String, dynamic>? ?? const {};
    return RiderEarnings(
      periods: {
        for (final period in RiderPeriod.values)
          period: RiderTotals.fromJson(periods[period.value] as Map<String, dynamic>?),
      },
      lastSevenDays: (json['last_7_days'] as List<dynamic>? ?? const [])
          .map((item) => item as Map<String, dynamic>)
          .map((day) => RiderEarningsDay(
                date: DateTime.parse(day['date'] as String),
                totals: RiderTotals.fromJson(day),
              ))
          .toList(),
      note: json['note'] as String?,
    );
  }
}

/// How many times one reason came up in a period.
class RiderReasonCount {
  const RiderReasonCount({required this.label, required this.count});

  final String label;
  final int count;

  static List<RiderReasonCount> listFrom(Object? json) => (json as List<dynamic>? ?? const [])
      .map((item) => item as Map<String, dynamic>)
      .map((item) => RiderReasonCount(label: item['label'] as String? ?? 'Other reason', count: item['count'] as int? ?? 0))
      .toList();
}

class RiderReportDay {
  const RiderReportDay({required this.date, this.delivered = 0, this.failed = 0});

  final DateTime date;
  final int delivered;
  final int failed;
}

/// GET /mobile/rider/reports: what the rider did in a period.
class RiderReport {
  const RiderReport({
    required this.period,
    this.accepted = 0,
    this.declined = 0,
    this.delivered = 0,
    this.failedAttempts = 0,
    this.successRate,
    this.cashCollected = 0,
    this.deliveryFees = 0,
    this.failedReasons = const [],
    this.declinedReasons = const [],
    this.lastSevenDays = const [],
  });

  final RiderPeriod period;
  final int accepted;
  final int declined;
  final int delivered;
  final int failedAttempts;

  /// Delivered parcels out of every attempt that ended, as a percentage; null with no attempts.
  final double? successRate;
  final double cashCollected;
  final double deliveryFees;
  final List<RiderReasonCount> failedReasons;
  final List<RiderReasonCount> declinedReasons;
  final List<RiderReportDay> lastSevenDays;

  factory RiderReport.fromJson(Map<String, dynamic> json) {
    final assignments = json['assignments'] as Map<String, dynamic>? ?? const {};
    final deliveries = json['deliveries'] as Map<String, dynamic>? ?? const {};
    final money = json['money'] as Map<String, dynamic>? ?? const {};
    return RiderReport(
      period: RiderPeriod.values.firstWhere((period) => period.value == json['period'], orElse: () => RiderPeriod.week),
      accepted: assignments['accepted'] as int? ?? 0,
      declined: assignments['declined'] as int? ?? 0,
      delivered: deliveries['delivered'] as int? ?? 0,
      failedAttempts: deliveries['failed_attempts'] as int? ?? 0,
      successRate: (deliveries['success_rate'] as num?)?.toDouble(),
      cashCollected: _money(money['cash_collected']),
      deliveryFees: _money(money['delivery_fees']),
      failedReasons: RiderReasonCount.listFrom(json['failed_reasons']),
      declinedReasons: RiderReasonCount.listFrom(json['declined_reasons']),
      lastSevenDays: (json['last_7_days'] as List<dynamic>? ?? const [])
          .map((item) => item as Map<String, dynamic>)
          .map((day) => RiderReportDay(
                date: DateTime.parse(day['date'] as String),
                delivered: day['delivered'] as int? ?? 0,
                failed: day['failed'] as int? ?? 0,
              ))
          .toList(),
    );
  }
}
