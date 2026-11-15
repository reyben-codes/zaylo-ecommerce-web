import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

/// Road geometry from OSRM, in travel order. No straight-line fallback.
class RoadRouting {
  static Future<List<LatLng>> Function(LatLng from, LatLng to) fetch = _fetch;

  static List<LatLng> decode(Object? json) {
    if (json is! Map || json['code'] != 'Ok') throw const FormatException('No road route');
    final routes = json['routes'];
    if (routes is! List || routes.isEmpty) throw const FormatException('No road route');
    final coordinates = routes.first['geometry']['coordinates'] as List;
    final points = coordinates.map((value) {
      final lat = (value[1] as num).toDouble();
      final lng = (value[0] as num).toDouble();
      if (!lat.isFinite || !lng.isFinite || lat.abs() > 90 || lng.abs() > 180) {
        throw const FormatException('Invalid route point');
      }
      return LatLng(lat, lng);
    }).toList();
    if (points.length < 2) throw const FormatException('No road route');
    return points;
  }

  static Future<List<LatLng>> _fetch(LatLng from, LatLng to) async {
    const base = String.fromEnvironment('ZAYLO_ROUTING_URL', defaultValue: 'https://router.project-osrm.org');
    final client = HttpClient()..connectionTimeout = const Duration(seconds: 8);
    try {
      return await (() async {
        final url = Uri.parse('${base.replaceFirst(RegExp(r'/$'), '')}/route/v1/driving/'
            '${from.longitude},${from.latitude};${to.longitude},${to.latitude}'
            '?overview=full&geometries=geojson&steps=false');
        final request = await client.getUrl(url);
        final response = await request.close();
        if (response.statusCode != 200) throw const HttpException('Road route unavailable');
        return decode(jsonDecode(await response.transform(utf8.decoder).join()));
      })().timeout(const Duration(seconds: 10));
    } finally {
      client.close(force: true);
    }
  }
}

/// Lives below markers inside FlutterMap. Ignores responses for superseded endpoints.
class RoadRouteLayer extends StatefulWidget {
  const RoadRouteLayer({super.key, required this.from, required this.to, this.onRoute});

  final LatLng? from;
  final LatLng? to;
  final ValueChanged<List<LatLng>>? onRoute;

  @override
  State<RoadRouteLayer> createState() => _RoadRouteLayerState();
}

class _RoadRouteLayerState extends State<RoadRouteLayer> {
  List<LatLng> _points = [];
  int _request = 0;
  bool _loading = false;
  bool _failed = false;
  bool _fitted = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void didUpdateWidget(RoadRouteLayer oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.from != widget.from || oldWidget.to != widget.to) {
      if (oldWidget.to != widget.to) {
        _points = [];
        _fitted = false;
      }
      _load();
    }
  }

  Future<void> _load() async {
    final request = ++_request;
    final from = widget.from;
    final to = widget.to;
    _loading = from != null && to != null;
    _failed = false;
    if (!_loading) {
      _points = [];
      _fitted = false;
      // Notify after build, since a parent may store route points for recentering.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && request == _request) widget.onRoute?.call([]);
      });
      return;
    }
    try {
      final points = await RoadRouting.fetch(from!, to!);
      if (!mounted || request != _request) return;
      setState(() {
        _points = points;
        _loading = false;
      });
      widget.onRoute?.call(points);
      if (!_fitted) {
        MapController.of(context).fitCamera(CameraFit.bounds(
          bounds: LatLngBounds.fromPoints([from, ...points, to]),
          padding: const EdgeInsets.all(40),
          maxZoom: 17,
        ));
        _fitted = true;
      }
    } catch (_) {
      if (!mounted || request != _request) return;
      setState(() {
        _points = [];
        _loading = false;
        _failed = true;
      });
      widget.onRoute?.call([]);
    }
  }

  @override
  Widget build(BuildContext context) => Stack(children: [
        if (_points.isNotEmpty)
          PolylineLayer(polylines: [
            Polyline(points: _points, strokeWidth: 6, color: const Color(0xFF2563EB),
                borderStrokeWidth: 2, borderColor: Colors.white),
          ]),
        if (widget.from != null && widget.to != null)
          Align(
            alignment: Alignment.topCenter,
            child: Padding(
              padding: const EdgeInsets.all(8),
              child: Material(
                color: Colors.white,
                borderRadius: BorderRadius.circular(8),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  child: _failed
                      ? TextButton(onPressed: () { _load(); setState(() {}); },
                          child: const Text('Road route unavailable · Retry'))
                      : Text(_loading ? 'Loading road route…' : 'Blue road: suggested route',
                          style: const TextStyle(fontSize: 12, color: Color(0xFF2563EB))),
                ),
              ),
            ),
          ),
      ]);
}
