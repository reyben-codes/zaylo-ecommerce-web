import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'package:zaylo_mobile/models/support_models.dart';

/// Pop-up notices from the ZAYLO admin, as in the website's announcement
/// modal. Each shows once on this device, one at a time; an edited notice has a
/// new key, so it shows again.
class AnnouncementPopups extends StatefulWidget {
  const AnnouncementPopups({super.key, required this.announcements});

  final List<Announcement> announcements;

  static const _storageKey = 'zaylo_seen_announcement_popups';
  static const _storage = FlutterSecureStorage();

  /// Shared by the buyer and rider screens, so a notice never shows twice.
  static final Set<String> _seen = {};
  static bool _restored = false;

  static Future<void> _restore() async {
    if (_restored) return;
    _restored = true;
    try {
      final saved = await _storage.read(key: _storageKey);
      if (saved != null) _seen.addAll((jsonDecode(saved) as List<dynamic>).cast<String>());
    } catch (_) {
      // Unreadable storage: the notice may show again, which is harmless.
    }
  }

  static Future<void> _remember(String key) async {
    _seen.add(key);
    final recent = _seen.toList();
    try {
      await _storage.write(
        key: _storageKey,
        value: jsonEncode(recent.sublist(recent.length > 50 ? recent.length - 50 : 0)),
      );
    } catch (_) {
      // Still remembered until the app closes.
    }
  }

  @override
  State<AnnouncementPopups> createState() => _AnnouncementPopupsState();
}

class _AnnouncementPopupsState extends State<AnnouncementPopups> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _showUnseen());
  }

  @override
  void didUpdateWidget(AnnouncementPopups oldWidget) {
    super.didUpdateWidget(oldWidget);
    WidgetsBinding.instance.addPostFrameCallback((_) => _showUnseen());
  }

  Future<void> _showUnseen() async {
    await AnnouncementPopups._restore();
    for (final notice in widget.announcements) {
      if (!mounted || AnnouncementPopups._seen.contains(notice.key)) continue;
      // Marked first, so a refresh behind the dialog can't open a second copy.
      await AnnouncementPopups._remember(notice.key);
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          icon: const Icon(Icons.campaign_outlined),
          title: Text(notice.title),
          content: SingleChildScrollView(child: Text(notice.body)),
          actions: [TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Got it'))],
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) => const SizedBox.shrink();
}
