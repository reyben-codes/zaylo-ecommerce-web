import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/auth/buyer/finish_sign_in.dart';
import 'package:zaylo_mobile/auth/google_auth.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// "or" and a "Continue with Google" button, as under the website's sign-in
/// and sign-up forms. Hidden until Google sign-in is configured for the build.
/// By default it signs buyers in (new Google accounts become buyer accounts with
/// a verified email); [signIn] replaces that, as on the rider login screen.
class GoogleSignInSection extends StatefulWidget {
  const GoogleSignInSection({super.key, required this.onError, this.signIn});

  /// Shows a problem in the screen's error banner; null clears it.
  final ValueChanged<String?> onError;

  /// Sends the Google ID token and opens the signed-in app. Defaults to the buyer sign-in.
  final Future<void> Function(String idToken)? signIn;

  @override
  State<GoogleSignInSection> createState() => _GoogleSignInSectionState();
}

class _GoogleSignInSectionState extends State<GoogleSignInSection> {
  bool _busy = false;

  Future<void> _signIn() async {
    widget.onError(null);
    setState(() => _busy = true);
    final state = AppScope.read(context);
    try {
      final idToken = await GoogleAuth.source.idToken();
      if (idToken == null || !mounted) return; // Google's account picker was closed.
      if (widget.signIn != null) {
        await widget.signIn!(idToken);
      } else {
        final result = await state.api.googleSignIn(idToken);
        if (mounted) await finishBuyerSignIn(context, result);
      }
    } on GoogleSignInFailure catch (error) {
      widget.onError(error.message);
    } on ApiException catch (error) {
      // Forget the chosen account, so trying again lets them pick another one.
      await GoogleAuth.source.signOut();
      widget.onError(error.fieldErrors['google'] ?? error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!GoogleAuth.source.available) return const SizedBox.shrink();
    final dark = Theme.of(context).brightness == Brightness.dark;
    // Google's sign-in button colors for light and dark backgrounds.
    final fill = dark ? const Color(0xFF131314) : Colors.white;
    final stroke = dark ? const Color(0xFF8E918F) : const Color(0xFF747775);
    final text = dark ? const Color(0xFFE3E3E3) : const Color(0xFF1F1F1F);
    return Column(children: [
      const SizedBox(height: 22),
      Row(children: [
        Expanded(child: Divider(color: ZayloColors.line)),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Text('or', style: TextStyle(color: ZayloColors.muted, fontSize: 13)),
        ),
        Expanded(child: Divider(color: ZayloColors.line)),
      ]),
      const SizedBox(height: 18),
      SizedBox(
        width: double.infinity,
        height: 50,
        child: OutlinedButton(
          onPressed: _busy ? null : _signIn,
          style: OutlinedButton.styleFrom(
            backgroundColor: fill,
            foregroundColor: text,
            disabledForegroundColor: text.withValues(alpha: .6),
            side: BorderSide(color: stroke),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
          ),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            _busy
                ? SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: text))
                : const GoogleLogo(size: 20),
            const SizedBox(width: 12),
            const Flexible(child: Text('Continue with Google', maxLines: 1, overflow: TextOverflow.ellipsis)),
          ]),
        ),
      ),
    ]);
  }
}

/// The four-color Google "G", drawn from the same SVG paths as the website's button.
class GoogleLogo extends StatelessWidget {
  const GoogleLogo({super.key, this.size = 20});

  final double size;

  @override
  Widget build(BuildContext context) =>
      ExcludeSemantics(child: CustomPaint(size: Size.square(size), painter: const _GoogleLogoPainter()));
}

class _GoogleLogoPainter extends CustomPainter {
  const _GoogleLogoPainter();

  @override
  void paint(Canvas canvas, Size size) {
    // The paths use the SVG's 24×24 viewBox.
    canvas.scale(size.width / 24, size.height / 24);
    void fill(int color, Path path) => canvas.drawPath(path..close(), Paint()..color = Color(color));
    const big = Radius.circular(9.75);

    fill(0xFF4285F4, Path()
      ..moveTo(21.35, 12.27)
      ..relativeCubicTo(0, -.79, -.07, -1.55, -.2, -2.27)
      ..lineTo(12, 10)
      ..relativeLineTo(0, 4.3)
      ..relativeLineTo(5.23, 0)
      ..relativeArcToPoint(const Offset(-1.94, 2.93), radius: const Radius.circular(4.47))
      ..relativeLineTo(0, 2.79)
      ..relativeLineTo(3.14, 0)
      ..relativeCubicTo(1.84, -1.69, 2.92, -4.18, 2.92, -7.75));
    fill(0xFF34A853, Path()
      ..moveTo(12, 21.75)
      ..relativeCubicTo(2.63, 0, 4.84, -.87, 6.45, -2.36)
      ..relativeLineTo(-3.14, -2.43)
      ..relativeCubicTo(-.87, .58, -1.98, .93, -3.31, .93)
      ..relativeCubicTo(-2.54, 0, -4.69, -1.72, -5.46, -4.02)
      ..lineTo(3.3, 13.87)
      ..relativeLineTo(0, 2.51)
      ..arcToPoint(const Offset(12, 21.75), radius: big, clockwise: false));
    fill(0xFFFBBC05, Path()
      ..moveTo(6.54, 13.87)
      ..arcToPoint(const Offset(6.23, 12), radius: const Radius.circular(5.86))
      ..relativeCubicTo(0, -.65, .11, -1.28, .31, -1.87)
      ..lineTo(6.54, 7.62)
      ..lineTo(3.3, 7.62)
      ..arcToPoint(const Offset(2.25, 12), radius: big, clockwise: false)
      ..relativeCubicTo(0, 1.57, .38, 3.05, 1.05, 4.38)
      ..relativeLineTo(3.24, -2.51));
    fill(0xFFEA4335, Path()
      ..moveTo(12, 6.11)
      ..relativeCubicTo(1.43, 0, 2.72, .49, 3.73, 1.46)
      ..relativeLineTo(2.79, -2.79)
      ..cubicTo(16.83, 3.21, 14.63, 2.25, 12, 2.25)
      ..relativeArcToPoint(const Offset(-8.7, 5.37), radius: big, clockwise: false)
      ..relativeLineTo(3.24, 2.51)
      ..relativeCubicTo(.77, -2.3, 2.92, -4.02, 5.46, -4.02));
  }

  @override
  bool shouldRepaint(_GoogleLogoPainter oldDelegate) => false;
}
