import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/api/api_client.dart';
import 'package:zaylo_mobile/features/reports/account_reports_screen.dart';

class ReportApi extends ApiClient {
  final submitted = <Map<String, dynamic>>[];

  @override
  Future<Map<String, dynamic>> accountReports() async => {
    'categories': {'harassment': 'Harassment or abusive messages'},
    'reports': {'data': [for (final data in submitted) {
      ...data, 'id': 1, 'reported_name': data['reported_name'] == '' ? 'Reported shop' : data['reported_name'], 'status': 'open',
    }]},
  };

  @override
  Future<Map<String, dynamic>> reportAccount(Map<String, dynamic> report) async {
    submitted.add(report);
    return {'message': 'Report submitted.'};
  }
}

void main() {
  testWidgets('generic account report validates details and submits the selected reason', (tester) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(400, 1600);
    addTearDown(tester.view.reset);
    final api = ReportApi();
    await tester.pumpWidget(MaterialApp(home: AccountReportsScreen(api: api)));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Submit report'));
    await tester.pump();
    expect(api.submitted, isEmpty);
    expect(find.text('Enter the account or shop name.'), findsOneWidget);
    await tester.enterText(find.widgetWithText(TextFormField, 'Account or shop name'), 'Concerned seller');
    await tester.tap(find.widgetWithText(DropdownButtonFormField<String>, 'Reason'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Harassment or abusive messages').last);
    await tester.pumpAndSettle();
    await tester.enterText(find.widgetWithText(TextFormField, 'What happened?'), 'The seller sent abusive replies about my order yesterday.');
    await tester.tap(find.text('Submit report'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 600));
    expect(api.submitted.single['reported_name'], 'Concerned seller');
    expect(api.submitted.single['category'], 'harassment');
    expect(find.text('#1 · Concerned seller'), findsOneWidget);
  });

  testWidgets('shop report identifies the seller through the shop id', (tester) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(400, 1600);
    addTearDown(tester.view.reset);
    final api = ReportApi();
    await tester.pumpWidget(MaterialApp(home: AccountReportsScreen(api: api, shopId: 42, shopName: 'Example shop')));
    await tester.pumpAndSettle();
    expect(find.text('Reporting seller: Example shop'), findsOneWidget);
    expect(find.widgetWithText(TextFormField, 'Account or shop name'), findsNothing);
    await tester.tap(find.widgetWithText(DropdownButtonFormField<String>, 'Reason'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Harassment or abusive messages').last);
    await tester.pumpAndSettle();
    await tester.enterText(find.widgetWithText(TextFormField, 'What happened?'), 'The seller sent abusive replies about my order yesterday.');
    await tester.tap(find.text('Submit report'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 600));
    expect(api.submitted.single['shop_id'], 42);
  });
}
