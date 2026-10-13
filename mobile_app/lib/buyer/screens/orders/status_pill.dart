import 'package:flutter/material.dart';

import '../../../core/theme/zaylo_theme.dart';
import '../../models/order_models.dart';

Color orderStatusColor(String status) => switch (status) {
      'completed' => const Color(0xFF2E7D32),
      'cancelled' => Colors.redAccent,
      'picked_up' || 'in_transit' => const Color(0xFF1565C0),
      _ => ZayloColors.taupe,
    };

class StatusPill extends StatelessWidget {
  const StatusPill(this.order, {super.key});

  final Order order;

  @override
  Widget build(BuildContext context) {
    final color = orderStatusColor(order.status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(20)),
      child: Text(order.statusLabel, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: color)),
    );
  }
}
