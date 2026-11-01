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

class _AiApi extends _FaqApi {
  _AiApi(this.reply);

  final String? reply;
  final asked = <String>[];
  List<Map<String, String>> lastHistory = const [];

  @override
  Future<String?> askZay(String question, List<Map<String, String>> history) async {
    asked.add(question);
    lastHistory = history;
    return reply;
  }
}

void main() {
  testWidgets('questions Zay cannot match get the AI reply, with the conversation so far', (tester) async {
    final api = _AiApi('You can sell soap on ZAYLO.');
    await tester.pumpWidget(MaterialApp(home: HelpBotScreen(api: api, onContactSupport: () {})));
    await tester.pump();
    await tester.pump(const Duration(seconds: 2));

    // Small talk stays on the phone; a sentence containing "more" is a real question, not a request for a joke.
    await tester.enterText(find.byType(TextField), 'thanks!');
    await tester.testTextInput.receiveAction(TextInputAction.send);
    await tester.pump(const Duration(seconds: 2));
    expect(api.asked, isEmpty);

    await tester.enterText(find.byType(TextField), 'tell me more about selling soap');
    await tester.testTextInput.receiveAction(TextInputAction.send);
    await tester.pump(const Duration(seconds: 2));
    expect(api.asked, ['tell me more about selling soap']);
    expect(api.lastHistory.map((message) => message['role']), ['user', 'assistant']);
    expect(find.text('You can sell soap on ZAYLO.'), findsOneWidget);

    await tester.pump(const Duration(seconds: 10));
    await tester.pumpWidget(const SizedBox());
  });

  testWidgets('when the AI cannot answer, Zay offers support', (tester) async {
    await tester.pumpWidget(MaterialApp(home: HelpBotScreen(api: _AiApi(null), onContactSupport: () {})));
    await tester.pump();
    await tester.pump(const Duration(seconds: 2));

    await tester.enterText(find.byType(TextField), 'can I pay in bitcoin next week');
    await tester.testTextInput.receiveAction(TextInputAction.send);
    await tester.pump(const Duration(seconds: 2));
    expect(find.text('Message ZAYLO support'), findsOneWidget);

    await tester.pump(const Duration(seconds: 10));
    await tester.pumpWidget(const SizedBox());
  });

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

  testWidgets('Zay answers who made it and tells jokes', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: HelpBotScreen(api: _FaqApi(), onContactSupport: () {}),
    ));
    await tester.pump();
    await tester.pump(const Duration(seconds: 2));

    // Ask who made Zay
    await tester.enterText(find.byType(TextField), 'who created you');
    await tester.testTextInput.receiveAction(TextInputAction.send);
    await tester.pump(const Duration(seconds: 2));
    expect(find.textContaining('ZAYLO engineering team'), findsOneWidget);

    // Ask for a joke
    await tester.enterText(find.byType(TextField), 'tell me a joke');
    await tester.testTextInput.receiveAction(TextInputAction.send);
    await tester.pump(const Duration(seconds: 2));
    expect(find.textContaining('Because it wanted to be a little more parcel-tial'), findsOneWidget);

    // Clean up
    await tester.pump(const Duration(seconds: 10));
    await tester.pumpWidget(const SizedBox());
  });

  testWidgets('Zay responds warmly to how are you', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: HelpBotScreen(api: _FaqApi(), onContactSupport: () {}),
    ));
    await tester.pump();
    await tester.pump(const Duration(seconds: 2));

    await tester.enterText(find.byType(TextField), 'how are you doing?');
    await tester.testTextInput.receiveAction(TextInputAction.send);
    await tester.pump(const Duration(seconds: 2));
    expect(
      find.byWidgetPredicate((widget) =>
          widget is Text &&
          (widget.data?.contains('Blinking happily') == true ||
              widget.data?.contains('ready to roll') == true ||
              widget.data?.contains('floating around ZAYLO') == true)),
      findsOneWidget,
    );

    // Clean up
    await tester.pump(const Duration(seconds: 10));
    await tester.pumpWidget(const SizedBox());
  });
}
