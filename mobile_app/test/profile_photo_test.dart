import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:zaylo_mobile/components/profile_photo.dart';
import 'package:zaylo_mobile/components/profile_photo_crop_screen.dart';
import 'package:zaylo_mobile/models/auth_models.dart';
import 'package:zaylo_mobile/models/rider/rider_dashboard_models.dart';

void main() {
  test('accounts carry their profile photo URL', () {
    final user = UserProfile.fromJson({'id': 1, 'name': 'Ana Cruz', 'email': 'ana@example.com', 'avatar_url': 'https://zaylo.test/a.webp'});
    expect(user.avatarUrl, 'https://zaylo.test/a.webp');
    expect(user.withAvatar(null).avatarUrl, isNull);
    expect(user.withAvatar(null).email, 'ana@example.com');
    expect(RiderProfile.fromJson({'name': 'Jun', 'avatar_url': 'https://zaylo.test/r.webp'}).avatarUrl, 'https://zaylo.test/r.webp');
    expect(RiderProfile.fromJson({'name': 'Jun'}).avatarUrl, isNull);
  });

  testWidgets('without a photo the avatar shows initials', (tester) async {
    await tester.pumpWidget(const MaterialApp(home: Center(child: ProfileAvatar(name: 'Ana Maria  Cruz'))));
    expect(find.text('AC'), findsOneWidget);
    expect(find.byType(Image), findsNothing);
    expect(ProfileAvatar.initialsOf('  '), '?');
  });

  testWidgets('the crop screen saves exactly the square the user framed', (tester) async {
    final photo = (await tester.runAsync(_halves))!;
    File? result;
    var popped = false;
    await tester.pumpWidget(MaterialApp(
      home: Builder(
        builder: (context) => TextButton(
          onPressed: () async {
            result = await Navigator.of(context).push<File>(
                MaterialPageRoute(builder: (_) => ProfilePhotoCropScreen(file: photo)));
            popped = true;
          },
          child: const Text('open'),
        ),
      ),
    ));
    await tester.tap(find.text('open'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));
    // Decoding happens outside the fake clock.
    final stage = find.byWidgetPredicate((widget) => widget is CustomPaint && widget.painter.runtimeType.toString() == '_CropPainter');
    for (var i = 0; i < 100 && stage.evaluate().isEmpty; i++) {
      await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 20)));
      await tester.pump();
    }
    expect(stage, findsOneWidget);

    // Drag the photo far to the left: the circle ends up on the right (blue) half.
    await tester.drag(stage, const Offset(-600, 0));
    await tester.pump();

    await tester.tap(find.text('Use photo'));
    for (var i = 0; i < 40 && !popped; i++) {
      await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 20)));
      await tester.pump();
    }
    await tester.pump(const Duration(milliseconds: 500));
    expect(result, isNotNull);
    // The whole 200px-high photo fits the circle at no zoom, so the square is 200 x 200 and all blue.
    expect(await tester.runAsync(() => _pixel(result!, 100, 100)), [200, 200, 0, 0, 255]);
    expect(await tester.runAsync(() => _pixel(result!, 2, 198)), [200, 200, 0, 0, 255]);
    await tester.runAsync(() => _deleteQuietly([photo, result!]));
  });

  testWidgets('cancelling the crop returns nothing', (tester) async {
    final photo = (await tester.runAsync(_halves))!;
    File? result;
    var popped = false;
    await tester.pumpWidget(MaterialApp(
      home: Builder(
        builder: (context) => TextButton(
          onPressed: () async {
            result = await Navigator.of(context).push<File>(
                MaterialPageRoute(builder: (_) => ProfilePhotoCropScreen(file: photo)));
            popped = true;
          },
          child: const Text('open'),
        ),
      ),
    ));
    await tester.tap(find.text('open'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));
    await tester.tap(find.text('Cancel'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));
    expect(popped, isTrue);
    expect(result, isNull);
    await tester.runAsync(() => _deleteQuietly([photo]));
  });
}

// Windows may still hold a file the screen was reading; leftovers in the temp folder are harmless.
Future<void> _deleteQuietly(List<File> files) async {
  for (final file in files) {
    try {
      await file.delete();
    } on FileSystemException {
      // ignore
    }
  }
}

/// A 400 x 200 PNG: left half red, right half blue.
Future<File> _halves() async {
  final recorder = ui.PictureRecorder();
  Canvas(recorder)
    ..drawRect(const Rect.fromLTWH(0, 0, 200, 200), Paint()..color = const Color(0xFFFF0000))
    ..drawRect(const Rect.fromLTWH(200, 0, 200, 200), Paint()..color = const Color(0xFF0000FF));
  final image = await recorder.endRecording().toImage(400, 200);
  final png = await image.toByteData(format: ui.ImageByteFormat.png);
  final file = File('${Directory.systemTemp.path}/zaylo-halves-${DateTime.now().microsecondsSinceEpoch}.png');
  return file.writeAsBytes(png!.buffer.asUint8List());
}

Future<List<int>> _pixel(File file, int x, int y) async {
  final codec = await ui.instantiateImageCodec(await file.readAsBytes());
  final image = (await codec.getNextFrame()).image;
  final data = (await image.toByteData(format: ui.ImageByteFormat.rawRgba))!;
  final i = (y * image.width + x) * 4;
  return [image.width, image.height, data.getUint8(i), data.getUint8(i + 1), data.getUint8(i + 2)];
}
