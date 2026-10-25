import 'package:flutter/material.dart';

import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/models/rider/rider_dashboard_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

Color get _green => ZayloColors.success;
Color get _amber => ZayloColors.warning;
Color get _red => ZayloColors.danger;

/// Greeting, approval badge, and vehicle at the top of the dashboard.
class RiderGreetingCard extends StatelessWidget {
  const RiderGreetingCard({super.key, required this.rider});

  final RiderProfile rider;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(color: ZayloColors.ink, borderRadius: BorderRadius.circular(18)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Expanded(
              child: Text('Hi, ${rider.firstName}',
                  style: TextStyle(color: ZayloColors.white, fontSize: 22, fontWeight: FontWeight.w700)),
            ),
            _Pill(label: 'Approved rider', color: _green, light: true),
          ]),
          const SizedBox(height: 6),
          Text('Ready to deliver today?', style: TextStyle(color: ZayloColors.onInkMuted)),
          if (rider.vehicleLabel != null || rider.plateNumber != null) ...[
            const SizedBox(height: 16),
            Row(children: [
              Icon(Icons.two_wheeler_outlined, color: ZayloColors.white, size: 20),
              const SizedBox(width: 8),
              Flexible(
                child: Text([rider.vehicleLabel, rider.plateNumber].whereType<String>().join(' · '),
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(color: ZayloColors.white, fontWeight: FontWeight.w600)),
              ),
            ]),
          ],
        ]),
      );
}

/// Shown when the driver's license has expired or expires within 30 days.
class LicenseWarningBanner extends StatelessWidget {
  const LicenseWarningBanner({super.key, required this.rider});

  final RiderProfile rider;

  @override
  Widget build(BuildContext context) {
    final days = rider.licenseDaysLeft ?? 0;
    final expired = days < 0;
    final message = expired
        ? 'Your driver\'s license expired on ${shortDate(rider.licenseExpiresOn)}. Renew it to keep delivering.'
        : days == 0
            ? 'Your driver\'s license expires today. Renew it to keep delivering.'
            : 'Your driver\'s license expires in $days day${days == 1 ? '' : 's'} (${shortDate(rider.licenseExpiresOn)}).';
    final color = expired ? _red : _amber;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: .35)),
      ),
      child: Row(children: [
        Icon(Icons.warning_amber_rounded, color: color),
        const SizedBox(width: 10),
        Expanded(child: Text(message, style: TextStyle(color: color, fontWeight: FontWeight.w600))),
      ]),
    );
  }
}

/// Four headline numbers: available, active, delivered today, earned today.
/// Each tile opens the matching list or screen.
class RiderStatsGrid extends StatelessWidget {
  const RiderStatsGrid({
    super.key,
    required this.stats,
    this.onDelivered,
    this.onActive,
    this.onDeliveredToday,
    this.onEarnedToday,
  });

  final RiderStats stats;
  final VoidCallback? onDelivered;
  final VoidCallback? onActive;
  final VoidCallback? onDeliveredToday;
  final VoidCallback? onEarnedToday;

  @override
  Widget build(BuildContext context) => Column(children: [
        Row(children: [
          Expanded(
            child: _StatTile(
                icon: Icons.local_shipping_outlined, label: 'Active', value: '${stats.active}', onTap: onActive),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: _StatTile(
                icon: Icons.inventory_2_outlined, label: 'Delivered', value: '${stats.completed}', onTap: onDelivered),
          ),
        ]),
        const SizedBox(height: 12),
        Row(children: [
          Expanded(
            child: _StatTile(
              icon: Icons.task_alt,
              label: 'Delivered today',
              value: '${stats.completedToday}',
              onTap: onDeliveredToday,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: _StatTile(
              icon: Icons.payments_outlined,
              label: 'Earned today',
              value: peso(stats.earningsToday, cents: false),
              onTap: onEarnedToday,
            ),
          ),
        ]),
      ]);
}

class _StatTile extends StatelessWidget {
  const _StatTile({required this.icon, required this.label, required this.value, this.onTap});

  final IconData icon;
  final String label;
  final String value;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Semantics(
        button: onTap != null,
        label: '$label: $value',
        excludeSemantics: true,
        child: Material(
          color: ZayloColors.paper,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
            side: BorderSide(color: ZayloColors.line),
          ),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.all(ZayloSpace.card),
              child: _tileContent(),
            ),
          ),
        ),
      );

  Widget _tileContent() => Row(children: [
        CircleAvatar(
          radius: 20,
          backgroundColor: ZayloColors.sand,
          child: Icon(icon, size: 20, color: ZayloColors.ink),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(value, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
            ),
            Text(label,
                maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
          ]),
        ),
        if (onTap != null) Icon(Icons.chevron_right, size: 18, color: ZayloColors.muted),
      ]);
}

/// A section title with an optional count.
class RiderSectionHeader extends StatelessWidget {
  const RiderSectionHeader({super.key, required this.title, this.count});

  final String title;
  final int? count;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 26, bottom: 12),
        child: Row(children: [
          Flexible(
            child: Text(title,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
          ),
          if (count != null) ...[
            const SizedBox(width: 8),
            _Pill(label: '$count', color: ZayloColors.ink),
          ],
        ]),
      );
}

/// One delivery: status, address, amounts, and the rider's next action.
/// [busy] shows a spinner on the action while the request is in flight.
class RiderDeliveryCard extends StatelessWidget {
  const RiderDeliveryCard({
    super.key,
    required this.delivery,
    this.actionLabel,
    this.onAction,
    this.busy = false,
    this.onChat,
    this.onOtherAction,
    this.onAccept,
    this.onDecline,
    this.onTap,
  });

  final RiderDelivery delivery;

  /// Answer a new assignment ([RiderDelivery.needsAcceptance]); shown instead of the delivery steps.
  final VoidCallback? onAccept;
  final VoidCallback? onDecline;

  /// Opens the delivery's details.
  final VoidCallback? onTap;
  final String? actionLabel;
  final VoidCallback? onAction;
  final bool busy;

  /// Runs one of the other steps in [RiderDelivery.actions], e.g. "Could not deliver".
  final ValueChanged<String>? onOtherAction;

  /// Opens the chat with the buyer; shown only while [RiderDelivery.chatOpen].
  final VoidCallback? onChat;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: SectionCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            InkWell(
              onTap: onTap,
              borderRadius: BorderRadius.circular(8),
              child: Row(children: [
                Expanded(
                  child: Text(delivery.orderNumber ?? delivery.trackingNumber,
                      style: const TextStyle(fontWeight: FontWeight.w700)),
                ),
                _Pill(label: delivery.statusLabel, color: _statusColor(delivery.status)),
                if (onTap != null) ...[
                  const SizedBox(width: 4),
                  Icon(Icons.chevron_right, size: 20, color: ZayloColors.muted, semanticLabel: 'Details'),
                ],
              ]),
            ),
            const SizedBox(height: 4),
            Text('Tracking ${delivery.trackingNumber}', style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
            const SizedBox(height: 12),
            if (delivery.pickup != null)
              _Stop(
                icon: Icons.storefront_outlined,
                label: 'Pick up from',
                title: delivery.pickup!.storeName,
                lines: [delivery.pickup!.address ?? 'Ask the seller for the pickup address', delivery.pickup!.phone],
              ),
            _Stop(
              icon: Icons.location_on_outlined,
              label: 'Deliver to',
              title: delivery.recipientName ?? 'Recipient shown after you accept',
              lines: [delivery.shippingAddress, delivery.recipientPhone],
            ),
            if (delivery.items.isNotEmpty) _ItemsToPickUp(items: delivery.items, total: delivery.itemCount),
            if (delivery.notes != null && delivery.notes!.trim().isNotEmpty)
              _InfoRow(icon: Icons.sticky_note_2_outlined, text: delivery.notes!),
            if (delivery.lastFailure != null && delivery.status == 'failed')
              _InfoRow(icon: Icons.error_outline, text: 'Last attempt: ${delivery.lastFailure!.summary}', color: _red),
            const SizedBox(height: 8),
            Row(children: [
              Expanded(
                child: _Amount(
                  label: delivery.cashToCollect > 0 ? 'Collect (COD)' : 'Payment',
                  value: delivery.cashToCollect > 0 ? peso(delivery.cashToCollect) : 'Prepaid',
                ),
              ),
              Expanded(child: _Amount(label: 'Delivery fee', value: peso(delivery.deliveryFee))),
            ]),
            if (delivery.deliveredAt != null) ...[
              const SizedBox(height: 8),
              Text('Delivered ${dateTimeLabel(delivery.deliveredAt)}',
                  style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
            ],
            if (onChat != null && delivery.chatOpen) ...[
              const SizedBox(height: 14),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: onChat,
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                  icon: Badge(
                    isLabelVisible: delivery.unreadMessages > 0,
                    label: Text('${delivery.unreadMessages}'),
                    child: const Icon(Icons.chat_bubble_outline, size: 18),
                  ),
                  label: Text(
                    'Chat with ${_firstName(delivery.recipientName) ?? 'buyer'}',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
              ),
            ],
            if (delivery.needsAcceptance && (onAccept != null || onDecline != null || busy)) ...[
              const SizedBox(height: 14),
              Text('Your logistics partner assigned this parcel to you. Accept it to pick it up at the hub.',
                  style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
              const SizedBox(height: 10),
              Row(children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: busy ? null : onDecline,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: _red,
                      side: BorderSide(color: _red),
                      padding: const EdgeInsets.symmetric(vertical: 13),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    child: const Text('DECLINE', style: TextStyle(fontWeight: FontWeight.w700)),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton(
                    onPressed: busy ? null : onAccept,
                    style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 13)),
                    child: busy
                        ? SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2, color: ZayloColors.white))
                        : const Text('ACCEPT', style: TextStyle(fontWeight: FontWeight.w700)),
                  ),
                ),
              ]),
            ],
            if (actionLabel != null) ...[
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: busy ? null : onAction,
                  style: FilledButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 13),
                  ),
                  child: busy
                      ? SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2, color: ZayloColors.white))
                      : Row(mainAxisSize: MainAxisSize.min, children: [
                          if (delivery.nextNeedsScan) ...[
                            const Icon(Icons.qr_code_scanner, size: 18),
                            const SizedBox(width: 8),
                          ],
                          Flexible(
                            child: Text(actionLabel!.toUpperCase(),
                                overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
                          ),
                        ]),
                ),
              ),
            ],
            if (onOtherAction != null)
              for (final action in delivery.actions.skip(1)) ...[
                const SizedBox(height: 8),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton(
                    onPressed: busy ? null : () => onOtherAction!(action.status),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: action.status == 'failed' ? _red : ZayloColors.ink,
                      side: BorderSide(color: action.status == 'failed' ? _red : ZayloColors.ink),
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    child: Text(action.label.toUpperCase(), style: const TextStyle(fontWeight: FontWeight.w700)),
                  ),
                ),
              ],
          ]),
        ),
      );

  static String? _firstName(String? name) {
    final first = name?.trim().split(RegExp(r'\s+')).first;
    return first == null || first.isEmpty ? null : first;
  }

  static Color _statusColor(String status) => switch (status) {
        'new' || 'assigned' => _amber,
        'delivered' => _green,
        'failed' || 'returned' || 'cancelled' => _red,
        _ => ZayloColors.info,
      };
}

/// Shown when a list is empty, inside the scrolling dashboard.
class RiderEmptyCard extends StatelessWidget {
  const RiderEmptyCard({super.key, required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) => SectionCard(
        child: Row(children: [
          Icon(icon, color: ZayloColors.muted),
          const SizedBox(width: 12),
          Expanded(child: Text(message, style: TextStyle(color: ZayloColors.muted))),
        ]),
      );
}

/// License, contact details, and lifetime totals.
class RiderProfileCard extends StatelessWidget {
  const RiderProfileCard({super.key, required this.rider, required this.stats});

  final RiderProfile rider;
  final RiderStats stats;

  @override
  Widget build(BuildContext context) => SectionCard(
        child: Column(children: [
          _DetailRow('License number', rider.licenseNumber ?? '—'),
          _DetailRow('License expires', rider.licenseExpiresOn == null ? '—' : shortDate(rider.licenseExpiresOn)),
          _DetailRow('Vehicle', [rider.vehicleLabel, rider.plateNumber].whereType<String>().join(' · ')),
          _DetailRow('Mobile number', rider.phone ?? '—'),
          _DetailRow('Email', rider.email),
          const Divider(height: 22),
          _DetailRow('Deliveries completed', '${stats.completed}'),
          _DetailRow('Total delivery fees', peso(stats.earningsTotal)),
        ]),
      );
}

class _DetailRow extends StatelessWidget {
  const _DetailRow(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 5),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(child: Text(label, style: TextStyle(color: ZayloColors.muted))),
          const SizedBox(width: 12),
          Flexible(child: Text(value, textAlign: TextAlign.right, style: const TextStyle(fontWeight: FontWeight.w600))),
        ]),
      );
}

/// A pickup or drop-off stop: a label, a bold name, and address lines.
class _Stop extends StatelessWidget {
  const _Stop({required this.icon, required this.label, required this.title, required this.lines});

  final IconData icon;
  final String label;
  final String title;
  final List<String?> lines;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(icon, size: 20, color: ZayloColors.ink),
          const SizedBox(width: 10),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(label.toUpperCase(),
                  style: TextStyle(
                      fontSize: 10, letterSpacing: .8, fontWeight: FontWeight.w700, color: ZayloColors.muted)),
              const SizedBox(height: 2),
              Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
              for (final line in lines.whereType<String>()) Text(line),
            ]),
          ),
        ]),
      );
}

/// The parcel's contents, so the rider can check them with the seller.
class _ItemsToPickUp extends StatelessWidget {
  const _ItemsToPickUp({required this.items, required this.total});

  final List<RiderDeliveryItem> items;
  final int total;

  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: ZayloColors.white,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: ZayloColors.line),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            const Icon(Icons.inventory_2_outlined, size: 18),
            const SizedBox(width: 8),
            Expanded(
              child: Text('Items to pick up ($total)', style: const TextStyle(fontWeight: FontWeight.w700)),
            ),
          ]),
          const SizedBox(height: 8),
          for (final item in items)
            Padding(
              padding: const EdgeInsets.only(bottom: 4),
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                SizedBox(
                  width: 34,
                  child: Text('${item.quantity}×', style: const TextStyle(fontWeight: FontWeight.w700)),
                ),
                Expanded(child: Text(item.name)),
              ]),
            ),
        ]),
      );
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.icon, required this.text, this.color});

  final IconData icon;
  final String text;
  final Color? color;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 6),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(icon, size: 18, color: color ?? ZayloColors.muted),
          const SizedBox(width: 8),
          Expanded(child: Text(text, style: color == null ? null : TextStyle(color: color))),
        ]),
      );
}

class _Amount extends StatelessWidget {
  const _Amount({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
        const SizedBox(height: 2),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w700)),
      ]);
}

class _Pill extends StatelessWidget {
  const _Pill({required this.label, required this.color, this.light = false});

  final String label;
  final Color color;

  /// White text on a solid pill, for dark backgrounds.
  final bool light;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: light ? color : color.withValues(alpha: .1),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(label,
            style: TextStyle(
              color: light ? ZayloColors.white : color,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            )),
      );
}
