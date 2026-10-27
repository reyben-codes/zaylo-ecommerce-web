import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/chat/delivery_chat.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/file_picking.dart';
import 'package:zaylo_mobile/features/rider/dashboard/rider_delivery_detail_screen.dart';
import 'package:zaylo_mobile/features/rider/dashboard/rider_scan_screen.dart';
import 'package:zaylo_mobile/features/rider/dashboard/widgets/rider_reason_sheet.dart';
import 'package:zaylo_mobile/models/rider/rider_dashboard_models.dart';

/// Delivery step logic shared by every screen that lists deliveries.
/// The screen supplies [riderApi] and [reloadDeliveries]; the mixin tracks
/// which delivery's button is busy and reports results with a snackbar.
mixin RiderDeliveryActions<T extends StatefulWidget> on State<T> {
  RiderApi get riderApi;

  /// Refreshes the screen's lists after an action (or a failed one).
  Future<void> reloadDeliveries();

  /// The delivery whose button is waiting on the server, if any.
  int? busyDeliveryId;

  /// Reasons for declining and failed attempts; screens update them from the dashboard.
  RiderReasons riderReasons = const RiderReasons();

  /// Takes a new assignment from the logistics partner.
  Future<void> acceptAssignment(RiderDelivery delivery) => _run(
        delivery,
        () => riderApi.acceptDelivery(delivery.id),
        'Accepted. Pick the parcel up at the hub, then scan it to start the delivery.',
      );

  /// Turns a new assignment down with a reason; the logistics partner gives it to another rider.
  Future<void> declineAssignment(RiderDelivery delivery) async {
    final choice = await askRiderReason(
      context,
      title: 'Decline this delivery?',
      message: 'Tell your logistics partner why, so they can assign ${delivery.orderNumber ?? delivery.trackingNumber} '
          'to another rider.',
      reasons: riderReasons.decline,
      confirmLabel: 'Decline delivery',
    );
    if (choice == null || !mounted) return;
    setState(() => busyDeliveryId = delivery.id);
    try {
      final message = await riderApi.declineDelivery(delivery.id, reason: choice.reason, note: choice.note);
      if (mounted) showMessage(context, message);
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Declining a delivery failed: ${error.debugDescription}');
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => busyDeliveryId = null);
    }
    if (mounted) await reloadDeliveries();
  }

  /// Opens a parcel's details: its stops, amounts and everything the rider did with it.
  Future<void> openDeliveryDetails(RiderDelivery delivery) async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => RiderDeliveryDetailScreen(api: riderApi, deliveryId: delivery.id, preview: delivery),
    ));
    if (mounted) await reloadDeliveries();
  }

  /// Moves [delivery] to [status] (its main next step by default). Starting
  /// and finishing a delivery need a scan of the parcel's QR code; "could not
  /// deliver" asks the rider why first.
  Future<void> advanceDelivery(RiderDelivery delivery, [String? status]) async {
    final next = status ?? delivery.nextStatus;
    if (next == null) return;
    final needsScan = delivery.actions.any((action) => action.status == next && action.needsScan);
    if (needsScan) {
      final code = await _scan(
        title: next == 'delivered' ? 'Confirm delivery' : 'Start delivery',
        hint: 'Scan the label of ${delivery.orderNumber ?? delivery.trackingNumber}.',
        accept: (code) => delivery.matchesCode(code)
            ? null
            : 'That label is for a different parcel. Scan ${delivery.trackingNumber}.',
      );
      if (code == null || !mounted) return;
      await _sendScan(code, delivery);
      return;
    }
    if (next != 'failed') return;
    final choice = await askRiderReason(
      context,
      title: 'Could not deliver?',
      message: 'The buyer and seller will be told why. If the buyer cancelled or refused the parcel, your logistics '
          'partner returns it to the seller; otherwise you can try again later.',
      reasons: riderReasons.failed,
      confirmLabel: 'Report failed delivery',
    );
    if (choice == null || !mounted) return;
    await _run(
        delivery,
        () => riderApi.updateDelivery(delivery.id, next, reason: choice.reason, note: choice.note),
        'Marked as not delivered. The buyer and seller were told why.');
  }

  /// Scans any parcel's waybill: one of [active] goes out for delivery, or is
  /// delivered if it already was. The server checks the parcel is the rider's.
  Future<void> scanAnyParcel(List<RiderDelivery> active) async {
    final code = await _scan(title: 'Scan parcel', hint: 'Scan a parcel to start or confirm its delivery.');
    if (code == null || !mounted) return;
    await _sendScan(code, active.where((delivery) => delivery.matchesCode(code)).firstOrNull);
  }

  Future<String?> _scan({required String title, required String hint, String? Function(String code)? accept}) =>
      Navigator.of(context).push<String>(MaterialPageRoute(
        builder: (_) => RiderScanScreen(title: title, hint: hint, accept: accept),
      ));

  /// Sends a scanned [code]. Handing over a parcel ([delivery] out for
  /// delivery) is confirmed first, with a cash reminder for COD.
  Future<void> _sendScan(String code, RiderDelivery? delivery) async {
    if (delivery?.nextStatus == 'delivered' && !await _confirmDelivered(delivery!)) return;
    if (!mounted) return;
    setState(() => busyDeliveryId = delivery?.id ?? -1);
    RiderDelivery? delivered;
    try {
      // A typed code may differ in case from the label; send the parcel's own code when it is known.
      final result = await riderApi.scanParcel(delivery == null || delivery.scanCode.isEmpty ? code : delivery.scanCode);
      if (mounted) showMessage(context, result.message);
      delivered = result.step == 'delivered' && result.delivery.canAddDeliveryPhoto ? result.delivery : null;
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Parcel scan failed: ${error.debugDescription}');
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => busyDeliveryId = null);
    }
    // Proof of delivery: ask for a photo of the parcel at the door right away.
    if (delivered != null && mounted) await addDeliveryPhoto(delivered, afterDelivery: true);
    if (mounted) await reloadDeliveries();
  }

  /// Takes a camera photo of the delivered parcel and sends it as proof of
  /// delivery; the seller and buyer can open it from the order. Right after a
  /// delivery ([afterDelivery]) the rider is asked first and may skip it.
  Future<void> addDeliveryPhoto(RiderDelivery delivery, {bool afterDelivery = false}) async {
    if (afterDelivery && !await _wantsDeliveryPhoto()) return;
    final File? photo;
    try {
      final picked = await ImagePicker().pickImage(
        source: ImageSource.camera,
        maxWidth: 2000,
        maxHeight: 2000,
        imageQuality: 80,
      );
      photo = picked == null ? null : File(picked.path);
    } on Exception catch (error) {
      debugPrint('[ZAYLO] Camera failed: $error');
      if (mounted) showMessage(context, "Could not open the camera. Check the app's permissions.");
      return;
    }
    if (photo == null || !mounted) return;
    final problem = uploadFileError(photo, extensions: const ['jpg', 'jpeg', 'png', 'webp']);
    if (problem != null) {
      showMessage(context, problem);
      return;
    }
    setState(() => busyDeliveryId = delivery.id);
    try {
      final message = await riderApi.uploadDeliveryPhoto(delivery.id, photo);
      if (mounted) showMessage(context, message);
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Delivery photo upload failed: ${error.debugDescription}');
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => busyDeliveryId = null);
    }
  }

  Future<bool> _wantsDeliveryPhoto() async =>
      await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Take a delivery photo?'),
          content: const Text('Take a photo of the parcel with the recipient or at the door. '
              'The seller and buyer see it as proof of delivery.'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Skip')),
            TextButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('Take photo')),
          ],
        ),
      ) ??
      false;

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
