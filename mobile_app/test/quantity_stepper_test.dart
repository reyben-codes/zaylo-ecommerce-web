import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/components/buttons.dart';

void main() {
  Future<List<int>> pump(WidgetTester tester, {int value = 2, int max = 10}) async {
    final changes = <int>[];
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: StatefulBuilder(
          builder: (context, setState) => QuantityStepper(
            value: value,
            max: max,
            onChanged: (amount) => setState(() {
              value = amount;
              changes.add(amount);
            }),
          ),
        ),
      ),
    ));
    return changes;
  }

  testWidgets('typing an exact quantity applies it when done', (tester) async {
    final changes = await pump(tester);
    await tester.enterText(find.byType(TextField), '7');
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await tester.pump();

    expect(changes, [7]);
    expect(find.text('7'), findsOneWidget);
  });

  testWidgets('typed amounts are kept between 1 and the stock left', (tester) async {
    final changes = await pump(tester, max: 5);
    await tester.enterText(find.byType(TextField), '40');
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await tester.pump();
    expect(changes, [5]);

    await tester.enterText(find.byType(TextField), '0');
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await tester.pump();
    expect(changes, [5, 1]);
  });

  testWidgets('the + and - buttons still step by one and show the new amount', (tester) async {
    final changes = await pump(tester);
    await tester.tap(find.byIcon(Icons.add));
    await tester.pump();
    await tester.tap(find.byIcon(Icons.remove));
    await tester.pump();

    expect(changes, [3, 2]);
    expect(find.text('2'), findsOneWidget);
  });
}
