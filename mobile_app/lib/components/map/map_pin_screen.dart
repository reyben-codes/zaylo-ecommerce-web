import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';

import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/map/zaylo_map.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Pins the exact spot of an address: tap the map or use the phone's location. Pops `(point: ...)` when saved, with
/// a null point when the pin was removed; pops nothing when the buyer backs out.
class MapPinScreen extends StatefulWidget {
  const MapPinScreen({super.key, this.initial});

  final MapPoint? initial;

  @override
  State<MapPinScreen> createState() => _MapPinScreenState();
}

class _MapPinScreenState extends State<MapPinScreen> {
  final _map = MapController();
  late MapPoint? _pin = widget.initial;
  bool _locating = false;

  Future<void> _useMyLocation() async {
    setState(() => _locating = true);
    try {
      final here = await DeviceLocation.current();
      if (!mounted) return;
      setState(() => _pin = MapPoint.from(here));
      _map.move(here, 17);
    } on LocationUnavailable catch (error) {
      if (mounted) showMessage(context, error.message);
    } on Exception {
      if (mounted) showMessage(context, 'Could not get your location. Tap the map instead.');
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final pin = _pin;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pin exact location'),
        actions: [
          if (pin != null)
            TextButton(onPressed: () => setState(() => _pin = null), child: const Text('Remove pin')),
        ],
      ),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 4, ZayloSpace.page, 10),
          child: Text(
            pin == null ? 'Tap the map on your door, or use your current location.' : 'Pinned. Tap elsewhere to move the pin.',
            style: TextStyle(color: ZayloColors.muted),
          ),
        ),
        Expanded(
          child: Stack(children: [
            ZayloMap(
              controller: _map,
              center: widget.initial?.latLng,
              zoom: 17,
              markers: [if (pin != null) mapMarker(pin.latLng, MapPinKind.home)],
              onTap: (point) => setState(() => _pin = MapPoint.from(point)),
            ),
            Positioned(
              right: 16,
              bottom: 16,
              child: FloatingActionButton.extended(
                heroTag: null,
                onPressed: _locating ? null : _useMyLocation,
                backgroundColor: ZayloColors.paper,
                foregroundColor: ZayloColors.ink,
                icon: _locating
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.my_location),
                label: const Text('Use my location'),
              ),
            ),
          ]),
        ),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.all(ZayloSpace.page),
            child: PrimaryButton(
              label: pin == null ? 'SAVE WITHOUT A PIN' : 'SAVE PIN',
              onPressed: () => Navigator.pop(context, (point: pin)),
            ),
          ),
        ),
      ]),
    );
  }
}
