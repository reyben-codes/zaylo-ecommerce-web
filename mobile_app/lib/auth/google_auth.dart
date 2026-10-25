import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:google_sign_in/google_sign_in.dart';

/// "Continue with Google" for buyers. The app gets a Google ID token from the
/// phone's own Google sign-in and sends it to POST /mobile/google, which checks
/// it and signs the buyer in by the website's Google account rules.
///
/// The OAuth client IDs are passed when building the app (see
/// docs/google-authentication.md, "Mobile app"):
///
///     flutter run --dart-define=GOOGLE_SERVER_CLIENT_ID=<web client ID>
///                 --dart-define=GOOGLE_IOS_CLIENT_ID=<iOS client ID>   # iOS only
///
/// The website's web client ID is built in, so Android needs no flag; iOS
/// still needs its own client ID, and without it the button is hidden there.
abstract class GoogleIdTokenSource {
  /// Whether Google sign-in is configured for this platform.
  bool get available;

  /// Shows Google's account picker and returns an ID token, or null when the
  /// buyer closes it. Throws [GoogleSignInFailure] for any other problem.
  Future<String?> idToken();

  /// Forgets the Google account chosen in this app, so the next sign-in asks again.
  Future<void> signOut();
}

/// A problem with Google sign-in that the buyer can read.
class GoogleSignInFailure implements Exception {
  const GoogleSignInFailure(this.message);

  final String message;

  @override
  String toString() => message;
}

abstract final class GoogleAuth {
  /// Replaced in tests; the real one uses the google_sign_in plugin.
  static GoogleIdTokenSource source = PluginGoogleIdTokenSource();
}

class PluginGoogleIdTokenSource implements GoogleIdTokenSource {
  /// The *web* OAuth client ID. Google issues ID tokens to it, and the server
  /// accepts tokens for it (GOOGLE_CLIENT_ID).
  /// Client IDs are public; the website's is built in so a plain `flutter run` offers Google sign-in.
  static const serverClientId = String.fromEnvironment(
    'GOOGLE_SERVER_CLIENT_ID',
    defaultValue: '71918808884-d4uh5997uq3pe2n7m4vha15ikt52vvbq.apps.googleusercontent.com',
  );

  /// The iOS OAuth client ID; iOS also needs its reversed form as a URL scheme.
  static const iosClientId = String.fromEnvironment('GOOGLE_IOS_CLIENT_ID');

  Future<void>? _initialized;

  @override
  bool get available {
    if (kIsWeb || serverClientId.isEmpty) return false;
    if (Platform.isAndroid) return true;
    return Platform.isIOS && iosClientId.isNotEmpty;
  }

  /// Runs once; a failed attempt is retried on the next tap.
  Future<void> _initialize() => _initialized ??= GoogleSignIn.instance
          .initialize(clientId: Platform.isIOS ? iosClientId : null, serverClientId: serverClientId)
          .onError<Object>((error, stackTrace) {
        _initialized = null;
        Error.throwWithStackTrace(error, stackTrace);
      });

  @override
  Future<String?> idToken() async {
    try {
      await _initialize();
      if (!GoogleSignIn.instance.supportsAuthenticate()) {
        throw const GoogleSignInFailure('Google sign-in is not available on this device.');
      }
      final account = await GoogleSignIn.instance.authenticate(scopeHint: const ['email', 'profile']);
      final token = account.authentication.idToken;
      if (token == null) throw const GoogleSignInFailure('Google did not confirm your account. Please try again.');
      return token;
    } on GoogleSignInException catch (error) {
      if (error.code == GoogleSignInExceptionCode.canceled) return null;
      // Logged for setup problems such as a missing SHA-1 or a wrong client ID.
      debugPrint('[ZAYLO] Google sign-in failed: ${error.code.name} ${error.description ?? ''}');
      throw GoogleSignInFailure(switch (error.code) {
        GoogleSignInExceptionCode.clientConfigurationError || GoogleSignInExceptionCode.providerConfigurationError =>
          'Google sign-in is not set up correctly for this app yet. Please use email and password.',
        GoogleSignInExceptionCode.uiUnavailable => 'Google sign-in could not open. Please try again.',
        _ => _genericFailure,
      });
    } on GoogleSignInFailure {
      rethrow;
    } on Exception catch (error) {
      debugPrint('[ZAYLO] Google sign-in failed: $error');
      throw const GoogleSignInFailure(_genericFailure);
    }
  }

  static const _genericFailure = 'We could not sign you in with Google. Please try again or use email and password.';

  @override
  Future<void> signOut() async {
    // Only if Google sign-in was used in this session; otherwise there is nothing to forget.
    if (_initialized == null) return;
    try {
      await GoogleSignIn.instance.signOut();
    } on Exception catch (error) {
      debugPrint('[ZAYLO] Google sign-out failed: $error');
    }
  }
}
