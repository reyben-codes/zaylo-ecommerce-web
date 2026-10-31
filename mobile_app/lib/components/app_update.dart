import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:url_launcher/url_launcher.dart';

import 'package:zaylo_mobile/models/support_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// The version the release workflow builds into the app (--dart-define=APP_VERSION, see
/// .github/workflows/release-apk.yml). Empty in local builds, which never suggest updating.
const appVersion = String.fromEnvironment('APP_VERSION');

/// A single release entry shown in the Patch Notes modal pop-up.
class PatchNoteEntry {
  const PatchNoteEntry({
    required this.version,
    required this.title,
    required this.highlights,
    this.isLatest = false,
  });

  final String version;
  final String title;
  final List<String> highlights;
  final bool isLatest;

  PatchNoteEntry copyWith({String? version}) => PatchNoteEntry(
        version: version ?? this.version,
        title: title,
        highlights: highlights,
        isLatest: isLatest,
      );
}

/// Built-in release history shown in the Patch Notes modal pop-up.
const zayloPatchNotes = <PatchNoteEntry>[
  PatchNoteEntry(
    version: '1.0.5',
    title: 'Chatbot, Product Zoom & Cart Update',
    isLatest: true,
    highlights: [
      'Floating Zay chatbot with lively animated expressions and sidebar control.',
      'Interactive pinch-to-zoom and full-screen photo zoom viewer on product pages.',
      'Unified Cart experience across bottom navigation, product cards, and checkout.',
      'Sidebar shortcut to summon the Zay chatbot and view patch notes anytime.',
    ],
  ),
  PatchNoteEntry(
    version: '1.0.4',
    title: 'Google Sign-In & Rider Parcels',
    highlights: [
      'One-tap Continue with Google for both buyer and delivery rider accounts.',
      'Return slip barcode scanner and live step-by-step parcel tracking.',
      'Dark mode theme toggle matching the web storefront palette.',
    ],
  ),
];

/// Persists the last patch notes version the user has seen; tests use [MemoryPatchNoteStore].
abstract class PatchNoteStore {
  Future<String?> readSeenVersion();
  Future<void> writeSeenVersion(String version);
}

class SecurePatchNoteStore implements PatchNoteStore {
  static const _key = 'zaylo_seen_patch_version';
  final _storage = const FlutterSecureStorage();

  @override
  Future<String?> readSeenVersion() async {
    try {
      return await _storage.read(key: _key);
    } catch (_) {
      return null;
    }
  }

  @override
  Future<void> writeSeenVersion(String version) async {
    try {
      await _storage.write(key: _key, value: version);
    } catch (_) {
      // Safe to ignore if secure storage is unavailable.
    }
  }
}

class MemoryPatchNoteStore implements PatchNoteStore {
  MemoryPatchNoteStore([this._seen]);

  String? _seen;

  @override
  Future<String?> readSeenVersion() async => _seen;

  @override
  Future<void> writeSeenVersion(String version) async => _seen = version;
}

/// Opens the modal pop-up showing what updates were added in recent releases.
Future<void> showPatchNotesDialog(
  BuildContext context, {
  List<PatchNoteEntry> entries = zayloPatchNotes,
  String? currentVersion,
}) {
  final effectiveVersion = (currentVersion ?? appVersion).trim();
  final resolvedEntries = [
    for (var i = 0; i < entries.length; i++)
      if (i == 0 && effectiveVersion.isNotEmpty) entries[i].copyWith(version: effectiveVersion) else entries[i],
  ];
  return showDialog<void>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      backgroundColor: ZayloColors.paper,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: ZayloColors.line),
      ),
      icon: Container(
        width: 48,
        height: 48,
        decoration: BoxDecoration(
          color: ZayloColors.sand,
          shape: BoxShape.circle,
          border: Border.all(color: ZayloColors.line),
        ),
        child: Icon(Icons.auto_awesome_rounded, color: ZayloColors.ink, size: 22),
      ),
      title: Column(
        children: [
          Text(
            'Patch notes',
            style: TextStyle(fontWeight: FontWeight.w800, color: ZayloColors.ink),
          ),
          const SizedBox(height: 4),
          Text(
            'See what updates were added to ZAYLO',
            style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w500, color: ZayloColors.muted),
            textAlign: TextAlign.center,
          ),
        ],
      ),
      content: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 380, maxHeight: 380),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              for (var i = 0; i < resolvedEntries.length; i++) ...[
                if (i > 0) const SizedBox(height: 12),
                _PatchNoteCard(entry: resolvedEntries[i]),
              ],
            ],
          ),
        ),
      ),
      actions: [
        FilledButton(
          onPressed: () => Navigator.pop(dialogContext),
          child: const Text('GOT IT'),
        ),
      ],
    ),
  );
}

class _PatchNoteCard extends StatelessWidget {
  const _PatchNoteCard({required this.entry});

  final PatchNoteEntry entry;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: ZayloColors.cream,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: ZayloColors.line),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: entry.isLatest ? ZayloColors.ink : ZayloColors.sand,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    'v${entry.version}',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      color: entry.isLatest ? ZayloColors.white : ZayloColors.ink,
                    ),
                  ),
                ),
                if (entry.isLatest) ...[
                  const SizedBox(width: 6),
                  Text(
                    'NEW',
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.6,
                      color: ZayloColors.bronze,
                    ),
                  ),
                ],
              ],
            ),
            const SizedBox(height: 8),
            Text(
              entry.title,
              style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w800, color: ZayloColors.ink),
            ),
            const SizedBox(height: 8),
            for (final item in entry.highlights)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Padding(
                      padding: const EdgeInsets.only(top: 3),
                      child: Icon(Icons.check_circle_outline_rounded, size: 14, color: ZayloColors.bronze),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        item,
                        style: TextStyle(fontSize: 12.5, height: 1.35, color: ZayloColors.ink),
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      );
}

/// Suggests updating when a newer app has been published: checked when ZAYLO opens and when it comes back to
/// the front. "Later" hides it until the app is opened again.
class AppUpdatePrompt {
  AppUpdatePrompt({
    required this.navigatorKey,
    required this.load,
    this.currentVersion = appVersion,
    PatchNoteStore? patchNoteStore,
    Future<void> Function(Uri url)? open,
  })  : _patchNoteStore = patchNoteStore ?? SecurePatchNoteStore(),
        open = open ?? _openInBrowser;

  final GlobalKey<NavigatorState> navigatorKey;

  /// Asks the server for the newest app.
  final Future<AppVersionInfo> Function() load;
  final String currentVersion;
  final PatchNoteStore _patchNoteStore;

  /// Opens the APK download.
  final Future<void> Function(Uri url) open;

  bool _asked = false;
  bool _checking = false;
  bool _checkedPatchNotes = false;

  /// Shows the Patch Notes modal pop-up once per release version so users can see what updates were added.
  Future<void> checkPatchNotes() async {
    if (_checkedPatchNotes) return;
    _checkedPatchNotes = true;
    final latestPatch = currentVersion.isNotEmpty ? currentVersion : zayloPatchNotes.first.version;
    final seen = await _patchNoteStore.readSeenVersion();
    if (seen == latestPatch) return;
    final context = navigatorKey.currentState?.overlay?.context;
    if (context == null || !context.mounted) return;
    await _patchNoteStore.writeSeenVersion(latestPatch);
    if (!context.mounted) return;
    await showPatchNotesDialog(context, currentVersion: latestPatch);
  }

  Future<void> check() async {
    if (_asked || _checking || currentVersion.isEmpty) return;
    _checking = true;
    final AppVersionInfo info;
    try {
      info = await load();
    } on Exception catch (error) {
      // Offline or the server is busy: try again when the app comes back to the front.
      debugPrint('[ZAYLO] Update check failed: $error');
      return;
    } finally {
      _checking = false;
    }
    final url = Uri.tryParse(info.downloadUrl ?? '');
    final context = navigatorKey.currentState?.overlay?.context;
    if (_asked || url == null || !info.isNewerThan(currentVersion) || context == null || !context.mounted) return;

    _asked = true;
    final notes = info.releaseNotes.isNotEmpty ? info.releaseNotes : zayloPatchNotes.first.highlights;
    final update = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        icon: const Icon(Icons.system_update_outlined),
        title: const Text('Update available'),
        content: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 380),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('ZAYLO ${info.latestVersion} is out with the latest features and fixes. '
                    'Update now so everything keeps working smoothly (you are on $currentVersion).'),
                if (notes.isNotEmpty) ...[
                  const SizedBox(height: 14),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: ZayloColors.cream,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: ZayloColors.line),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          "What's new in ${info.latestVersion}",
                          style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: ZayloColors.ink),
                        ),
                        const SizedBox(height: 8),
                        for (final note in notes)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 5),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Padding(
                                  padding: const EdgeInsets.only(top: 3),
                                  child: Icon(Icons.check_circle_outline_rounded, size: 13, color: ZayloColors.bronze),
                                ),
                                const SizedBox(width: 7),
                                Expanded(
                                  child: Text(
                                    note,
                                    style: TextStyle(fontSize: 12, height: 1.3, color: ZayloColors.ink),
                                  ),
                                ),
                              ],
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('LATER')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('UPDATE')),
        ],
      ),
    );
    if (update == true) await open(url);
  }

  static Future<void> _openInBrowser(Uri url) async {
    if (!await launchUrl(url, mode: LaunchMode.externalApplication)) {
      debugPrint('[ZAYLO] Could not open $url');
    }
  }
}
