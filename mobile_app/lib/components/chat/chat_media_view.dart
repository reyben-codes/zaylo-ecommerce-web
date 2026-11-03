import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:video_player/video_player.dart';

import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/models/chat_media.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// The headers to open a media link with: the session's for ZAYLO's own links,
/// none for a signed storage link (which refuses a second sign-in).
typedef MediaHeaders = Map<String, String> Function(String url);

enum _Pick { takePhoto, recordVideo, choosePhoto, chooseVideo }

/// Asks for a photo or video from the camera or gallery. Photos are resized and
/// saved as JPG by the picker; a video over 25 MB is turned away with a
/// message. Returns null when the user cancels.
Future<ChatAttachment?> pickChatAttachment(BuildContext context) async {
  final pick = await showModalBottomSheet<_Pick>(
    context: context,
    backgroundColor: ZayloColors.white,
    builder: (sheetContext) => SafeArea(
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        for (final (choice, icon, label) in const [
          (_Pick.takePhoto, Icons.photo_camera_outlined, 'Take photo'),
          (_Pick.recordVideo, Icons.videocam_outlined, 'Record video'),
          (_Pick.choosePhoto, Icons.photo_library_outlined, 'Choose photo'),
          (_Pick.chooseVideo, Icons.video_library_outlined, 'Choose video'),
        ])
          ListTile(leading: Icon(icon), title: Text(label), onTap: () => Navigator.pop(sheetContext, choice)),
      ]),
    ),
  );
  if (pick == null || !context.mounted) return null;

  final video = pick == _Pick.recordVideo || pick == _Pick.chooseVideo;
  final source = pick == _Pick.takePhoto || pick == _Pick.recordVideo ? ImageSource.camera : ImageSource.gallery;
  try {
    final file = video
        ? await ImagePicker().pickVideo(source: source, maxDuration: const Duration(minutes: 3))
        : await ImagePicker().pickImage(source: source, maxWidth: 2000, maxHeight: 2000, imageQuality: 80);
    if (file == null) return null;
    final picked = File(file.path);
    if (picked.lengthSync() > (video ? chatVideoMaxBytes : chatPhotoMaxBytes)) {
      if (context.mounted) {
        showMessage(context,
            video ? 'The video must be 25 MB or smaller. Try a shorter clip.' : 'The photo must be 5 MB or smaller.');
      }
      return null;
    }
    return ChatAttachment(picked, isVideo: video);
  } on Exception catch (error) {
    // Usually a denied camera, microphone or photo permission.
    debugPrint('[ZAYLO] Chat attachment (${pick.name}) failed: $error');
    if (context.mounted) showMessage(context, "Could not open the ${source.name}. Check the app's permissions.");
    return null;
  }
}

/// The paperclip beside a chat's message box.
class ChatAttachButton extends StatelessWidget {
  const ChatAttachButton({super.key, required this.onPressed});

  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => IconButton(
        tooltip: 'Add photo or video',
        onPressed: onPressed,
        icon: const Icon(Icons.attach_file),
      );
}

/// The picked photo or video above the message box, with a button to remove it.
class ChatAttachmentChip extends StatelessWidget {
  const ChatAttachmentChip({super.key, required this.attachment, required this.onRemove});

  final ChatAttachment attachment;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final megabytes = attachment.file.lengthSync() / (1024 * 1024);
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: SizedBox.square(
            dimension: 44,
            child: attachment.isVideo
                ? ColoredBox(color: ZayloColors.ink, child: Icon(Icons.videocam, color: ZayloColors.white))
                : Image.file(attachment.file, fit: BoxFit.cover),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            '${attachment.isVideo ? 'Video' : 'Photo'} · ${megabytes.toStringAsFixed(1)} MB',
            style: TextStyle(fontSize: 13, color: ZayloColors.muted),
          ),
        ),
        IconButton(tooltip: 'Remove attachment', onPressed: onRemove, icon: const Icon(Icons.close)),
      ]),
    );
  }
}

/// A message's photo or video inside its bubble. Shows the server's copy once
/// sent, or the picked file while sending. Tapping opens it full screen.
class ChatMediaThumbnail extends StatelessWidget {
  const ChatMediaThumbnail({super.key, required this.media, required this.headers, this.local});

  final ChatMedia media;
  final ChatAttachment? local;
  final MediaHeaders headers;

  static const _maxSide = 220.0;

  @override
  Widget build(BuildContext context) {
    final local = this.local;
    final video = local?.isVideo ?? media.videoUrl != null;
    final url = media.videoUrl ?? media.photoUrl;
    if (local == null && url == null) return const SizedBox.shrink();

    final Widget content = video
        ? Container(
            width: _maxSide,
            height: _maxSide * 9 / 16,
            color: Colors.black,
            alignment: Alignment.center,
            child: const Icon(Icons.play_circle_fill, color: Colors.white, size: 48),
          )
        : local != null
            ? Image.file(local.file, fit: BoxFit.cover)
            : Image.network(
                url!,
                headers: headers(url),
                fit: BoxFit.cover,
                loadingBuilder: (context, child, progress) => progress == null
                    ? child
                    : const SizedBox.square(
                        dimension: 120, child: Center(child: CircularProgressIndicator(strokeWidth: 2))),
                errorBuilder: (context, _, __) => SizedBox.square(
                  dimension: 120,
                  child: Icon(Icons.broken_image_outlined, color: ZayloColors.muted),
                ),
              );

    return Semantics(
      button: true,
      label: video ? 'Play video' : 'Open photo',
      child: GestureDetector(
        onTap: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => video
              ? ChatVideoScreen(
                  url: media.videoUrl,
                  file: local?.file,
                  headers: media.videoUrl == null ? const {} : headers(media.videoUrl!))
              : _ChatPhotoScreen(
                  url: media.photoUrl,
                  file: local?.file,
                  headers: media.photoUrl == null ? const {} : headers(media.photoUrl!)),
        )),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: _maxSide, maxHeight: _maxSide),
            child: content,
          ),
        ),
      ),
    );
  }
}

class _ChatPhotoScreen extends StatelessWidget {
  const _ChatPhotoScreen({this.url, this.file, required this.headers});

  final String? url;
  final File? file;
  final Map<String, String> headers;

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: Colors.black,
        appBar: AppBar(backgroundColor: Colors.black, foregroundColor: Colors.white, title: const Text('Photo')),
        body: Center(
          child: InteractiveViewer(
            maxScale: 5,
            child: file != null ? Image.file(file!) : Image.network(url!, headers: headers),
          ),
        ),
      );
}

/// Plays a chat video full screen. Tap the video to pause or play; drag the
/// bar to skip through it.
class ChatVideoScreen extends StatefulWidget {
  const ChatVideoScreen({super.key, this.url, this.file, this.headers = const {}});

  final String? url;
  final File? file;
  final Map<String, String> headers;

  @override
  State<ChatVideoScreen> createState() => _ChatVideoScreenState();
}

class _ChatVideoScreenState extends State<ChatVideoScreen> {
  late final VideoPlayerController _controller = widget.file != null
      ? VideoPlayerController.file(widget.file!)
      : VideoPlayerController.networkUrl(Uri.parse(widget.url!), httpHeaders: widget.headers);
  bool _failed = false;

  @override
  void initState() {
    super.initState();
    _controller.initialize().then((_) {
      if (!mounted) return;
      setState(() {});
      _controller.play();
    }).catchError((Object error) {
      debugPrint('[ZAYLO] Chat video failed to load: $error');
      if (mounted) setState(() => _failed = true);
    });
    _controller.addListener(_refresh);
  }

  void _refresh() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    _controller.removeListener(_refresh);
    _controller.dispose();
    super.dispose();
  }

  void _toggle() => _controller.value.isPlaying ? _controller.pause() : _controller.play();

  @override
  Widget build(BuildContext context) {
    final value = _controller.value;
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(backgroundColor: Colors.black, foregroundColor: Colors.white, title: const Text('Video')),
      body: SafeArea(
        child: _failed
            ? const Center(
                child: Padding(
                  padding: EdgeInsets.all(24),
                  child: Text('This video could not be played. Check your connection and try again.',
                      textAlign: TextAlign.center, style: TextStyle(color: Colors.white)),
                ),
              )
            : !value.isInitialized
                ? const Center(child: CircularProgressIndicator(color: Colors.white))
                : Column(children: [
                    Expanded(
                      child: GestureDetector(
                        onTap: _toggle,
                        child: Center(
                          child: AspectRatio(aspectRatio: value.aspectRatio, child: VideoPlayer(_controller)),
                        ),
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.fromLTRB(8, 4, 16, 12),
                      child: Row(children: [
                        IconButton(
                          tooltip: value.isPlaying ? 'Pause' : 'Play',
                          color: Colors.white,
                          onPressed: _toggle,
                          icon: Icon(value.isPlaying ? Icons.pause : Icons.play_arrow),
                        ),
                        Expanded(
                          child: VideoProgressIndicator(
                            _controller,
                            allowScrubbing: true,
                            colors: const VideoProgressColors(playedColor: Colors.white, bufferedColor: Colors.white38),
                          ),
                        ),
                      ]),
                    ),
                  ]),
      ),
    );
  }
}
