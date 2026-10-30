import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/features/buyer/messages/seller_chat_screen.dart';
import 'package:zaylo_mobile/models/buyer/seller_chat_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/state/chat_head_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

/// The buyer's conversations with sellers, most recent first.
class SellerInboxScreen extends StatefulWidget {
  const SellerInboxScreen({super.key});

  @override
  State<SellerInboxScreen> createState() => _SellerInboxScreenState();
}

class _SellerInboxScreenState extends State<SellerInboxScreen> {
  Future<List<SellerConversation>>? _conversations;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_conversations == null) {
      final state = AppScope.read(context);
      _conversations = state.api.sellerConversations();
    }
  }

  Future<void> _reload() async {
    final conversations = AppScope.read(context).api.sellerConversations();
    setState(() => _conversations = conversations);
    try {
      await conversations;
    } on ApiException {
      // Shown by the FutureBuilder's error state.
    }
  }

  Future<void> _open(SellerConversation conversation) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => SellerChatScreen(conversation: conversation)));
    if (mounted) {
      _reload();
      AppScope.read(context).refreshChatUnread();
    }
  }

  @override
  Widget build(BuildContext context) => HideChatHead(
        child: Scaffold(
          appBar: AppBar(title: const Text('Messages')),
          body: FutureBuilder<List<SellerConversation>>(
            future: _conversations,
            builder: (context, snapshot) {
              if (snapshot.hasError) {
                return LoadError(error: snapshot.error, onRetry: _reload, title: 'Could not load your messages');
              }
              if (!snapshot.hasData) return const Loader();
              final conversations = snapshot.requireData;
              if (conversations.isEmpty) {
                return const EmptyState(
                  icon: Icons.forum_outlined,
                  title: 'No messages yet',
                  message: 'Use “Chat with seller” on a product page to ask about sizes, stock or delivery.',
                );
              }
              return RefreshIndicator(
                color: ZayloColors.ink,
                onRefresh: _reload,
                child: ListView.separated(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  itemCount: conversations.length,
                  separatorBuilder: (_, __) => Divider(height: 1, indent: 76, color: ZayloColors.line),
                  itemBuilder: (context, index) {
                    final chat = conversations[index];
                    final unread = chat.unread > 0;
                    return ListTile(
                      contentPadding: const EdgeInsets.symmetric(horizontal: ZayloSpace.page, vertical: 4),
                      leading: ClipRRect(
                        borderRadius: BorderRadius.circular(10),
                        child: SizedBox(width: 46, height: 46, child: NetworkImageBox(url: chat.productImageUrl)),
                      ),
                      title: Text(chat.name,
                          style: TextStyle(fontWeight: unread ? FontWeight.w800 : FontWeight.w600, color: ZayloColors.ink)),
                      subtitle: Text(
                        chat.preview == null
                            ? 'About ${chat.productName ?? 'a product'}'
                            : '${chat.lastMessageMine ? 'You: ' : ''}${chat.preview}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(fontSize: 13, color: unread ? ZayloColors.ink : ZayloColors.muted),
                      ),
                      trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
                        Text(relativeTime(chat.updatedAt), style: TextStyle(fontSize: 11, color: ZayloColors.muted)),
                        if (unread) ...[
                          const SizedBox(height: 4),
                          Badge(label: Text('${chat.unread}'), backgroundColor: Colors.redAccent),
                        ],
                      ]),
                      onTap: () => _open(chat),
                    );
                  },
                ),
              );
            },
          ),
        ),
      );
}
