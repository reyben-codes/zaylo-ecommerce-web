import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';

import 'package:zaylo_mobile/models/map_point.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

export 'package:latlong2/latlong.dart' show LatLng;
export 'package:zaylo_mobile/models/map_point.dart';

enum MapPinKind { home, rider, me }

/// The buyer's delivery pin, the rider, or the person holding the phone.
Marker mapMarker(LatLng point, MapPinKind kind) => Marker(
      point: point,
      width: 44,
      height: 44,
      // The home pin's tip marks the spot; the round markers are centred on it.
      alignment: kind == MapPinKind.home ? Alignment.topCenter : Alignment.center,
      child: switch (kind) {
        MapPinKind.home => Icon(Icons.location_on, size: 44, color: ZayloColors.ink, semanticLabel: 'Delivery pin'),
        MapPinKind.rider || MapPinKind.me => Center(
            child: Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: kind == MapPinKind.rider ? ZayloColors.bronze : const Color(0xFF2F6FDE),
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white, width: 3),
                boxShadow: const [BoxShadow(color: Color(0x55000000), blurRadius: 6)],
              ),
              child: Icon(kind == MapPinKind.rider ? Icons.two_wheeler : Icons.person, size: 17, color: Colors.white,
                  semanticLabel: kind == MapPinKind.rider ? 'Rider' : 'You'),
            ),
          ),
      },
    );

/// An OpenStreetMap map with ZAYLO's markers. Rotation is off so north stays up.
class ZayloMap extends StatelessWidget {
  const ZayloMap({
    super.key,
    this.markers = const [],
    this.controller,
    this.center,
    this.zoom = 16,
    this.fit,
    this.onTap,
  });

  /// Widget tests turn this off: they cannot load map tiles.
  static bool showTiles = true;

  /// The middle of the Philippines, for maps with nothing to show yet.
  static const philippines = LatLng(12.8797, 121.774);

  final List<Marker> markers;
  final MapController? controller;
  final LatLng? center;
  final double zoom;

  /// Frames these points when there are two or more, instead of [center].
  final List<LatLng>? fit;
  final void Function(LatLng point)? onTap;

  @override
  Widget build(BuildContext context) {
    final points = fit ?? const [];
    return FlutterMap(
      mapController: controller,
      options: MapOptions(
        initialCenter: center ?? (points.isNotEmpty ? points.first : philippines),
        initialZoom: center == null && points.isEmpty ? 5.5 : zoom,
        initialCameraFit: points.length > 1
            ? CameraFit.bounds(bounds: LatLngBounds.fromPoints(points), padding: const EdgeInsets.all(56), maxZoom: 17)
            : null,
        interactionOptions: const InteractionOptions(flags: InteractiveFlag.all & ~InteractiveFlag.rotate),
        onTap: onTap == null ? null : (_, point) => onTap!(point),
      ),
      children: [
        if (showTiles)
          TileLayer(
            urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            userAgentPackageName: 'com.example.zaylo_mobile',
          ),
        MarkerLayer(markers: markers),
        // OpenStreetMap's required credit, kept small so it never crowds a narrow map.
        Align(
          alignment: Alignment.bottomLeft,
          child: Padding(
            padding: const EdgeInsets.all(6),
            child: Semantics(
              link: true,
              label: 'Map data from OpenStreetMap contributors',
              excludeSemantics: true,
              child: GestureDetector(
                onTap: () => launchUrl(Uri.parse('https://www.openstreetmap.org/copyright'), mode: LaunchMode.externalApplication),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  color: const Color(0xCCFFFFFF),
                  child: const Text('© OpenStreetMap', maxLines: 1, overflow: TextOverflow.ellipsis,
                      style: TextStyle(fontSize: 10, color: Color(0xFF333333))),
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

/// Why the phone's location could not be read, in words for the person.
class LocationUnavailable implements Exception {
  const LocationUnavailable(this.message);

  final String message;

  @override
  String toString() => message;
}

/// The phone's current location. Tests replace [current].
class DeviceLocation {
  /// With [ask] false, a missing permission is not requested again (so a background reporter never nags).
  static Future<LatLng> Function({bool ask}) current = _current;

  static Future<LatLng> _current({bool ask = true}) async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw const LocationUnavailable('Turn on your phone\'s location to use this.');
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied && ask) permission = await Geolocator.requestPermission();
    if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
      throw const LocationUnavailable('Allow ZAYLO to use your location in your phone\'s settings.');
    }
    final position = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 20)),
    );
    return LatLng(position.latitude, position.longitude);
  }
}

/// Opens turn-by-turn directions to [to] in Google Maps (or the browser when it is not installed).
Future<bool> openDirections(MapPoint to) => launchUrl(
      Uri.https('www.google.com', '/maps/dir/', {
        'api': '1',
        'destination': '${to.latitude},${to.longitude}',
        'travelmode': 'driving',
      }),
      mode: LaunchMode.externalApplication,
    );
