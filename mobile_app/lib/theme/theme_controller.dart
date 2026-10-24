import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Persists the appearance choice; tests use [MemoryThemeModeStore].
abstract class ThemeModeStore {
  Future<ThemeMode?> read();
  Future<void> write(ThemeMode mode);
}

class SecureThemeModeStore implements ThemeModeStore {
  static const _key = 'zaylo_theme_mode';
  final _storage = const FlutterSecureStorage();

  @override
  Future<ThemeMode?> read() async {
    try {
      final value = await _storage.read(key: _key);
      return ThemeMode.values.where((mode) => mode.name == value).firstOrNull;
    } catch (_) {
      // Falling back to the system setting is always safe.
      return null;
    }
  }

  @override
  Future<void> write(ThemeMode mode) => _storage.write(key: _key, value: mode.name);
}

class MemoryThemeModeStore implements ThemeModeStore {
  ThemeMode? _mode;

  @override
  Future<ThemeMode?> read() async => _mode;

  @override
  Future<void> write(ThemeMode mode) async => _mode = mode;
}

/// The appearance choice: light, dark, or following the device (system).
class ThemeController extends ChangeNotifier {
  ThemeController({ThemeModeStore? store}) : _store = store ?? SecureThemeModeStore();

  final ThemeModeStore _store;
  ThemeMode _mode = ThemeMode.system;

  ThemeMode get mode => _mode;

  Future<void> restore() async {
    final saved = await _store.read();
    if (saved == null || saved == _mode) return;
    _mode = saved;
    notifyListeners();
  }

  Future<void> setMode(ThemeMode mode) async {
    if (mode == _mode) return;
    _mode = mode;
    notifyListeners();
    await _store.write(mode);
  }

  /// Flips between light and dark, as the web's theme toggle does. Choosing
  /// the appearance the device already uses goes back to following the device.
  Future<void> toggle({required Brightness current, required Brightness system}) {
    final next = current == Brightness.dark ? Brightness.light : Brightness.dark;
    if (next == system) return setMode(ThemeMode.system);
    return setMode(next == Brightness.dark ? ThemeMode.dark : ThemeMode.light);
  }
}

class ThemeScope extends InheritedNotifier<ThemeController> {
  const ThemeScope({super.key, required ThemeController controller, required super.child})
      : super(notifier: controller);

  /// Null when a screen is pumped on its own, as in widget tests.
  static ThemeController? maybeOf(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<ThemeScope>()?.notifier;
}
