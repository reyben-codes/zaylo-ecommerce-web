import 'package:zaylo_mobile/models/auth_models.dart';
import 'package:zaylo_mobile/models/json_parsing.dart';

class ProfileUpdate {
  const ProfileUpdate({
    required this.message,
    required this.emailVerificationRequired,
    required this.user,
  });

  final String message;
  final bool emailVerificationRequired;
  final UserProfile user;
}

class AppNotification {
  const AppNotification({
    required this.key,
    required this.type,
    required this.title,
    required this.message,
    required this.action,
    required this.read,
    this.at,
    this.productSlug,
  });

  final String key;
  final String type;
  final String title;
  final String message;
  final String action;
  final bool read;
  final DateTime? at;
  final String? productSlug;

  factory AppNotification.fromJson(Map<String, dynamic> json) => AppNotification(
        key: json['key'] as String,
        type: json['type'] as String,
        title: json['title'] as String,
        message: json['message'] as String,
        action: json['action'] as String,
        read: json['read'] as bool? ?? false,
        at: parseDate(json['at']),
        productSlug: json['product_slug'] as String?,
      );
}

class NotificationFeed {
  const NotificationFeed({required this.notifications, required this.unreadCount});

  final List<AppNotification> notifications;
  final int unreadCount;

  factory NotificationFeed.fromJson(Map<String, dynamic> json) => NotificationFeed(
        notifications: (json['notifications'] as List<dynamic>)
            .map((item) => AppNotification.fromJson(item as Map<String, dynamic>))
            .toList(),
        unreadCount: (json['unread_count'] as num).toInt(),
      );
}

class IdType {
  const IdType({required this.id, required this.label});

  final String id;
  final String label;

  factory IdType.fromJson(Map<String, dynamic> json) => IdType(id: json['id'] as String, label: json['label'] as String);
}

/// The buyer's latest ID upload and its review.
class IdentitySubmission {
  const IdentitySubmission({required this.status, required this.idTypeLabel, this.rejectionReason, this.submittedAt});

  /// pending, approved or rejected.
  final String status;
  final String idTypeLabel;
  final String? rejectionReason;
  final DateTime? submittedAt;

  factory IdentitySubmission.fromJson(Map<String, dynamic> json) => IdentitySubmission(
        status: json['status'] as String? ?? 'pending',
        idTypeLabel: json['id_type_label'] as String? ?? '',
        rejectionReason: json['rejection_reason'] as String?,
        submittedAt: parseDate(json['submitted_at']),
      );
}

/// GET /mobile/account/identity: whether the buyer can check out, and how to verify.
class IdentityStatus {
  const IdentityStatus({required this.enabled, required this.verified, required this.idTypes, this.submission});

  /// False until the server's ID verification migration runs; nobody is blocked then.
  final bool enabled;
  final bool verified;
  final IdentitySubmission? submission;
  final List<IdType> idTypes;

  bool get pending => !verified && submission?.status == 'pending';
  bool get rejected => !verified && submission?.status == 'rejected';

  factory IdentityStatus.fromJson(Map<String, dynamic> json) => IdentityStatus(
        enabled: json['enabled'] as bool? ?? true,
        verified: json['verified'] as bool? ?? false,
        submission: json['submission'] is Map<String, dynamic>
            ? IdentitySubmission.fromJson(json['submission'] as Map<String, dynamic>)
            : null,
        idTypes: (json['id_types'] as List<dynamic>? ?? const [])
            .map((item) => IdType.fromJson(item as Map<String, dynamic>))
            .toList(),
      );
}
