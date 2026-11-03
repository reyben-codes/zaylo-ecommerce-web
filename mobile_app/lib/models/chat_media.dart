/// Photos and videos in chats, shared by every chat in the app: with sellers,
/// with ZAYLO support, and between the rider and the buyer. Mirrors
/// App\Services\ChatMedia on the server.
library;

import 'dart:io';

/// The server's limits: photos up to 5 MB (the picker keeps them far smaller)
/// and videos up to 25 MB.
const chatPhotoMaxBytes = 5 * 1024 * 1024;
const chatVideoMaxBytes = 25 * 1024 * 1024;

/// What a sent message carries: links to its photo or video, if any. Links to
/// ZAYLO need the session's headers; a video may instead be a signed storage
/// link, which must be opened without them.
class ChatMedia {
  const ChatMedia({this.photoUrl, this.videoUrl});

  final String? photoUrl;
  final String? videoUrl;

  bool get isEmpty => photoUrl == null && videoUrl == null;

  static ChatMedia fromJson(Map<String, dynamic> json) =>
      ChatMedia(photoUrl: json['photo'] as String?, videoUrl: json['video'] as String?);
}

/// A photo or video picked on this phone, waiting to be sent (or retried).
class ChatAttachment {
  const ChatAttachment(this.file, {required this.isVideo});

  final File file;
  final bool isVideo;

  /// The form field the server reads it from.
  Map<String, File> get field => {isVideo ? 'video' : 'photo': file};
}
