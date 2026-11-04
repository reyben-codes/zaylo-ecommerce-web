import 'package:flutter/material.dart';

import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Zaylo's warehouse holds at most this many of one item (matches Product::WAREHOUSE_CAPACITY on the server).
const zayloWarehouseCapacity = 100;

/// Slides open while [quantity] is above [zayloWarehouseCapacity] to say the order ships from the seller's warehouse.
class WarehouseNote extends StatelessWidget {
  const WarehouseNote({super.key, required this.quantity, this.padding = const EdgeInsets.only(top: 8)});

  final int quantity;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    final show = quantity > zayloWarehouseCapacity;
    final duration = MediaQuery.of(context).disableAnimations ? Duration.zero : const Duration(milliseconds: 300);
    return AnimatedSize(
      duration: duration,
      curve: Curves.easeOutCubic,
      alignment: Alignment.topCenter,
      child: AnimatedOpacity(
        duration: duration,
        curve: Curves.easeOut,
        opacity: show ? 1 : 0,
        child: !show
            ? const SizedBox(width: double.infinity)
            : Padding(
                padding: padding,
                child: Semantics(
                  liveRegion: true,
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                    decoration: BoxDecoration(
                      color: ZayloColors.white,
                      border: Border.all(color: ZayloColors.line),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Icon(Icons.warehouse_outlined, size: 16, color: ZayloColors.muted),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          "Zaylo's warehouse can only hold $zayloWarehouseCapacity of this item, so orders over "
                          "$zayloWarehouseCapacity ship directly from the seller's warehouse instead.",
                          style: TextStyle(fontSize: 12, height: 1.4, color: ZayloColors.ink),
                        ),
                      ),
                    ]),
                  ),
                ),
              ),
      ),
    );
  }
}
