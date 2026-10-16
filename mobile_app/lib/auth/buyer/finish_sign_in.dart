import 'package:flutter/material.dart';

import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/models/auth_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';

/// Signs the buyer in and closes every auth screen above the tab shell.
Future<void> finishBuyerSignIn(BuildContext context, AuthResult result) async {
  await AppScope.read(context).completeSignIn(result);
  if (!context.mounted) return;
  Navigator.of(context).popUntil((route) => route.isFirst);
  showMessage(context, 'Welcome, ${result.user?.firstName ?? 'back'}!');
}
