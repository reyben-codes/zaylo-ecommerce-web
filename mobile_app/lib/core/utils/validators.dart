const strongPasswordHint = 'Use 8+ characters with uppercase, lowercase, number, and symbol.';

/// Mirrors app/Rules/StrongPassword.php so buyers see problems before submitting.
String? strongPasswordError(String? value) {
  final password = value ?? '';
  final ok = password.runes.length >= 8 &&
      RegExp(r'\p{Lu}', unicode: true).hasMatch(password) &&
      RegExp(r'\p{Ll}', unicode: true).hasMatch(password) &&
      RegExp(r'\p{N}', unicode: true).hasMatch(password) &&
      RegExp(r'[\p{P}\p{S}]', unicode: true).hasMatch(password);
  return ok ? null : strongPasswordHint;
}

/// Mirrors app/Rules/PersonName.php (accented names, apostrophes, hyphens).
String? personNameError(String? value) {
  final name = value?.trim() ?? '';
  if (name.isEmpty) return 'This field is required.';
  return RegExp(r"^\p{L}[\p{L}\p{M} .'’-]*$", unicode: true).hasMatch(name)
      ? null
      : 'Use letters, spaces, apostrophes, periods, and hyphens only.';
}

/// Mirrors app/Rules/PhoneNumber.php.
String? phMobileError(String? value) => RegExp(r'^09\d{9}$').hasMatch(value ?? '')
    ? null
    : 'Enter an 11-digit mobile number starting with 09.';
