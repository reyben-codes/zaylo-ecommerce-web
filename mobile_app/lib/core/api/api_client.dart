import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/foundation.dart';

import '../models/auth_models.dart';
import '../models/location.dart';
import 'api_exception.dart';

/// HTTP boundary for Laravel. The app never connects to MySQL directly.
///
/// [BuyerApi] and [RiderApi] extend this with their endpoints. Each instance
/// holds one Sanctum bearer [token], so buyer and rider sessions never mix.
///
/// In debug builds every request is logged as
/// `[ZAYLO API] POST /mobile/login -> 422 (183ms)`, and failures also log the
/// server message, so the console shows which call broke and why. Request
/// bodies and tokens are never logged.
class ApiClient {
  ApiClient({String? baseUrl})
      : baseUrl = (baseUrl ??
                const String.fromEnvironment(
                  'ZAYLO_API_URL',
                  defaultValue: 'http://10.0.2.2:8000/api',
                ))
            .replaceFirst(RegExp(r'/$'), '');

  final String baseUrl;
  String? token;

  /// Called when the server rejects the token, so the app can sign out.
  void Function()? onUnauthorized;

  // ------------------------------------------------- Shared account endpoints

  Future<void> logout() => send('POST', '/mobile/logout');

  Future<String> forgotPassword(String email) async =>
      (await send('POST', '/mobile/forgot-password', body: {'email': email}))['message'] as String;

  /// Buyers get a signed-in [AuthResult]; riders awaiting approval get only a message.
  Future<AuthResult> verifyEmail({required String email, required String code}) async =>
      AuthResult.fromJson(await send('POST', '/mobile/verify-email', body: {
        'email': email,
        'otp': code,
        'device_name': deviceName,
      }));

  Future<Map<String, dynamic>> resendVerification(String email) =>
      send('POST', '/mobile/resend-verification', body: {'email': email});

  // -------------------------------------------- Philippine locations (PSGC)

  Future<List<Location>> regions() => locationsAt('/locations/regions');

  Future<List<Location>> provinces(String regionCode) =>
      locationsAt('/locations/regions/$regionCode/provinces');

  /// Cities directly under a region (for example NCR, which has no provinces).
  Future<List<Location>> regionCities(String regionCode) =>
      locationsAt('/locations/regions/$regionCode/cities-municipalities');

  Future<List<Location>> cities(String provinceCode) =>
      locationsAt('/locations/provinces/$provinceCode/cities-municipalities');

  Future<List<Location>> barangays(String cityCode) =>
      locationsAt('/locations/cities-municipalities/$cityCode/barangays');

  Future<List<Location>> locationsAt(String path) async {
    final list = await sendRaw('GET', path) as List<dynamic>;
    return list.map((item) => Location.fromJson(item as Map<String, dynamic>)).toList();
  }

  // -------------------------------------------------------------- Transport

  String get deviceName => 'ZAYLO ${Platform.operatingSystem}';

  /// Sends a request whose response is a JSON object.
  @protected
  Future<Map<String, dynamic>> send(
    String method,
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
  }) async {
    final data = await sendRaw(method, path, query: query, body: body);
    return data is Map<String, dynamic> ? data : <String, dynamic>{};
  }

  /// Sends a request and returns the decoded JSON as-is (object or list).
  @protected
  Future<dynamic> sendRaw(
    String method,
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
  }) =>
      _execute(method, path, query: query, writeBody: (request) async {
        if (body != null || method != 'GET') {
          request.headers.contentType = ContentType.json;
          request.write(jsonEncode(body ?? const <String, dynamic>{}));
        }
      });

  /// Sends `multipart/form-data`: text [fields] plus [files] keyed by field
  /// name. Used for uploads such as rider document photos.
  @protected
  Future<Map<String, dynamic>> sendMultipart(
    String path, {
    required Map<String, String> fields,
    required Map<String, File> files,
  }) async {
    const crlf = '\r\n';
    final boundary = 'zaylo-${DateTime.now().microsecondsSinceEpoch}';
    // Built in memory so the request carries an exact Content-Length; photos
    // are compressed by the picker, so this stays a few megabytes at most.
    final body = BytesBuilder(copy: false);
    void text(String value) => body.add(utf8.encode(value));
    fields.forEach((name, value) {
      text('--$boundary${crlf}Content-Disposition: form-data; name="$name"$crlf$crlf$value$crlf');
    });
    for (final entry in files.entries) {
      final filename = entry.value.uri.pathSegments.last;
      text('--$boundary$crlf'
          'Content-Disposition: form-data; name="${entry.key}"; filename="$filename"$crlf'
          'Content-Type: ${_fileContentType(filename)}$crlf$crlf');
      body.add(await entry.value.readAsBytes());
      text(crlf);
    }
    text('--$boundary--$crlf');
    final bytes = body.takeBytes();

    final data = await _execute('POST', path, timeout: const Duration(seconds: 90), writeBody: (request) async {
      request.headers.contentType = ContentType('multipart', 'form-data', parameters: {'boundary': boundary});
      request.contentLength = bytes.length;
      request.add(bytes);
    });
    return data is Map<String, dynamic> ? data : <String, dynamic>{};
  }

  static String _fileContentType(String filename) => switch (filename.split('.').last.toLowerCase()) {
        'png' => 'image/png',
        'webp' => 'image/webp',
        'heic' => 'image/heic',
        'pdf' => 'application/pdf',
        _ => 'image/jpeg',
      };

  Future<dynamic> _execute(
    String method,
    String path, {
    Map<String, String>? query,
    Duration timeout = const Duration(seconds: 30),
    required Future<void> Function(HttpClientRequest request) writeBody,
  }) async {
    final client = HttpClient()..connectionTimeout = const Duration(seconds: 12);
    final stopwatch = Stopwatch()..start();
    try {
      var uri = Uri.parse('$baseUrl$path');
      if (query != null && query.isNotEmpty) {
        uri = uri.replace(queryParameters: query);
      }
      final request = await client.openUrl(method, uri);
      request.headers.set(HttpHeaders.acceptHeader, 'application/json');
      if (token != null) {
        request.headers.set(HttpHeaders.authorizationHeader, 'Bearer $token');
      }
      await writeBody(request);

      final response = await request.close().timeout(timeout);
      final text = await utf8.decoder.bind(response).join();
      _log(method, path, '${response.statusCode}', stopwatch);
      final data = text.isEmpty ? null : jsonDecode(text);

      if (response.statusCode < 200 || response.statusCode >= 300) {
        final map = data is Map<String, dynamic> ? data : const <String, dynamic>{};
        if (response.statusCode == 401 && token != null) onUnauthorized?.call();
        final error = ApiException.fromResponse(response.statusCode, map, endpoint: '$method $path');
        _log(method, path, 'error: ${error.message}', stopwatch);
        throw error;
      }
      return data;
    } on SocketException catch (error) {
      throw _transportError(method, path, stopwatch, error,
          'Cannot reach ZAYLO. Check your connection and try again.');
    } on TimeoutException catch (error) {
      throw _transportError(method, path, stopwatch, error,
          'ZAYLO is taking too long to respond. Please try again.');
    } on HandshakeException catch (error) {
      throw _transportError(method, path, stopwatch, error,
          'A secure connection to ZAYLO could not be made.');
    } on FormatException catch (error) {
      throw _transportError(method, path, stopwatch, error,
          'ZAYLO returned an invalid response. Please try again.');
    } finally {
      client.close(force: true);
    }
  }

  ApiException _transportError(String method, String path, Stopwatch stopwatch, Object cause, String message) {
    _log(method, path, 'failed: $cause', stopwatch);
    return ApiException(message, endpoint: '$method $path');
  }

  void _log(String method, String path, String outcome, Stopwatch stopwatch) {
    if (kDebugMode) debugPrint('[ZAYLO API] $method $path -> $outcome (${stopwatch.elapsedMilliseconds}ms)');
  }
}
