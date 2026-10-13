import 'package:flutter/material.dart';

import 'package:zaylo_mobile/models/buyer/order_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

Color orderStatusColor(String status) => switch (status) {
      'completed' => ZayloColors.success,
      'cancelled' => Colors.redAccent,
      'picked_up' || 'in_transit' => ZayloColors.info,
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
