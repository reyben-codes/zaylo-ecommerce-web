import 'package:flutter/material.dart';

import 'package:zaylo_mobile/components/chat/support_chat.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/state/chat_head_state.dart';

/// Help & support: the buyer's chat with the ZAYLO team, the same thread as on the website.
class SupportScreen extends StatelessWidget {
  const SupportScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = AppScope.read(context).api;
    return HideChatHead(
      child: Scaffold(
        appBar: AppBar(title: const Text('Help & support')),
        body: SupportChatView(
          load: api.supportMessages,
          send: (body, clientId) => api.sendSupportMessage(body, clientId: clientId),
          intro: 'Message the ZAYLO team about orders, payments, refunds, or your account. '
              'Include the order number if it is about an order.',
        ),
      ),
    );
  }
}
