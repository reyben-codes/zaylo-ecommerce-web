import 'package:flutter/material.dart';

import 'package:zaylo_mobile/models/support_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Notices from the ZAYLO admin, as in the website's announcement bar. Each
/// can be dismissed for the rest of the session; an edited notice has a new
/// key, so it shows again.
class AnnouncementBanner extends StatefulWidget {
  const AnnouncementBanner({super.key, required this.announcements, this.padding = EdgeInsets.zero});

  final List<Announcement> announcements;
  final EdgeInsetsGeometry padding;

  /// Shared by the buyer and rider screens, so a notice stays dismissed.
  static final Set<String> _dismissed = {};

  @override
  State<AnnouncementBanner> createState() => _AnnouncementBannerState();
}

class _AnnouncementBannerState extends State<AnnouncementBanner> {
  static const _duration = Duration(milliseconds: 300);

  void _dismiss(Announcement notice) => setState(() => AnnouncementBanner._dismissed.add(notice.key));

  @override
  Widget build(BuildContext context) {
    // Collapsing slides the content below up; skip the motion when the system asks.
    final duration = MediaQuery.disableAnimationsOf(context) ? Duration.zero : _duration;
    return Column(children: [
      for (final notice in widget.announcements)
        AnimatedSize(
          key: ValueKey(notice.key),
          duration: duration,
          curve: Curves.easeOut,
          alignment: Alignment.topCenter,
          child: AnnouncementBanner._dismissed.contains(notice.key)
              ? const SizedBox(width: double.infinity)
              : Padding(padding: widget.padding, child: _Notice(notice: notice, onDismiss: () => _dismiss(notice))),
        ),
    ]);
  }
}

class _Notice extends StatelessWidget {
  const _Notice({required this.notice, required this.onDismiss});

  final Announcement notice;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) => Semantics(
        container: true,
        label: 'Announcement',
        child: Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.fromLTRB(14, 10, 4, 10),
          decoration: BoxDecoration(
            color: ZayloColors.sand,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: ZayloColors.line),
          ),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Padding(
              padding: const EdgeInsets.only(top: 2),
              child: Icon(Icons.campaign_outlined, size: 20, color: ZayloColors.ink),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.only(top: 2),
                child: Text.rich(TextSpan(children: [
                  TextSpan(text: '${notice.title}  ', style: const TextStyle(fontWeight: FontWeight.w800)),
                  TextSpan(text: notice.body),
                ]), style: TextStyle(fontSize: 13, color: ZayloColors.ink)),
              ),
            ),
            IconButton(
              tooltip: 'Dismiss announcement: ${notice.title}',
              visualDensity: VisualDensity.compact,
              onPressed: onDismiss,
              icon: Icon(Icons.close, size: 18, color: ZayloColors.muted),
            ),
          ]),
        ),
      );
}
