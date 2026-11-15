import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/components/map/road_route.dart';
import 'package:zaylo_mobile/components/map/zaylo_map.dart';

void main() {
  const origin = LatLng(14.22, 121.15);
  const destination = LatLng(14.21, 121.16);
  const detour = LatLng(14.23, 121.18);
  final originalFetch = RoadRouting.fetch;

  setUp(() => ZayloMap.showTiles = false);
  tearDown(() {
    RoadRouting.fetch = originalFetch;
    ZayloMap.showTiles = true;
  });

  Widget map({LatLng? from = origin}) => MaterialApp(
        home: Scaffold(body: ZayloMap(routeFrom: from, routeTo: destination)),
      );

  test('GeoJSON coordinates become latitude/longitude in road order', () {
    final points = RoadRouting.decode({
      'code': 'Ok',
      'routes': [
        {'geometry': {'coordinates': [[121.15, 14.22], [121.18, 14.23], [121.16, 14.21]]}},
      ],
    });
    expect(points, [origin, detour, destination]);
    expect(() => RoadRouting.decode({'code': 'NoRoute', 'routes': []}), throwsFormatException);
  });

  testWidgets('draws road bends and removes the route when the rider disappears', (tester) async {
    RoadRouting.fetch = (from, to) async => [origin, detour, destination];
    await tester.pumpWidget(map());
    await tester.pump();
    expect(tester.widget<PolylineLayer>(find.byType(PolylineLayer)).polylines.single.points,
        [origin, detour, destination]);
    expect(find.text('Blue road: suggested route'), findsOneWidget);
    await tester.pumpWidget(map(from: null));
    expect(find.byType(PolylineLayer), findsNothing);
  });

  testWidgets('a late response cannot restore a route after tracking ends', (tester) async {
    final response = Completer<List<LatLng>>();
    RoadRouting.fetch = (from, to) => response.future;
    await tester.pumpWidget(map());
    await tester.pumpWidget(map(from: null));
    response.complete([origin, detour, destination]);
    await tester.pump();
    expect(find.byType(PolylineLayer), findsNothing);
  });

  testWidgets('failed routes can be retried without moving the rider', (tester) async {
    var calls = 0;
    RoadRouting.fetch = (from, to) async {
      if (++calls == 1) throw const FormatException('No route');
      return [origin, detour, destination];
    };
    await tester.pumpWidget(map());
    await tester.pump();
    expect(find.byType(PolylineLayer), findsNothing);
    await tester.tap(find.text('Road route unavailable · Retry'));
    await tester.pump();
    expect(find.byType(PolylineLayer), findsOneWidget);
    expect(calls, 2);
  });
}
