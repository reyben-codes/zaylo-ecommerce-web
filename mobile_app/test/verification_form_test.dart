import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/api/api_client.dart';
import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/auth/widgets/otp_code_field.dart';
import 'package:zaylo_mobile/auth/widgets/verification_form.dart';
import 'package:zaylo_mobile/models/auth_models.dart';

/// Accepts only [validCode]; records every code it was asked to check.
class _FakeApi extends ApiClient {
  _FakeApi(this.validCode) : super(baseUrl: 'http://localhost');

  final String validCode;
  final checked = <String>[];

  @override
  Future<AuthResult> verifyEmail({required String email, required String code}) async {
    checked.add(code);
    await Future<void>.delayed(const Duration(milliseconds: 50));
    if (code != validCode) throw const ApiException('The verification code is incorrect.', statusCode: 422);
    return const AuthResult(message: 'Email verified.');
  }
}

void main() {
  Future<List<AuthResult>> pump(WidgetTester tester, _FakeApi api) async {
    final verified = <AuthResult>[];
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: VerificationForm(api: api, email: 'shopper@example.com', onVerified: (result) async => verified.add(result)),
        ),
      ),
    ));
    return verified;
  }

  test('extracts a code from copied text', () {
    expect(OtpCodeField.extractCode('Your ZAYLO code is 012 345.'), '012345');
    expect(OtpCodeField.extractCode('123456 is your ZAYLO verification code'), '123456');
    expect(OtpCodeField.extractCode('Order 99, code 654-321'), '654321');
    expect(OtpCodeField.extractCode('nothing here'), '');
  });

  testWidgets('the sixth digit submits the code and shows the verified state', (tester) async {
    final api = _FakeApi('012345');
    final verified = await pump(tester, api);

    await tester.enterText(find.byType(TextField), '01234');
    await tester.pump();
    expect(api.checked, isEmpty);
    expect(find.text('4'), findsOneWidget);

    await tester.enterText(find.byType(TextField), '012345');
    await tester.pump();
    expect(find.text('Checking your code…'), findsOneWidget);

    await tester.pump(const Duration(milliseconds: 60));
    expect(api.checked, ['012345']);
    expect(find.text('Email verified!'), findsOneWidget);
    expect(find.text('VERIFIED'), findsOneWidget);

    await tester.pump(const Duration(seconds: 1));
    await tester.pump(const Duration(milliseconds: 300));
    expect(verified, hasLength(1));
  });

  testWidgets('a wrong code shows the error, then clears the boxes for another try', (tester) async {
    final api = _FakeApi('012345');
    final verified = await pump(tester, api);

    await tester.enterText(find.byType(TextField), '999999');
    await tester.pump(const Duration(milliseconds: 60));
    expect(find.text('The verification code is incorrect.'), findsOneWidget);

    await tester.pump(const Duration(milliseconds: 700));
    await tester.pump(const Duration(milliseconds: 300));
    expect(tester.widget<TextField>(find.byType(TextField)).controller!.text, isEmpty);
    expect(verified, isEmpty);

    // Typing again clears the error message.
    await tester.enterText(find.byType(TextField), '0');
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300)); // let the message fade out
    expect(find.text('The verification code is incorrect.'), findsNothing);
    expect(api.checked, ['999999']);
  });

  testWidgets('Paste code fills the boxes from the clipboard and submits', (tester) async {
    final api = _FakeApi('654321');
    tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, (call) async {
      if (call.method == 'Clipboard.getData') return {'text': 'Your ZAYLO verification code is 654 321'};
      return null;
    });
    addTearDown(() => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, null));
    final verified = await pump(tester, api);

    await tester.tap(find.text('Paste code'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 60));
    expect(api.checked, ['654321']);

    await tester.pump(const Duration(seconds: 1));
    await tester.pump(const Duration(milliseconds: 300));
    expect(verified, hasLength(1));
  });
}
