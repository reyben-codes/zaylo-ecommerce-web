import 'dart:async';

import 'package:flutter/widgets.dart';

import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/map/zaylo_map.dart';
import 'package:zaylo_mobile/models/rider/rider_dashboard_models.dart';

/// Shares the rider's location while the app is open and the rider is on shift or has a parcel out for delivery, so
/// the buyer can follow it and logistics can offer parcels to the nearest riders.
/// It reports as often as the dashboard says, pauses while the app is in the background, and asks for the
/// location permission once; after a refusal it stays quiet until the rider allows it in settings.
class RiderLocationReporter {
  RiderLocationReporter({required this.api}) {
    _lifecycle = AppLifecycleListener(onHide: _pause, onShow: _resume);
  }

  final RiderApi api;
  late final AppLifecycleListener _lifecycle;
  Timer? _timer;
  Duration? _every;
  bool _asked = false;
  bool _sending = false;

  /// Whether reports are being sent now.
  bool get isSharing => _timer != null;

  /// Starts or stops sharing to match the latest dashboard.
  void follow(RiderDashboard dashboard) {
    final seconds = dashboard.locationReportSeconds;
    final onTheWay = dashboard.active.any((delivery) => delivery.status == 'out_for_delivery');
    // Also while timed in at the hub, so logistics can give parcels to the riders nearest them.
    final onShift = dashboard.attendance?.status == 'on_shift';
    if (seconds == null || !(onTheWay || onShift)) {
      _every = null;
      _pause();
      return;
    }
    final every = Duration(seconds: seconds);
    if (_timer != null && every == _every) return;
    _every = every;
    _resume();
  }

  void _resume() {
    final every = _every;
    if (every == null) return;
    _timer?.cancel();
    _timer = Timer.periodic(every, (_) => _report());
    _report();
  }

  void _pause() {
    _timer?.cancel();
    _timer = null;
  }

  Future<void> _report() async {
    if (_sending) return;
    _sending = true;
    try {
      final here = await DeviceLocation.current(ask: !_asked);
      await api.reportLocation(here.latitude, here.longitude);
    } on Exception {
      // No permission, no signal, or offline: the buyer keeps the last position; try again next time.
    } finally {
      _asked = true;
      _sending = false;
    }
  }

  void dispose() {
    _pause();
    _lifecycle.dispose();
  }
}
