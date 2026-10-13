class UserProfile {
  const UserProfile({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.dateOfBirth,
    this.emailVerified = true,
    this.hasPassword = true,
    this.googleLinked = false,
  });

  final int id;
  final String name;
  final String email;
  final String? phone;
  final String? dateOfBirth;
  final bool emailVerified;
  final bool hasPassword;
  final bool googleLinked;

  String get firstName => name.split(' ').first;

  String get initials {
    final parts = name.trim().split(RegExp(r'\s+')).where((part) => part.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    return (parts.first[0] + (parts.length > 1 ? parts.last[0] : '')).toUpperCase();
  }

  factory UserProfile.fromJson(Map<String, dynamic> json) => UserProfile(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String,
        email: json['email'] as String,
        phone: json['phone'] as String?,
        dateOfBirth: json['date_of_birth'] as String?,
        emailVerified: json['email_verified'] as bool? ?? true,
        hasPassword: json['has_password'] as bool? ?? true,
        googleLinked: json['google_linked'] as bool? ?? false,
      );
}

class AuthResult {
  const AuthResult({required this.message, this.token, this.user});

  final String message;
  final String? token;
  final UserProfile? user;

  factory AuthResult.fromJson(Map<String, dynamic> json) => AuthResult(
        message: json['message'] as String? ?? '',
        token: json['token'] as String?,
        user: json['user'] == null ? null : UserProfile.fromJson(json['user'] as Map<String, dynamic>),
      );
}
