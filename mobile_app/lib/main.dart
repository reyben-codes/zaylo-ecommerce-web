import 'package:flutter/material.dart';

import 'buyer/api/buyer_api.dart';
import 'buyer/app_shell.dart';
import 'buyer/state/app_state.dart';
import 'core/theme/zaylo_theme.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(ZayloApp(state: AppState(api: BuyerApi())..restore()));
}

class ZayloApp extends StatelessWidget {
  const ZayloApp({super.key, required this.state});

  final AppState state;

  @override
  Widget build(BuildContext context) => AppScope(
        state: state,
        child: MaterialApp(
          title: 'ZAYLO',
          debugShowCheckedModeBanner: false,
          theme: zayloTheme(),
          home: const AppShell(),
        ),
      );
}
