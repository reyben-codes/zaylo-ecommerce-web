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
    this.returnInfo,
  });

  final int id;
  final int? orderId;
  final String? orderNumber;
  final String categoryLabel;

  /// The item being sent back, when the buyer asked for a return.
  final ComplaintReturn? returnInfo;

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
        returnInfo: json['return'] is Map<String, dynamic>
            ? ComplaintReturn.fromJson(json['return'] as Map<String, dynamic>)
            : null,
      );
}

/// A returned item on its way back: a rider collects it (or the buyer drops it
/// off at the hub), the logistics partner brings it to the seller, who refunds.
class ComplaintReturn {
  const ComplaintReturn({
    required this.trackingNumber,
    required this.typeLabel,
    required this.method,
    required this.methodLabel,
    required this.status,
    required this.statusLabel,
    this.pickupDate,
    this.pickupSlot,
    this.dropoffLocation,
    this.dropoffDeadline,
    this.riderName,
    this.refundAmount,
    this.steps = const [],
    this.barcode = const [],
    this.next,
  });

  final String trackingNumber;

  /// What the person viewing the return does now (or where it stands when it is finished).
  final String? next;

  /// Code 128 bar and space widths (starting with a bar) for the rider's scanner.
  final List<int> barcode;
  final String typeLabel;

  /// pickup or dropoff.
  final String? method;
  final String methodLabel;
  final String status;
  final String statusLabel;
  final DateTime? pickupDate;

  /// morning or afternoon.
  final String? pickupSlot;
  final String? dropoffLocation;
  final DateTime? dropoffDeadline;

  /// First name of the rider picking it up, once the logistics partner assigns one.
  final String? riderName;
  final double? refundAmount;
  final List<ReturnStep> steps;

  bool get isPickup => method != 'dropoff';

  factory ComplaintReturn.fromJson(Map<String, dynamic> json) => ComplaintReturn(
        trackingNumber: json['tracking_number'] as String? ?? '',
        barcode: (json['barcode'] as List<dynamic>? ?? const []).map((width) => (width as num).toInt()).toList(),
        typeLabel: json['type_label'] as String? ?? 'Return and Refund',
        method: json['method'] as String?,
        methodLabel: json['method_label'] as String? ?? 'Pickup',
        status: json['status'] as String? ?? 'scheduled',
        statusLabel: json['status_label'] as String? ?? 'Waiting for pickup',
        pickupDate: parseDate(json['pickup_date']),
        pickupSlot: json['pickup_slot'] as String?,
        dropoffLocation: json['dropoff_location'] as String?,
        dropoffDeadline: parseDate(json['dropoff_deadline']),
        riderName: json['rider_name'] as String?,
        refundAmount: (json['refund_amount'] as num?)?.toDouble(),
        next: json['next'] as String?,
        steps: (json['steps'] as List<dynamic>? ?? const [])
            .map((item) => ReturnStep.fromJson(item as Map<String, dynamic>))
            .toList(),
      );
}

/// One step of a return, the same on the website: when it happened and whether it is the one in progress.
class ReturnStep {
  const ReturnStep({
    required this.key,
    required this.label,
    required this.done,
    this.current = false,
    this.detail,
    this.at,
  });

  final String key;
  final String label;
  final bool done;

  /// The first step not done yet.
  final bool current;

  /// For example the rider's name, or the refunded amount.
  final String? detail;
  final DateTime? at;

  factory ReturnStep.fromJson(Map<String, dynamic> json) => ReturnStep(
        key: json['key'] as String? ?? '',
        label: json['label'] as String? ?? '',
        done: json['done'] as bool? ?? false,
        current: json['current'] as bool? ?? false,
        detail: json['detail'] as String?,
        at: DateTime.tryParse(json['at'] as String? ?? '')?.toLocal(),
      );
}

class ComplaintCategory {
  const ComplaintCategory({required this.id, required this.label});

  final String id;
  final String label;

  factory ComplaintCategory.fromJson(Map<String, dynamic> json) =>
      ComplaintCategory(id: json['id'] as String, label: json['label'] as String);
}

/// The newest published app (MobileAppVersionController), which the app
/// compares with its own version when it opens.
class AppVersionInfo {
  const AppVersionInfo({
    this.latestVersion,
    this.downloadUrl,
    this.releaseNotes = const [],
  });

  /// "1.0.4", or null when the server could not tell.
  final String? latestVersion;
  final String? downloadUrl;
  final List<String> releaseNotes;

  /// Whether [latestVersion] is newer than [current] ("1.0.3"); false when either is unknown.
  bool isNewerThan(String current) {
    final latest = _parts(latestVersion);
    final mine = _parts(current);
    if (latest == null || mine == null) return false;
    for (var index = 0; index < 3; index++) {
      if (latest[index] != mine[index]) return latest[index] > mine[index];
    }
    return false;
  }

  static List<int>? _parts(String? version) {
    final match = RegExp(r'^v?(\d+)\.(\d+)\.(\d+)').firstMatch(version?.trim() ?? '');
    return match == null ? null : [for (var group = 1; group <= 3; group++) int.parse(match.group(group)!)];
  }

  factory AppVersionInfo.fromJson(Map<String, dynamic> json) => AppVersionInfo(
        latestVersion: json['latest_version'] as String?,
        downloadUrl: json['download_url'] as String?,
        releaseNotes: List<String>.from(json['release_notes'] as List<dynamic>? ?? const []),
      );
}

/// The help bot's questions and answers from GET /mobile/faq.
class FaqData {
  const FaqData({required this.greeting, this.items = const []});

  final String greeting;
  final List<FaqItem> items;

  factory FaqData.fromJson(Map<String, dynamic> json) => FaqData(
        greeting: json['greeting'] as String? ?? 'Hi! Pick a question below or type your own.',
        items: (json['items'] as List<dynamic>? ?? const [])
            .map((item) => FaqItem.fromJson(item as Map<String, dynamic>))
            .toList(),
      );

  /// Questions that match [text], best first, with their scores: a matching
  /// keyword counts most, words shared with the question a little (the same
  /// rules as the website's bot).
  List<(FaqItem, int)> match(String text) {
    final typed = ' ${_normalize(text)} ';
    final typedWords = _words(text).toSet();
    final ranked = [
      for (final item in items)
        (
          item,
          item.keywords.fold<int>(0, (total, keyword) {
                final key = _normalize(keyword);
                return typed.contains(' $key ') || typed.contains(' $key') ? total + (keyword.contains(' ') ? 4 : 3) : total;
              }) +
              _words(item.question).where(typedWords.contains).length,
        ),
    ].where((entry) => entry.$2 > 0).toList()
      ..sort((a, b) => b.$2.compareTo(a.$2));
    return ranked;
  }

  static const _stopWords = {
    'how', 'what', 'when', 'where', 'why', 'who', 'can', 'the', 'and', 'for', 'my', 'do', 'does', 'did', 'is', //
    'are', 'a', 'an', 'i', 'to', 'of', 'in', 'on', 'it', 'me', 'you', 'your', 'with', 'or', 'be', 'get', 'not', //
    'there', 'this', 'that',
  };

  static String _normalize(String text) =>
      text.toLowerCase().replaceAll(RegExp(r"[^a-z0-9'\-\s]"), ' ').replaceAll(RegExp(r'\s+'), ' ').trim();

  static List<String> _words(String text) =>
      _normalize(text).split(' ').where((word) => word.length > 2 && !_stopWords.contains(word)).toList();
}

class FaqItem {
  const FaqItem({required this.question, required this.answer, this.keywords = const []});

  final String question;
  final String answer;
  final List<String> keywords;

  factory FaqItem.fromJson(Map<String, dynamic> json) => FaqItem(
        question: json['q'] as String? ?? '',
        answer: json['a'] as String? ?? '',
        keywords: List<String>.from(json['keywords'] as List<dynamic>? ?? const []),
      );
}
