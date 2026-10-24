/// Shapes shared by the buyer and rider apps: ZAYLO support chat, complaints,
/// policies and admin announcements. Each mirrors app/Support/MobilePayload.php
/// or the mobile controller that returns it.
library;

import 'package:zaylo_mobile/models/json_parsing.dart';

/// A message in the user's chat with ZAYLO support (MobileSupportController).
class SupportMessage {
  const SupportMessage({
    required this.clientId,
    required this.body,
    required this.fromStaff,
    this.id,
    this.sentAt,
    this.pending = false,
    this.failed = false,
  });

  /// Null until the server has stored it.
  final int? id;

  /// Chosen by the app so a retried send is stored only once.
  final String clientId;
  final String body;
  final bool fromStaff;
  final DateTime? sentAt;

  /// Shown immediately while sending, then replaced by the server copy.
  final bool pending;
  final bool failed;

  SupportMessage copyWith({bool? pending, bool? failed}) => SupportMessage(
        id: id,
        clientId: clientId,
        body: body,
        fromStaff: fromStaff,
        sentAt: sentAt,
        pending: pending ?? this.pending,
        failed: failed ?? this.failed,
      );

  factory SupportMessage.fromJson(Map<String, dynamic> json) => SupportMessage(
        id: json['id'] as int?,
        clientId: json['client_id'] as String? ?? '',
        body: json['body'] as String? ?? '',
        fromStaff: json['from_staff'] as bool? ?? false,
        sentAt: parseDate(json['sent_at']),
      );
}

/// A notice from the ZAYLO admin. [key] changes when the admin edits it, so a
/// dismissed notice returns after an update, as on the website.
class Announcement {
  const Announcement({required this.key, required this.title, required this.body});

  final String key;
  final String title;
  final String body;

  factory Announcement.fromJson(Map<String, dynamic> json) => Announcement(
        key: json['key'] as String? ?? '',
        title: json['title'] as String? ?? '',
        body: json['body'] as String? ?? '',
      );

  static List<Announcement> listFrom(Object? list) => (list as List<dynamic>? ?? const [])
      .map((item) => Announcement.fromJson(item as Map<String, dynamic>))
      .toList();
}

class PolicySummary {
  const PolicySummary({required this.slug, required this.title});

  final String slug;
  final String title;

  factory PolicySummary.fromJson(Map<String, dynamic> json) =>
      PolicySummary(slug: json['slug'] as String, title: json['title'] as String);
}

/// Policy text as the admin writes it: "## " lines are headings, "- " lines
/// are bullets, and blank lines separate paragraphs.
class Policy {
  const Policy({required this.slug, required this.title, required this.body, this.updatedAt, this.url});

  final String slug;
  final String title;
  final String body;
  final DateTime? updatedAt;

  /// The same page on the website.
  final String? url;

  factory Policy.fromJson(Map<String, dynamic> json) => Policy(
        slug: json['slug'] as String,
        title: json['title'] as String,
        body: json['body'] as String? ?? '',
        updatedAt: parseDate(json['updated_at']),
        url: json['url'] as String?,
      );
}

/// A file attached to a complaint. [url] needs the user's sign-in token.
class ComplaintFile {
  const ComplaintFile({required this.name, required this.url, required this.isImage});

  final String name;
  final String url;
  final bool isImage;

  factory ComplaintFile.fromJson(Map<String, dynamic> json) => ComplaintFile(
        name: json['name'] as String? ?? 'File',
        url: json['url'] as String,
        isImage: json['is_image'] as bool? ?? false,
      );
}

class ComplaintMessage {
  const ComplaintMessage({
    required this.id,
    required this.body,
    required this.mine,
    required this.sender,
    this.sentAt,
    this.attachment,
  });

  final int id;
  final String body;
  final bool mine;

  /// "Maria · Buyer", "Jun · Rider", or "ZAYLO team".
  final String sender;
  final DateTime? sentAt;
  final ComplaintFile? attachment;

  factory ComplaintMessage.fromJson(Map<String, dynamic> json) => ComplaintMessage(
        id: (json['id'] as num).toInt(),
        body: json['body'] as String? ?? '',
        mine: json['mine'] as bool? ?? false,
        sender: json['sender'] as String? ?? '',
        sentAt: parseDate(json['sent_at']),
        attachment: json['attachment'] is Map<String, dynamic>
            ? ComplaintFile.fromJson(json['attachment'] as Map<String, dynamic>)
            : null,
      );
}

/// A complaint about an order (a dispute on the website). The list endpoint
/// sends the summary fields; the case endpoint adds the rest.
class Complaint {
  const Complaint({
    required this.id,
    required this.categoryLabel,
    required this.status,
    required this.statusLabel,
    required this.closed,
    this.orderId,
    this.orderNumber,
    this.openedAt,
    this.updatedAt,
    this.details,
    this.resolution,
    this.canReply = false,
    this.evidence = const [],
    this.messages = const [],
  });

  final int id;
  final int? orderId;
  final String? orderNumber;
  final String categoryLabel;

  /// open, in_review, resolved or rejected.
  final String status;
  final String statusLabel;
  final bool closed;
  final DateTime? openedAt;
  final DateTime? updatedAt;

  final String? details;

  /// The ZAYLO team's decision, once the case is closed.
  final String? resolution;
  final bool canReply;
  final List<ComplaintFile> evidence;
  final List<ComplaintMessage> messages;

  factory Complaint.fromJson(Map<String, dynamic> json) => Complaint(
        id: (json['id'] as num).toInt(),
        orderId: (json['order_id'] as num?)?.toInt(),
        orderNumber: json['order_number'] as String?,
        categoryLabel: json['category_label'] as String? ?? 'Complaint',
        status: json['status'] as String? ?? 'open',
        statusLabel: json['status_label'] as String? ?? 'Open',
        closed: json['closed'] as bool? ?? false,
        openedAt: parseDate(json['opened_at']),
        updatedAt: parseDate(json['updated_at']),
        details: json['details'] as String?,
        resolution: json['resolution'] as String?,
        canReply: json['can_reply'] as bool? ?? false,
        evidence: (json['evidence'] as List<dynamic>? ?? const [])
            .map((item) => ComplaintFile.fromJson(item as Map<String, dynamic>))
            .toList(),
        messages: (json['messages'] as List<dynamic>? ?? const [])
            .map((item) => ComplaintMessage.fromJson(item as Map<String, dynamic>))
            .toList(),
      );
}

class ComplaintCategory {
  const ComplaintCategory({required this.id, required this.label});

  final String id;
  final String label;

  factory ComplaintCategory.fromJson(Map<String, dynamic> json) =>
      ComplaintCategory(id: json['id'] as String, label: json['label'] as String);
}
