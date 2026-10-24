/// Buyer–seller chat shapes: MobileChatController for the inbox, and the
/// website's ChatController JSON for the messages in a thread.
library;

import 'package:zaylo_mobile/models/json_parsing.dart';

/// One conversation in the buyer's inbox: a seller, about a product.
class SellerConversation {
  const SellerConversation({
    required this.id,
    required this.name,
    this.productName,
    this.productImageUrl,
    this.preview,
    this.lastMessageMine = false,
    this.unread = 0,
    this.updatedAt,
  });

  final int id;

  /// The seller's store name.
  final String name;
  final String? productName;
  final String? productImageUrl;

  /// The latest message, or null before anyone has written.
  final String? preview;
  final bool lastMessageMine;
  final int unread;
  final DateTime? updatedAt;

  factory SellerConversation.fromJson(Map<String, dynamic> json) => SellerConversation(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String? ?? 'Seller',
        productName: json['product_name'] as String?,
        productImageUrl: json['product_image_url'] as String?,
        preview: json['preview'] as String?,
        lastMessageMine: json['last_message_mine'] as bool? ?? false,
        unread: (json['unread'] as num?)?.toInt() ?? 0,
        updatedAt: parseDate(json['updated_at']),
      );
}

class SellerChatMessage {
  const SellerChatMessage({
    required this.clientId,
    required this.body,
    required this.mine,
    this.id,
    this.sentAt,
    this.pending = false,
    this.failed = false,
  });

  /// Null while the message is still being sent.
  final int? id;

  /// Chosen by the app so a retried send is stored only once.
  final String clientId;
  final String body;
  final bool mine;
  final DateTime? sentAt;
  final bool pending;
  final bool failed;

  SellerChatMessage copyWith({bool? pending, bool? failed}) => SellerChatMessage(
        id: id,
        clientId: clientId,
        body: body,
        mine: mine,
        sentAt: sentAt,
        pending: pending ?? this.pending,
        failed: failed ?? this.failed,
      );

  factory SellerChatMessage.fromJson(Map<String, dynamic> json, {String? clientId}) => SellerChatMessage(
        id: (json['id'] as num).toInt(),
        clientId: clientId ?? 'server-${json['id']}',
        body: json['body'] as String? ?? '',
        mine: json['mine'] as bool? ?? false,
        sentAt: parseDate(json['created_at']),
      );
}

/// GET /mobile/messages/{id}: messages after (or before) a message id.
class SellerChatBatch {
  const SellerChatBatch({required this.messages, this.hasOlder = false, this.typing = false});

  final List<SellerChatMessage> messages;
  final bool hasOlder;

  /// The seller is typing right now.
  final bool typing;

  factory SellerChatBatch.fromJson(Map<String, dynamic> json) => SellerChatBatch(
        messages: (json['messages'] as List<dynamic>? ?? const [])
            .map((item) => SellerChatMessage.fromJson(item as Map<String, dynamic>))
            .toList(),
        hasOlder: json['has_older'] as bool? ?? false,
        typing: json['typing'] as bool? ?? false,
      );
}

/// Someone typing to the buyer right now (GET /mobile/chat/typing), for the chat head.
class ChatTyping {
  const ChatTyping({required this.conversationId, required this.name});

  final int conversationId;
  final String name;

  factory ChatTyping.fromJson(Map<String, dynamic> json) => ChatTyping(
        conversationId: (json['conversation_id'] as num).toInt(),
        name: (json['name'] as String?)?.trim().isNotEmpty == true ? json['name'] as String : 'Someone',
      );
}
