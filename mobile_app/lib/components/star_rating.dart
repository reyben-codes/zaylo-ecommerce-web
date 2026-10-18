import 'package:flutter/material.dart';

import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Five stars filled to [rating] (halves included), read as "4.5 out of 5 stars".
class StarRow extends StatelessWidget {
  const StarRow({super.key, required this.rating, this.size = 16});

  final double rating;
  final double size;

  @override
  Widget build(BuildContext context) => Semantics(
        label: '${rating.toStringAsFixed(rating % 1 == 0 ? 0 : 1)} out of 5 stars',
        excludeSemantics: true,
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          for (var star = 1; star <= 5; star++)
            Icon(
              rating >= star
                  ? Icons.star_rounded
                  : rating >= star - .5
                      ? Icons.star_half_rounded
                      : Icons.star_outline_rounded,
              size: size,
              color: ZayloColors.bronze,
            ),
        ]),
      );
}

/// Tappable 1–5 star picker for writing a review.
class StarPicker extends StatelessWidget {
  const StarPicker({super.key, required this.value, required this.onChanged, this.size = 40});

  final int value;
  final ValueChanged<int> onChanged;
  final double size;

  static const labels = ['Poor', 'Fair', 'Good', 'Very good', 'Excellent'];

  @override
  Widget build(BuildContext context) => Row(mainAxisAlignment: MainAxisAlignment.center, children: [
        for (var star = 1; star <= 5; star++)
          IconButton(
            tooltip: '$star ${star == 1 ? 'star' : 'stars'} · ${labels[star - 1]}',
            iconSize: size,
            onPressed: () => onChanged(star),
            icon: Icon(star <= value ? Icons.star_rounded : Icons.star_outline_rounded, color: ZayloColors.bronze),
          ),
      ]);
}
