import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/chat/support_chat.dart';
import 'package:zaylo_mobile/features/buyer/account/notifications_screen.dart';
import 'package:zaylo_mobile/features/buyer/account/support_screen.dart';
import 'package:zaylo_mobile/features/buyer/messages/seller_chat_screen.dart';
import 'package:zaylo_mobile/features/buyer/messages/seller_inbox_screen.dart';
import 'package:zaylo_mobile/features/buyer/orders/order_detail_screen.dart';
import 'package:zaylo_mobile/features/rider/dashboard/rider_delivery_detail_screen.dart';
import 'package:zaylo_mobile/features/rider/history/rider_attendance_screen.dart';
import 'package:zaylo_mobile/features/rider/messages/logistics_chat_screen.dart';
import 'package:zaylo_mobile/features/rider/dashboard/rider_pickup_queue_screen.dart';
import 'package:zaylo_mobile/notifications/phone_notifications_screen.dart';
import 'package:zaylo_mobile/notifications/push_notifications.dart';
import 'package:zaylo_mobile/state/app_state.dart';

Future<Widget?> pushDestination(PushTap tap, AppState state) async {
  if (tap.session.rider) {
    final api = tap.session.api;
    if (api is! RiderApi || api.token == null) return null;
    return switch (tap.kind) {
      'delivery' when tap.entityId > 0 => RiderDeliveryDetailScreen(api: api, deliveryId: tap.entityId),
      'rider_schedule' => RiderAttendanceScreen(api: api),
      'logistics_chat' => LogisticsChatScreen(api: api),
      'rider_pickups' => RiderPickupQueueScreen(api: api),
      'support' => Scaffold(appBar: AppBar(title: const Text('ZAYLO support')), body: SupportChatView(
          load: api.supportMessages, send: (body, clientId, attachment) => api.sendSupportMessage(body, clientId: clientId, attachment: attachment), mediaHeaders: api.mediaHeaders,
          intro: 'Messages with the ZAYLO support team.')),
      _ => PhoneNotificationsScreen(api: api),
    };
  }
  if (!state.signedIn || state.user?.id != tap.session.userId || state.api != tap.session.api) return null;
  if (tap.kind == 'order' && tap.entityId > 0) return OrderDetailScreen(orderId: tap.entityId);
  if (tap.kind == 'seller_chat') {
    final conversations = await state.api.sellerConversations();
    for (final conversation in conversations) {
      if (conversation.id == tap.entityId) return SellerChatScreen(conversation: conversation);
    }
    return const SellerInboxScreen();
  }
  if (tap.kind == 'support') return const SupportScreen();
  return const NotificationsScreen();
}
