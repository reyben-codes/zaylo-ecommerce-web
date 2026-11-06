import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/components/map/zaylo_map.dart';
import 'package:zaylo_mobile/models/buyer/order_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

/// The parcel on a map: the buyer's delivery pin, and the rider while the parcel is out for delivery. It refreshes
/// as often as the server says while the app is in the foreground, and stops once the parcel is delivered.
class OrderTrackingScreen extends StatefulWidget {
  const OrderTrackingScreen({super.key, required this.orderId, required this.orderNumber, required this.initial});

  final int orderId;
  final String orderNumber;
  final ParcelTracking initial;

  @override
  State<OrderTrackingScreen> createState() => _OrderTrackingScreenState();
}

class _OrderTrackingScreenState extends State<OrderTrackingScreen> {
  static const _finished = {'delivered', 'returned', 'cancelled'};

  final _map = MapController();
  late ParcelTracking _tracking = widget.initial;
  late final AppLifecycleListener _lifecycle;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _lifecycle = AppLifecycleListener(onShow: () {
      _refresh();
      _start();
    }, onHide: _stop);
    _start();
  }

  @override
  void dispose() {
    _stop();
    _lifecycle.dispose();
    super.dispose();
  }

  void _start() {
    _stop();
    if (_finished.contains(_tracking.stage)) return;
    _timer = Timer.periodic(Duration(seconds: _tracking.refreshSeconds), (_) => _refresh());
  }

  void _stop() {
    _timer?.cancel();
    _timer = null;
  }

  Future<void> _refresh() async {
    try {
      final tracking = await AppScope.read(context).api.orderTracking(widget.orderId);
      if (!mounted) return;
      setState(() => _tracking = tracking);
      if (_finished.contains(tracking.stage)) _stop();
    } on ApiException {
      // Keep the last position and try again on the next tick.
    }
  }

  List<LatLng> get _points => [
        if (_tracking.destination != null) _tracking.destination!.latLng,
        if (_tracking.rider != null) _tracking.rider!.point.latLng,
      ];

  void _recenter() {
    final points = _points;
    if (points.length > 1) {
      _map.fitCamera(CameraFit.bounds(bounds: LatLngBounds.fromPoints(points), padding: const EdgeInsets.all(56), maxZoom: 17));
    } else if (points.length == 1) {
      _map.move(points.first, 16);
    }
  }

  @override
  Widget build(BuildContext context) {
    final tracking = _tracking;
    final rider = tracking.rider;
    return Scaffold(
      appBar: AppBar(title: Text('Track ${widget.orderNumber}')),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 4, ZayloSpace.page, 12),
          child: SectionCard(
            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Icon(Icons.local_shipping_outlined, color: ZayloColors.ink),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(tracking.stageLabel, style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
                  const SizedBox(height: 4),
                  Text(tracking.message, style: TextStyle(color: ZayloColors.muted)),
                  if (rider?.updatedAt != null) ...[
                    const SizedBox(height: 4),
                    Text('Rider location updated ${relativeTime(rider!.updatedAt)}',
                        style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
                  ],
                ]),
              ),
            ]),
          ),
        ),
        Expanded(
          child: Stack(children: [
            ZayloMap(
              controller: _map,
              fit: _points,
              zoom: 16,
              markers: [
                if (tracking.destination != null) mapMarker(tracking.destination!.latLng, MapPinKind.home),
                if (rider != null) mapMarker(rider.point.latLng, MapPinKind.rider),
              ],
            ),
            if (_points.isNotEmpty)
              Positioned(
                right: 16,
                bottom: 16,
                child: FloatingActionButton.small(
                  heroTag: null,
                  tooltip: 'Show the whole route',
                  onPressed: _recenter,
                  backgroundColor: ZayloColors.paper,
                  foregroundColor: ZayloColors.ink,
                  child: const Icon(Icons.center_focus_strong),
                ),
              ),
          ]),
        ),
        if (tracking.destination == null)
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.all(ZayloSpace.page),
              child: Text(
                'This order has no pinned location, so the rider uses the written address. Pin your saved addresses for your next orders.',
                style: TextStyle(fontSize: 12, color: ZayloColors.muted),
              ),
            ),
          ),
      ]),
    );
  }
}
