import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:zaylo_mobile/components/cart_flight.dart';

void main() {
  Widget app({bool reducedMotion = false, required void Function(BuildContext button) onAdd}) => MaterialApp(
        home: MediaQuery(
          data: MediaQueryData(disableAnimations: reducedMotion),
          child: Scaffold(
            appBar: AppBar(actions: const [CartTarget(child: Icon(Icons.shopping_bag_outlined))]),
            body: Center(
              child: Builder(
                builder: (button) => ElevatedButton(onPressed: () => onAdd(button), child: const Text('Add')),
              ),
            ),
          ),
        ),
      );

  ScaleTransition bagScale(WidgetTester tester) => tester.widget<ScaleTransition>(
      find.ancestor(of: find.byIcon(Icons.shopping_bag_outlined), matching: find.byType(ScaleTransition)).first);

  testWidgets('the product photo flies into the bag, which pops when it lands', (tester) async {
    var landed = false;
    await tester.pumpWidget(app(
      onAdd: (button) => CartFlight.fly(button, from: CartFlight.rectOf(button), imageUrl: null).then((_) => landed = true),
    ));

    await tester.tap(find.text('Add'));
    await tester.pump();
    expect(find.byType(ClipOval), findsOneWidget, reason: 'the photo is in flight');
    final start = tester.getCenter(find.byType(ClipOval));

    await tester.pump(CartFlight.duration ~/ 2);
    final middle = tester.getCenter(find.byType(ClipOval));
    expect(middle.dy, lessThan(start.dy), reason: 'it arcs up toward the bag');

    await tester.pump(CartFlight.duration ~/ 2 + const Duration(milliseconds: 20));
    expect(find.byType(ClipOval), findsNothing, reason: 'it disappears into the bag');
    expect(landed, isTrue);

    await tester.pump(const Duration(milliseconds: 150));
    expect(bagScale(tester).scale.value, greaterThan(1), reason: 'the bag pops');
    await tester.pumpAndSettle();
    expect(bagScale(tester).scale.value, 1);
  });

  testWidgets('with reduced motion nothing flies or pops', (tester) async {
    await tester.pumpWidget(app(
      reducedMotion: true,
      onAdd: (button) => CartFlight.fly(button, from: CartFlight.rectOf(button), imageUrl: null),
    ));

    await tester.tap(find.text('Add'));
    await tester.pump(const Duration(milliseconds: 150));
    expect(find.byType(ClipOval), findsNothing);
    expect(bagScale(tester).scale.value, 1);
  });
}
