import 'package:zaylo_mobile/models/rider/rider_dashboard_models.dart';

class RiderAttendanceHistory {
  RiderAttendanceHistory.fromJson(Map<String, dynamic> json)
      : today = RiderAttendance.fromJson(json['today'] as Map<String, dynamic>),
        schedule =
            (json['schedule'] as Map<String, dynamic>)['label'] as String?,
        provider =
            (json['schedule'] as Map<String, dynamic>)['provider'] as String?,
        days = ((json['schedule'] as Map<String, dynamic>)['days'] as List)
            .map((day) => (
                  label: day['label'] as String,
                  working: day['working'] as bool
                ))
            .toList(),
        records = (json['records'] as List)
            .map((record) =>
                RiderAttendanceRecord.fromJson(record as Map<String, dynamic>))
            .toList(),
        nextPage = json['next_page'] as int?;

  final RiderAttendance today;
  final String? schedule;
  final String? provider;
  final List<({String label, bool working})> days;
  final List<RiderAttendanceRecord> records;
  final int? nextPage;
}

class RiderAttendanceRecord {
  RiderAttendanceRecord.fromJson(Map<String, dynamic> json)
      : date = DateTime.parse(json['date'] as String),
        shift = json['shift'] as String?,
        timeIn = DateTime.parse(json['time_in'] as String),
        timeOut = DateTime.tryParse(json['time_out'] as String? ?? ''),
        statusLabel = json['status_label'] as String,
        late = json['late'] as bool,
        leftEarly = json['left_early'] as bool;

  final DateTime date;
  final String? shift;
  final DateTime timeIn;
  final DateTime? timeOut;
  final String statusLabel;
  final bool late;
  final bool leftEarly;
}
