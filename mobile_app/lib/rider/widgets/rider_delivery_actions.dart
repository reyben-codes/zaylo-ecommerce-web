import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/widgets/feedback.dart';
import '../../shared/chat/delivery_chat.dart';
import '../api/rider_api.dart';
import '../models/rider_dashboard_models.dart';

/// Accept / advance logic shared by every screen that lists deliveries.
/// The screen supplies [riderApi] and [reloadDeliveries]; the mixin tracks
/// which delivery's button is busy and reports results with a snackbar.
mixin RiderDeliveryActions<T extends StatefulWidget> on State<T> {
  RiderApi get riderApi;

  /// Refreshes the screen's lists after an action (or a failed one).
  Future<void> reloadDeliveries();

  /// The delivery whose button is waiting on the server, if any.
  int? busyDeliveryId;

  Future<void> acceptDelivery(RiderDelivery delivery) =>
      _run(delivery, () => riderApi.acceptDelivery(delivery.id), 'Delivery accepted. Head to the pickup point.');

  /// Moves [delivery] to its next status, confirming before "delivered".
  Future<void> advanceDelivery(RiderDelivery delivery) async {
    final next = delivery.nextStatus;
    if (next == null) return;
    if (next == 'delivered' && !await _confirmDelivered(delivery)) return;
    await _run(
        delivery,
        () => riderApi.updateDelivery(delivery.id, next),
        switch (next) {
          'picked_up' => 'Marked as picked up.',
          'in_transit' => 'Delivery started. Drive safely!',
          _ => 'Delivered. Nice work!',
        });
  }

  /// Opens the chat with [delivery]'s buyer, then refreshes so its unread badge clears.
  Future<void> openDeliveryChat(RiderDelivery delivery) async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => DeliveryChatScreen(
        title: 'Chat with buyer',
        load: () => riderApi.deliveryChat(delivery.id),
        send: (body, clientId) => riderApi.sendDeliveryMessage(delivery.id, body, clientId: clientId),
      ),
    ));
    if (mounted) await reloadDeliveries();
  }

  Future<bool> _confirmDelivered(RiderDelivery delivery) async =>
      await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Mark as delivered?'),
          content: Text(delivery.cashToCollect > 0
              ? 'Confirm the parcel was handed over and you collected the cash on delivery.'
              : 'Confirm the parcel was handed over to the recipient.'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Not yet')),
            TextButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('Delivered')),
          ],
        ),
      ) ??
      false;

  Future<void> _run(RiderDelivery delivery, Future<RiderDelivery> Function() action, String doneMessage) async {
    setState(() => busyDeliveryId = delivery.id);
    try {
      await action();
      if (!mounted) return;
      showMessage(context, doneMessage);
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Rider delivery action failed: ${error.debugDescription}');
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => busyDeliveryId = null);
    }
    // Refresh either way: on failure the delivery may have been taken by someone else.
    if (mounted) await reloadDeliveries();
  }
}
