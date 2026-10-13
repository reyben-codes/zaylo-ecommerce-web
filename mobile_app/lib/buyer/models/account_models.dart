import '../../core/models/auth_models.dart';
import '../../core/models/json_parsing.dart';

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
