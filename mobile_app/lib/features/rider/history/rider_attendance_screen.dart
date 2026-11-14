import 'package:flutter/material.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/models/rider/rider_attendance_history.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

class RiderAttendanceScreen extends StatefulWidget {
  const RiderAttendanceScreen({super.key, required this.api});
  final RiderApi api;

  @override
  State<RiderAttendanceScreen> createState() => _RiderAttendanceScreenState();
}

class _RiderAttendanceScreenState extends State<RiderAttendanceScreen> {
  RiderAttendanceHistory? _data;
  final _records = <RiderAttendanceRecord>[];
  DateTimeRange? _range;
  int? _nextPage = 1;
  bool _loading = false;
  Object? _error;
  int _generation = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _reload() async {
    _generation++;
    setState(() {
      _records.clear();
      _nextPage = 1;
      _loading = false;
      _error = null;
    });
    await _load();
  }

  Future<void> _load() async {
    final page = _nextPage;
    if (_loading || page == null) return;
    final generation = ++_generation;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await widget.api
          .attendanceHistory(page: page, from: _range?.start, to: _range?.end);
      if (!mounted || generation != _generation) return;
      setState(() {
        _data = data;
        _records.addAll(data.records);
        _nextPage = data.nextPage;
      });
    } catch (error) {
      if (mounted && generation == _generation) setState(() => _error = error);
    } finally {
      if (mounted && generation == _generation) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _pickDates() async {
    final now = DateTime.now();
    final range = await showDateRangePicker(
      context: context,
      firstDate: DateTime(now.year - 3),
      lastDate: now,
      initialDateRange: _range,
      helpText: 'Attendance between',
    );
    if (!mounted || range == null) return;
    setState(() => _range = range);
    await _reload();
  }

  // Attendance always uses Philippine time, including on phones in another timezone.
  String _time(DateTime? value) => value == null
      ? 'Not recorded'
      : dateTimeLabel(value.toUtc().add(const Duration(hours: 8)));

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Attendance & schedule')),
        body: RefreshIndicator(
          onRefresh: _reload,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(20),
            children: [
              if (_data case final data?) ...[
                SectionCard(
                    child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                      Text('Current work schedule',
                          style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 8),
                      Text(data.schedule ?? 'No schedule set yet',
                          style: const TextStyle(fontWeight: FontWeight.w700)),
                      const SizedBox(height: 6),
                      Text(data.schedule == null
                          ? 'Your logistics partner will set your work days and shift.'
                          : 'Set by ${data.provider ?? 'your logistics partner'}. All times are Philippine time.'),
                      if (data.schedule != null) ...[
                        const SizedBox(height: 12),
                        Wrap(spacing: 6, runSpacing: 6, children: [
                          for (final day in data.days)
                            Chip(
                              avatar: Icon(
                                  day.working ? Icons.check : Icons.remove,
                                  size: 16),
                              label: Text(day.label),
                              backgroundColor:
                                  day.working ? ZayloColors.sand : null,
                            ),
                        ]),
                      ],
                      const SizedBox(height: 10),
                      Text('Today: ${data.today.statusLabel}'),
                    ])),
                const SizedBox(height: 24),
              ],
              Text('Attendance history',
                  style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              Wrap(spacing: 8, children: [
                ActionChip(
                  avatar: const Icon(Icons.date_range, size: 18),
                  label: Text(_range == null
                      ? 'Filter dates'
                      : '${shortDate(_range!.start)} – ${shortDate(_range!.end)}'),
                  onPressed: _pickDates,
                ),
                if (_range != null)
                  ActionChip(
                      label: const Text('Clear dates'),
                      onPressed: () {
                        setState(() => _range = null);
                        _reload();
                      }),
              ]),
              const Text(
                  'Recorded scans and the scheduled shift at time in. All times are Philippine time.'),
              const SizedBox(height: 12),
              for (final record in _records)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: SectionCard(
                      child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                        Text(shortDate(record.date),
                            style:
                                const TextStyle(fontWeight: FontWeight.w800)),
                        const SizedBox(height: 6),
                        Text([
                          record.statusLabel,
                          if (record.late) 'Late',
                          if (record.leftEarly) 'Left early'
                        ].join(' · ')),
                        Text(
                            'Scheduled shift: ${record.shift ?? 'No schedule recorded'}'),
                        const SizedBox(height: 8),
                        Text('Time in: ${_time(record.timeIn)}'),
                        Text('Time out: ${_time(record.timeOut)}'),
                      ])),
                ),
              if (_error != null)
                LoadError(
                    error: _error,
                    onRetry: _load,
                    title: 'Could not load attendance')
              else if (!_loading && _records.isEmpty)
                const SectionCard(
                    child: Text('No attendance records for these dates.')),
              if (_loading)
                const Padding(
                    padding: EdgeInsets.all(20),
                    child: Center(child: CircularProgressIndicator()))
              else if (_nextPage != null && _error == null)
                OutlinedButton(
                    onPressed: _load, child: const Text('Load more')),
            ],
          ),
        ),
      );
}
