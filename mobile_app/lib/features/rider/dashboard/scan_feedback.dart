import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// Sound and vibration for the parcel scanner: a bright beep for an accepted
/// code and a low double buzz for the wrong parcel, so riders know the result
/// without looking at the screen.
///
/// The players are shared rather than owned by the scan screen, which closes
/// as soon as a code is accepted and would otherwise cut the beep short.
abstract final class ScanFeedback {
  static AudioPlayer? _success;
  static AudioPlayer? _error;

  // Mix with the rider's music or navigation voice instead of pausing it.
  static final _context = AudioContextConfig(focus: AudioContextConfigFocus.mixWithOthers).build();

  static Future<AudioPlayer> _player(String asset) async {
    final player = AudioPlayer()..audioCache.prefix = 'assets/sounds/';
    await player.setAudioContext(_context);
    await player.setPlayerMode(PlayerMode.lowLatency);
    await player.setReleaseMode(ReleaseMode.stop);
    await player.setSource(AssetSource(asset));
    return player;
  }

  /// Loads both sounds so the first scan beeps without a delay.
  static Future<void> preload() async {
    try {
      _success ??= await _player('scan_success.wav');
      _error ??= await _player('scan_error.wav');
    } catch (error) {
      debugPrint('[ScanFeedback] could not load sounds: $error');
    }
  }

  static Future<void> accepted() async {
    HapticFeedback.mediumImpact();
    await _play(() async => _success ??= await _player('scan_success.wav'));
  }

  static Future<void> rejected() async {
    HapticFeedback.heavyImpact();
    await _play(() async => _error ??= await _player('scan_error.wav'));
  }

  // A sound that fails to play must never block or break a scan.
  static Future<void> _play(Future<AudioPlayer> Function() load) async {
    try {
      final player = await load();
      await player.stop();
      await player.resume();
    } catch (error) {
      debugPrint('[ScanFeedback] could not play sound: $error');
    }
  }
}
