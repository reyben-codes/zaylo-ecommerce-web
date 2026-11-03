import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Persists the Sanctum token. Secure storage uses the Keychain on iOS and
/// encrypted storage on Android; tests use [MemoryTokenStore].
abstract class TokenStore {
  Future<String?> read();
  Future<void> write(String token);
  Future<void> clear();
}

class SecureTokenStore implements TokenStore {
  /// The buyer session uses the default key; the rider session keeps its own.
  SecureTokenStore({this.key = 'zaylo_api_token'});

  final String key;
  final _storage = const FlutterSecureStorage();

  @override
  Future<String?> read() async {
    try {
      return await _storage.read(key: key);
    } catch (_) {
      // A corrupted keystore entry must not keep the app from starting.
      try {
        await clear();
      } catch (_) {
        // Nothing more to do; the entry is ignored.
      }
      return null;
    }
  }

  @override
  Future<void> write(String token) => _storage.write(key: key, value: token);

  @override
  Future<void> clear() => _storage.delete(key: key);
}

class MemoryTokenStore implements TokenStore {
  String? _token;

  @override
  Future<String?> read() async => _token;

  @override
  Future<void> write(String token) async => _token = token;

  @override
  Future<void> clear() async => _token = null;
}
