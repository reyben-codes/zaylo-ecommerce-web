import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:zaylo_mobile/features/buyer/catalog/search_field.dart';

void main() {
  testWidgets('search field reports every keystroke and clears with one tap', (tester) async {
    final controller = TextEditingController();
    final typed = <String>[];
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(body: SearchField(controller: controller, onChanged: typed.add)),
    ));

    expect(find.byTooltip('Clear search'), findsNothing);
    await tester.enterText(find.byType(TextField), 'b');
    await tester.enterText(find.byType(TextField), 'bl');
    await tester.pump();
    expect(typed, ['b', 'bl']);

    await tester.tap(find.byTooltip('Clear search'));
    await tester.pump();
    expect(controller.text, isEmpty);
    expect(typed.last, '');
    expect(find.byTooltip('Clear search'), findsNothing);
  });

  testWidgets('home search bar opens the catalog instead of taking text', (tester) async {
    var opened = 0;
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(body: SearchField(onTap: () => opened++)),
    ));

    await tester.tap(find.byType(TextField));
    expect(opened, 1);
    expect(tester.widget<TextField>(find.byType(TextField)).readOnly, isTrue);
  });
}
