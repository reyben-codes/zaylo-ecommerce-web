import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/help_bot.dart';
import 'package:zaylo_mobile/components/zay_avatar.dart';
import 'package:zaylo_mobile/models/support_models.dart';

class _FaqApi extends RiderApi {
  @override
  Future<FaqData> faq() async => FaqData.fromJson({
        'greeting': "Hi! I'm Zay.",
        'items': [
          {
            'q': 'How do I collect a returned item?',
            'a': 'Scan the return slip and take a photo of the item.',
            'keywords': ['return', 'collect'],
          },
        ],
      });
}

void main() {
  testWidgets('Zay greets, types, answers, chats back and hands over to support', (tester) async {
    var supportOpened = false;
    await tester.pumpWidget(MaterialApp(
      home: HelpBotScreen(api: _FaqApi(), onContactSupport: () => supportOpened = true),
    ));
    // The answers load, then Zay types its greeting.
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
    expect(find.text('Zay is typing…'), findsOneWidget);
    await tester.pump(const Duration(seconds: 2));
    expect(find.text("Hi! I'm Zay."), findsOneWidget);
    expect(find.byType(ZayAvatar), findsWidgets);

    // A typed question: Zay types first, then answers.
    await tester.enterText(find.byType(TextField), 'how do I collect a return?');
    await tester.testTextInput.receiveAction(TextInputAction.send);
    await tester.pump();
    expect(find.text('Scan the return slip and take a photo of the item.'), findsNothing);
    await tester.pump(const Duration(seconds: 2));
    expect(find.text('Scan the return slip and take a photo of the item.'), findsOneWidget);

    // Small talk.
    await tester.enterText(find.byType(TextField), 'thanks!');
    await tester.testTextInput.receiveAction(TextInputAction.send);
    await tester.pump(const Duration(seconds: 2));
    expect(find.text("You're welcome! Anything else I can help with?"), findsOneWidget);

    // Something Zay does not know: the support button.
    await tester.enterText(find.byType(TextField), 'do you sell cars');
    await tester.testTextInput.receiveAction(TextInputAction.send);
    await tester.pump(const Duration(seconds: 2));
    await tester.ensureVisible(find.text('Message ZAYLO support'));
    await tester.pump(const Duration(milliseconds: 400));
    await tester.tap(find.text('Message ZAYLO support'));
    expect(supportOpened, isTrue);

    // Let Zay's blink and mood timers finish before the test ends.
    await tester.pump(const Duration(seconds: 10));
    await tester.pumpWidget(const SizedBox());
  });
}
