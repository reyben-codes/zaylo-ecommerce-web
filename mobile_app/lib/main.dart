import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/buyer_api.dart';
import 'package:zaylo_mobile/app.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/theme_controller.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(ZayloApp(
    state: AppState(api: BuyerApi())..restore(),
    theme: ThemeController()..restore(),
  ));
}
