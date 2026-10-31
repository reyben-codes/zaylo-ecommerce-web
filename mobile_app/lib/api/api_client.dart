import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/foundation.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/models/auth_models.dart';
import 'package:zaylo_mobile/models/location.dart';
import 'package:zaylo_mobile/models/support_models.dart';
import 'package:zaylo_mobile/utils/ids.dart';

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
                // The live site by default, so a plain `flutter run` on a phone works. For a local
                // server use e.g. --dart-define=ZAYLO_API_URL=http://10.0.2.2:8000/api (Android emulator).
                const String.fromEnvironment(
                  'ZAYLO_API_URL',
                  defaultValue: 'https://zaylo-zyl.shop/api',
                ))
            .replaceFirst(RegExp(r'/$'), '');

  final String baseUrl;
  String? token;

  /// Called when the server rejects the token, so the app can sign out.
  void Function()? onUnauthorized;

  // ------------------------------------------------- Shared account endpoints

  Future<void> logout() => send('POST', '/mobile/logout');

  static const _githubReleasesApi = 'https://api.github.com/repos/reyben-codes/zaylo-ecommerce-web/releases/latest';
  static const _defaultApkUrl =
      'https://github.com/reyben-codes/zaylo-ecommerce-web/releases/latest/download/zaylo.apk';

  /// The newest published app and where to download it. Falls back to GitHub Releases directly from the phone's IP
  /// if the web host's shared IP was rate-limited by GitHub.
  Future<AppVersionInfo> appVersion() async {
    AppVersionInfo? serverInfo;
    try {
      serverInfo = AppVersionInfo.fromJson(await send('GET', '/mobile/app-version'));
      if (serverInfo.latestVersion != null && serverInfo.latestVersion!.isNotEmpty) {
        return serverInfo;
      }
    } catch (_) {
      // Fall back to direct GitHub lookup below.
    }
    final directVersion = await _fetchVersionFromGitHub();
    if (directVersion != null) {
      return AppVersionInfo(
        latestVersion: directVersion,
        downloadUrl: serverInfo?.downloadUrl ?? _defaultApkUrl,
        releaseNotes: serverInfo?.releaseNotes ?? const [],
      );
    }
    return serverInfo ?? const AppVersionInfo();
  }

  Future<String?> _fetchVersionFromGitHub() async {
    final client = HttpClient()..connectionTimeout = const Duration(seconds: 6);
    try {
      final request = await client.getUrl(Uri.parse(_githubReleasesApi));
      request.headers.set(HttpHeaders.acceptHeader, 'application/vnd.github+json');
      request.headers.set(HttpHeaders.userAgentHeader, 'Zaylo-Mobile-App');
      final response = await request.close().timeout(const Duration(seconds: 6));
      if (response.statusCode != 200) return null;
      final text = await response.transform(utf8.decoder).join();
      final decoded = jsonDecode(text);
      if (decoded is! Map<String, dynamic>) return null;
      final tag = (decoded['tag_name'] as String?)?.trim() ?? '';
      final match = RegExp(r'^v?(\d+\.\d+\.\d+)$').firstMatch(tag);
      return match?.group(1);
    } catch (_) {
      return null;
    } finally {
      client.close(force: true);
    }
  }

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

  // ------------------------------------- Support, complaints and policies

  /// Where this account's own endpoints live: `/mobile` for buyers and
  /// `/mobile/rider` for riders. Support and complaints use the same thread
  /// and cases as the website under either prefix.
  @protected
  String get accountPrefix => '/mobile';

  /// Whose questions the help bot answers: buyer here, rider in [RiderApi].
  @protected
  String get faqRole => 'buyer';

  /// The help bot's questions and answers (the same as on the website).
  Future<FaqData> faq() async => FaqData.fromJson(await send('GET', '/mobile/faq', query: {'role': faqRole}));

  /// The whole support thread, oldest first. Opening it marks replies as read.
  Future<List<SupportMessage>> supportMessages() async =>
      ((await send('GET', '$accountPrefix/support'))['messages'] as List<dynamic>)
          .map((item) => SupportMessage.fromJson(item as Map<String, dynamic>))
          .toList();

  /// [clientId] makes a retried send safe: the server stores it only once.
  Future<SupportMessage> sendSupportMessage(String body, {String? clientId}) async =>
      SupportMessage.fromJson((await send('POST', '$accountPrefix/support/messages', body: {
        'body': body,
        'client_id': clientId ?? newUuidV4(),
      }))['message'] as Map<String, dynamic>);

  /// Support replies not opened yet.
  Future<int> supportUnreadCount() async => (await send('GET', '$accountPrefix/support/unread'))['unread'] as int? ?? 0;

  /// Complaints the user filed (buyers) or is part of (riders), newest first.
  Future<List<Complaint>> complaints() async =>
      ((await send('GET', '$accountPrefix/complaints'))['complaints'] as List<dynamic>)
          .map((item) => Complaint.fromJson(item as Map<String, dynamic>))
          .toList();

  Future<Complaint> complaint(int id) async =>
      Complaint.fromJson((await send('GET', '$accountPrefix/complaints/$id'))['complaint'] as Map<String, dynamic>);

  /// Replies in the case thread, with an optional photo or PDF; returns the updated case.
  Future<Complaint> replyToComplaint(int id, String body, {File? attachment}) async =>
      Complaint.fromJson((await sendMultipart('$accountPrefix/complaints/$id/messages',
          fields: {'body': body}, files: {if (attachment != null) 'attachment': attachment}))['complaint']
          as Map<String, dynamic>);

  /// Terms, privacy, returns and prohibited items: the website's policy pages.
  Future<List<PolicySummary>> policies() async => ((await send('GET', '/mobile/policies'))['policies'] as List<dynamic>)
      .map((item) => PolicySummary.fromJson(item as Map<String, dynamic>))
      .toList();

  Future<Policy> policy(String slug) async =>
      Policy.fromJson((await send('GET', '/mobile/policies/$slug'))['policy'] as Map<String, dynamic>);

  // ------------------------------------------------------------ Profile photo

  /// Uploads a new profile photo (buyers and riders alike) and returns its URL.
  Future<String?> uploadProfilePhoto(File photo) async =>
      (await sendMultipart('/mobile/account/photo', fields: const {}, files: {'photo': photo}))['avatar_url'] as String?;

  Future<void> removeProfilePhoto() => send('DELETE', '/mobile/account/photo');

  /// For loading private files, such as complaint evidence, with this session.
  Map<String, String> get authHeaders => {if (token != null) HttpHeaders.authorizationHeader: 'Bearer $token'};

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
