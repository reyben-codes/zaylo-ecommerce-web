/// A failed API call, with a message safe to show the user.
///
/// [endpoint] (for example `POST /mobile/login`) and [statusCode] say which
/// request failed; [debugDescription] combines them for logs and bug reports.
class ApiException implements Exception {
  const ApiException(
    this.message, {
    this.endpoint,
    this.statusCode,
    this.code,
    this.email,
    this.fieldErrors = const {},
  });

  factory ApiException.fromResponse(int statusCode, Map<String, dynamic> data, {String? endpoint}) {
    final fieldErrors = <String, String>{};
    final errors = data['errors'];
    if (errors is Map<String, dynamic>) {
      errors.forEach((field, value) {
        if (value is List && value.isNotEmpty) fieldErrors[field] = value.first.toString();
      });
    }
    final fallback = switch (statusCode) {
      401 => 'Please sign in to continue.',
      403 => 'You do not have access to this.',
      404 => 'We could not find what you were looking for.',
      413 => 'The files are too large to upload. Each one must be 5.0 MB or smaller.',
      429 => 'Too many attempts. Please wait a moment and try again.',
      >= 500 => 'ZAYLO is having trouble right now. Please try again shortly.',
      _ => 'Something went wrong. Please try again.',
    };
    return ApiException(
      fieldErrors.values.firstOrNull ?? (data['message'] as String?) ?? fallback,
      endpoint: endpoint,
      statusCode: statusCode,
      code: data['code'] as String?,
      email: data['email'] as String?,
      fieldErrors: fieldErrors,
    );
  }

  final String message;

  /// The request that failed, such as `POST /mobile/login`.
  final String? endpoint;
  final int? statusCode;

  /// Machine-readable reason from the API, such as `unverified` or `inactive`.
  final String? code;
  final String? email;
  final Map<String, String> fieldErrors;

  bool get isUnverified => code == 'unverified';

  /// For logs: `POST /mobile/login (422, code: unverified): message`.
  String get debugDescription {
    final details = [if (statusCode != null) '$statusCode', if (code != null) 'code: $code'].join(', ');
    return '${endpoint ?? 'request'}${details.isEmpty ? '' : ' ($details)'}: $message';
  }

  @override
  String toString() => message;
}
