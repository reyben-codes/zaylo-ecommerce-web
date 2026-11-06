import 'package:latlong2/latlong.dart';

/// A spot on the map, as the API sends it: `{latitude, longitude}`.
class MapPoint {
  const MapPoint(this.latitude, this.longitude);

  final double latitude;
  final double longitude;

  LatLng get latLng => LatLng(latitude, longitude);

  static MapPoint? fromJson(Object? json) => switch (json) {
        {'latitude': final num latitude, 'longitude': final num longitude} => MapPoint(latitude.toDouble(), longitude.toDouble()),
        _ => null,
      };

  factory MapPoint.from(LatLng point) =>
      MapPoint(double.parse(point.latitude.toStringAsFixed(7)), double.parse(point.longitude.toStringAsFixed(7)));

  @override
  bool operator ==(Object other) => other is MapPoint && other.latitude == latitude && other.longitude == longitude;

  @override
  int get hashCode => Object.hash(latitude, longitude);
}
