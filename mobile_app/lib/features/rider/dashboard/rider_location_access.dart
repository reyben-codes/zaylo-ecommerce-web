import 'package:flutter/material.dart';

import 'package:zaylo_mobile/components/map/zaylo_map.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Why riders share their location, shown before the system prompt and on the Home banner.
const riderLocationReason =
    'ZAYLO shares your location while you are on shift or delivering, so your hub can give you parcels near you '
    'and buyers can see you coming on the map. It is only shared while the app is open.';

/// Reads where location stands, or null when it cannot be told (for example, no location plugin in tests).
Future<LocationAccess?> riderLocationStatus() async {
  try {
    return await DeviceLocation.access(ask: false);
  } on Object {
    return null;
  }
}

/// Asks the rider for location, explaining why first: the system prompt when it can still be shown, the app's
/// settings when it was refused for good, or the phone's location settings when GPS is off. Returns where it stands
/// afterwards (settings changes are picked up when the app comes back, see RiderDashboardTab).
Future<LocationAccess?> askRiderLocation(BuildContext context) async {
  final status = await riderLocationStatus();
  if (status == null || status == LocationAccess.granted || !context.mounted) return status;

  final (title, action) = switch (status) {
    LocationAccess.serviceOff => ('Turn on your location', 'Open location settings'),
    LocationAccess.blocked => ('Allow location in Settings', 'Open settings'),
    _ => ('Allow ZAYLO to use your location', 'Continue'),
  };
  final go = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      icon: Icon(Icons.location_on_outlined, color: ZayloColors.bronze, size: 32),
      title: Text(title),
      content: Text(switch (status) {
        LocationAccess.serviceOff => "Your phone's location is off. $riderLocationReason",
        LocationAccess.blocked =>
          'Location was turned off for ZAYLO. Open Settings, then Permissions, then Location, and choose "Allow while using the app". $riderLocationReason',
        _ => riderLocationReason,
      }),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Not now')),
        FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(action)),
      ],
    ),
  );
  if (go != true) return status;
  try {
    switch (status) {
      case LocationAccess.serviceOff:
        await DeviceLocation.openLocationSettings();
        return status;
      case LocationAccess.blocked:
        await DeviceLocation.openAppSettings();
        return status;
      case LocationAccess.needsPermission:
      case LocationAccess.granted:
        return await DeviceLocation.access(ask: true);
    }
  } on Object {
    return status;
  }
}

/// On the rider's Home while location is not available: what is missing and a button to fix it.
class RiderLocationBanner extends StatelessWidget {
  const RiderLocationBanner({super.key, required this.status, required this.onFix});

  final LocationAccess status;
  final VoidCallback onFix;

  @override
  Widget build(BuildContext context) => Material(
        color: ZayloColors.cream,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: BorderSide(color: ZayloColors.bronze.withValues(alpha: .5)),
        ),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 12, 10, 12),
          child: Row(children: [
            Icon(Icons.location_off_outlined, color: ZayloColors.bronze),
            const SizedBox(width: 12),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(
                  status == LocationAccess.serviceOff ? 'Your location is off' : 'Location not allowed',
                  style: TextStyle(fontWeight: FontWeight.w800, color: ZayloColors.ink),
                ),
                const SizedBox(height: 2),
                const Text('Your hub and buyers cannot see you on the map.', style: TextStyle(fontSize: 12.5)),
              ]),
            ),
            TextButton(
              onPressed: onFix,
              child: Text(status == LocationAccess.serviceOff ? 'TURN ON' : 'ALLOW'),
            ),
          ]),
        ),
      );
}
