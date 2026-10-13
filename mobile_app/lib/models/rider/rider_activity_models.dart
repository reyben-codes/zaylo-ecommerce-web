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

/// A message in the rider's chat with ZAYLO support.
class RiderSupportMessage {
  const RiderSupportMessage({
    required this.clientId,
    required this.body,
    required this.fromStaff,
    this.id,
    this.sentAt,
    this.pending = false,
    this.failed = false,
  });

  /// Null until the server has stored it.
  final int? id;

  /// Chosen by the app so a retried send is stored only once.
  final String clientId;
  final String body;
  final bool fromStaff;
  final DateTime? sentAt;

  /// Shown immediately while sending, then replaced by the server copy.
  final bool pending;
  final bool failed;

  RiderSupportMessage copyWith({bool? pending, bool? failed}) => RiderSupportMessage(
        id: id,
        clientId: clientId,
        body: body,
        fromStaff: fromStaff,
        sentAt: sentAt,
        pending: pending ?? this.pending,
        failed: failed ?? this.failed,
      );

  factory RiderSupportMessage.fromJson(Map<String, dynamic> json) => RiderSupportMessage(
        id: json['id'] as int?,
        clientId: json['client_id'] as String? ?? '',
        body: json['body'] as String? ?? '',
        fromStaff: json['from_staff'] as bool? ?? false,
        sentAt: json['sent_at'] is String ? DateTime.tryParse(json['sent_at'] as String)?.toLocal() : null,
      );
}
