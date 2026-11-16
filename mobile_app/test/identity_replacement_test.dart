import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/api/buyer_api.dart';
import 'package:zaylo_mobile/features/buyer/account/identity_screen.dart';
import 'package:zaylo_mobile/models/auth_models.dart';
import 'package:zaylo_mobile/models/buyer/account_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';

class IdentityApi extends BuyerApi {
  bool verified = true;
  @override
  Future<IdentityStatus> identity() async => IdentityStatus(enabled: true, verified: verified,
    idTypes: const [IdType(id: 'passport', label: 'Passport'), IdType(id: 'philid', label: 'National ID')],
    submission: IdentitySubmission(status: verified ? 'approved' : 'pending', idTypeLabel: 'Passport'),
  );
}

void main() {
  testWidgets('verified buyers can open replacement, cancel without losing their selection, and reopen', (tester) async {
    final state = AppState(api: IdentityApi())..updateUser(const UserProfile(id: 1, name: 'Buyer', email: 'buyer@example.test'));
    await tester.pumpWidget(AppScope(state: state, child: const MaterialApp(home: IdentityScreen())));
    await tester.pumpAndSettle();
    expect(find.text('CHANGE ID AND RE-VERIFY'), findsOneWidget);
    expect(state.user!.identityVerified, isTrue);
    await tester.tap(find.text('CHANGE ID AND RE-VERIFY'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
    expect(tester.widget<SizeTransition>(find.byType(SizeTransition).first).sizeFactor.value, inExclusiveRange(0, 1));
    await tester.pumpAndSettle();
    final dropdown = find.byType(DropdownButtonFormField<String>);
    await tester.tap(dropdown);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Passport').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('CANCEL'));
    await tester.pumpAndSettle();
    expect(state.user!.identityVerified, isTrue);
    await tester.tap(find.text('CHANGE ID AND RE-VERIFY'));
    await tester.pumpAndSettle();
    expect(tester.state<FormFieldState<String>>(dropdown).value, 'passport');
    expect(find.text('SUBMIT REPLACEMENT FOR REVIEW'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    state.dispose();
  });

  testWidgets('pending re-verification clears a stale verified profile and respects reduced motion', (tester) async {
    final state = AppState(api: IdentityApi()..verified = false)
      ..updateUser(const UserProfile(id: 1, name: 'Buyer', email: 'buyer@example.test'));
    await tester.pumpWidget(AppScope(state: state, child: const MaterialApp(home:
      MediaQuery(data: MediaQueryData(disableAnimations: true), child: IdentityScreen()))));
    await tester.pumpAndSettle();
    expect(find.text('Under review'), findsOneWidget);
    expect(state.user!.identityVerified, isFalse);
    await tester.tap(find.text('REPLACE MY UPLOAD'));
    await tester.pump();
    expect(tester.widget<SizeTransition>(find.byType(SizeTransition).first).sizeFactor.value, 1);
    await tester.pumpWidget(const SizedBox());
    state.dispose();
  });
}
