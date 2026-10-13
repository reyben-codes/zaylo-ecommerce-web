import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Persists the Sanctum token. Secure storage uses the Keychain on iOS and
/// encrypted storage on Android; tests use [MemoryTokenStore].
abstract class TokenStore {
  Future<String?> read();
  Future<void> write(String token);
  Future<void> clear();
}

class SecureTokenStore implements TokenStore {
  static const _key = 'zaylo_api_token';
  final _storage = const FlutterSecureStorage();

  @override
  Future<String?> read() async {
    try {
      return await _storage.read(key: _key);
    } catch (_) {
      // A corrupted keystore entry must not keep the app from starting.
      await clear();
      return null;
    }
  }

  @override
  Future<void> write(String token) => _storage.write(key: _key, value: token);

  @override
  Future<void> clear() => _storage.delete(key: _key);
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
