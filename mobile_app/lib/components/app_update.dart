import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import 'package:zaylo_mobile/models/support_models.dart';

/// The version the release workflow builds into the app (--dart-define=APP_VERSION, see
/// .github/workflows/release-apk.yml). Empty in local builds, which never suggest updating.
const appVersion = String.fromEnvironment('APP_VERSION');

/// Suggests updating when a newer app has been published: checked when ZAYLO opens and when it comes back to
/// the front. "Later" hides it until the app is opened again.
class AppUpdatePrompt {
  AppUpdatePrompt({
    required this.navigatorKey,
    required this.load,
    this.currentVersion = appVersion,
    Future<void> Function(Uri url)? open,
  }) : open = open ?? _openInBrowser;

  final GlobalKey<NavigatorState> navigatorKey;

  /// Asks the server for the newest app.
  final Future<AppVersionInfo> Function() load;
  final String currentVersion;

  /// Opens the APK download.
  final Future<void> Function(Uri url) open;

  bool _asked = false;
  bool _checking = false;

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
    final update = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        icon: const Icon(Icons.system_update_outlined),
        title: const Text('Update available'),
        content: Text('ZAYLO ${info.latestVersion} is out with the latest features and fixes. '
            'Update now so everything keeps working smoothly (you are on $currentVersion).'),
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
