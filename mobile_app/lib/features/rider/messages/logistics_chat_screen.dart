import 'package:flutter/material.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/chat/support_chat.dart';

class LogisticsChatScreen extends StatelessWidget {
  const LogisticsChatScreen({super.key, required this.api});
  final RiderApi api;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Logistics messages')),
    body: SupportChatView(load: api.logisticsMessages, send: api.sendLogisticsMessage,
      mediaHeaders: api.mediaHeaders, intro: 'Messages with your registered logistics partner.'),
  );
}
