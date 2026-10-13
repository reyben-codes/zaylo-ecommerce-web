import 'package:flutter/material.dart';

import '../../core/theme/zaylo_theme.dart';

class AuthScaffold extends StatelessWidget {
  const AuthScaffold({super.key, required this.children, this.onBack});

  final List<Widget> children;
  final VoidCallback? onBack;

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: ZayloColors.white,
        appBar: AppBar(
          backgroundColor: ZayloColors.white,
          leading: IconButton(
            tooltip: 'Back',
            onPressed: onBack ?? () => Navigator.pop(context),
            icon: const Icon(Icons.arrow_back),
          ),
          title: Image.asset('assets/images/ZAYLO_LOGO_DARK.png', height: 28),
        ),
        body: SafeArea(
          top: false,
          child: AutofillGroup(
            child: ListView(padding: const EdgeInsets.fromLTRB(24, 14, 24, 36), children: children),
          ),
        ),
      );
}

class AuthErrorBanner extends StatelessWidget {
  const AuthErrorBanner(this.message, {super.key});

  final String message;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFFFDECEA),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: const Color(0xFFF5C2BD)),
        ),
        child: Row(children: [
          const Icon(Icons.error_outline, color: Colors.redAccent, size: 20),
          const SizedBox(width: 10),
          Expanded(child: Text(message, style: const TextStyle(color: Color(0xFF8A1C12), fontSize: 13))),
        ]),
      );
}

class AuthMasthead extends StatelessWidget {
  const AuthMasthead({super.key, required this.registering});

  final bool registering;

  @override
  Widget build(BuildContext context) => Container(
        height: 170,
        decoration: BoxDecoration(
          color: ZayloColors.ink,
          borderRadius: BorderRadius.circular(20),
          image: const DecorationImage(
            image: NetworkImage(
                'https://images.unsplash.com/photo-1583394838336-acd977736f90?w=1000&q=80&auto=format&fit=crop'),
            fit: BoxFit.cover,
            opacity: .45,
          ),
        ),
        child: Container(
          padding: const EdgeInsets.all(22),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            gradient: const LinearGradient(colors: [Color(0xB31A1714), Color(0x331A1714)]),
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Image.asset('assets/images/ZAYLO_LOGO_LIGHT.png', height: 26),
            const Spacer(),
            // Scales down instead of overflowing on narrow phones or large text settings.
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(registering ? 'Your next chapter starts here.' : 'Driven by passion.',
                  style: const TextStyle(color: ZayloColors.white, fontSize: 21, fontWeight: FontWeight.w600)),
            ),
            const FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text('Defined by your origin',
                  style: TextStyle(color: Color(0xFFD7CCC0), fontStyle: FontStyle.italic)),
            ),
          ]),
        ),
      );
}
