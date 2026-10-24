import 'package:flutter/material.dart';

/// The website's cart icon (resources/views/partials/icons/cart.blade.php):
/// an outlined cart with two wheels, drawn from the same SVG paths with the
/// same round 1.7 stroke. Like [Icon], it takes its size and color from the
/// surrounding [IconTheme] unless given. [filled] fills the basket, for the
/// selected Bag tab.
class CartIcon extends StatelessWidget {
  const CartIcon({super.key, this.size, this.color, this.filled = false, this.semanticLabel});

  final double? size;
  final Color? color;
  final bool filled;
  final String? semanticLabel;

  @override
  Widget build(BuildContext context) {
    final theme = IconTheme.of(context);
    final iconSize = size ?? theme.size ?? 24;
    final iconColor = (color ?? theme.color ?? const Color(0xFF000000)).withValues(alpha: (theme.opacity ?? 1));
    final art = CustomPaint(size: Size.square(iconSize), painter: _CartPainter(iconColor, filled));
    return semanticLabel == null
        ? ExcludeSemantics(child: art)
        : Semantics(label: semanticLabel, image: true, child: ExcludeSemantics(child: art));
  }
}

class _CartPainter extends CustomPainter {
  const _CartPainter(this.color, this.filled);

  final Color color;
  final bool filled;

  @override
  void paint(Canvas canvas, Size size) {
    // The SVG's 24×24 viewBox: M3 4h2.1l1.55 9.1a2 2 0 0 0 1.98 1.66h8.72a2 2 0 0 0 1.96-1.58L20.6 7H5.62
    canvas.scale(size.width / 24, size.height / 24);
    final basket = Path()
      ..moveTo(3, 4)
      ..relativeLineTo(2.1, 0)
      ..relativeLineTo(1.55, 9.1)
      ..relativeArcToPoint(const Offset(1.98, 1.66), radius: const Radius.circular(2), clockwise: false)
      ..relativeLineTo(8.72, 0)
      ..relativeArcToPoint(const Offset(1.96, -1.58), radius: const Radius.circular(2), clockwise: false)
      ..lineTo(20.6, 7)
      ..lineTo(5.62, 7);
    final stroke = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.7
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;

    if (filled) canvas.drawPath(basket, Paint()..color = color);
    canvas.drawPath(basket, stroke);
    for (final wheel in const [Offset(9, 19), Offset(17, 19)]) {
      if (filled) canvas.drawCircle(wheel, 1.5, Paint()..color = color);
      canvas.drawCircle(wheel, 1.5, stroke);
    }
  }

  @override
  bool shouldRepaint(_CartPainter oldDelegate) => oldDelegate.color != color || oldDelegate.filled != filled;
}
