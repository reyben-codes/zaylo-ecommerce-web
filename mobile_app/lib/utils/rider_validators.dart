import 'dart:io';

/// Rider sign-up validators. Each mirrors a rule in
/// app/Http/Requests/RegisterRiderRequest.php and uses the same message, so a
/// rider sees the same wording whether the app or the server catches it.
/// "Already registered" checks need the database and come from the server.

const riderNameMinLength = 2;
const riderNameMaxLength = 50;
const riderLicenseFormat = 'A00-00-000000';
const riderPlateLength = 6;
const riderDocumentExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
const riderDocumentMaxBytes = 5 * 1024 * 1024; // 5.0 MB, the server's max:5120

/// Letters (accents and ñ included) and spaces only, 2 to 50 characters.
String? riderNameError(String? value) {
  final name = value?.trim() ?? '';
  if (name.isEmpty) return 'This field is required.';
  if (name.runes.length < riderNameMinLength) return 'Use at least $riderNameMinLength characters.';
  if (name.runes.length > riderNameMaxLength) return 'Use $riderNameMaxLength characters or fewer.';
  return RegExp(r'^\p{L}[\p{L}\p{M} ]*$', unicode: true).hasMatch(name) ? null : 'Use letters and spaces only.';
}

/// Needs a name, an "@", a domain, and an extension such as .com — no spaces.
String? riderEmailError(String? value) {
  final email = value ?? '';
  if (email.trim().isEmpty) return 'Enter your email address.';
  return RegExp(r'^[^\s@]+@[^\s@.]+(\.[^\s@.]+)*\.[A-Za-z]{2,}$').hasMatch(email)
      ? null
      : 'Enter a valid email address, like name@example.com, without spaces.';
}

/// Exactly 11 digits starting with 09. The start is checked first, so a
/// number like 1917... or 08... is rejected from its first wrong digit,
/// before it is long enough to count.
String? riderPhoneError(String? value) {
  final phone = value ?? '';
  if (phone.isEmpty) return 'Enter your mobile number.';
  if (!'09'.startsWith(phone.length < 2 ? phone : phone.substring(0, 2))) {
    return 'Your mobile number must start with 09.';
  }
  if (!RegExp(r'^\d{11}$').hasMatch(phone)) return 'Your mobile number must be exactly 11 digits.';
  return null;
}

/// LTO format A00-00-000000: a letter, then 2-2-6 digits (11 characters plus dashes).
String? riderLicenseNumberError(String? value) =>
    RegExp(r'^[A-Z]\d{2}-\d{2}-\d{6}$').hasMatch((value ?? '').trim().toUpperCase())
        ? null
        : 'Enter your license number in the format $riderLicenseFormat, starting with a letter.';

/// Exactly 6 letters and numbers, with at least one of each (e.g. ABC123).
String? riderPlateNumberError(String? value) {
  final plate = (value ?? '').toUpperCase();
  if (plate.length != riderPlateLength) return 'Your plate number must be exactly $riderPlateLength characters.';
  final mixed = RegExp(r'^[A-Z0-9]+$').hasMatch(plate) &&
      RegExp('[A-Z]').hasMatch(plate) &&
      RegExp(r'\d').hasMatch(plate);
  return mixed ? null : 'Use letters and numbers only, with at least one of each (for example ABC123).';
}

/// JPG, PNG, or PDF, at most 5.0 MB. [label] names the document in messages.
String? riderDocumentError(File? file, {required String label}) {
  if (file == null) return 'Upload a photo or PDF of your $label.';
  final extension = file.path.split('.').last.toLowerCase();
  if (!riderDocumentExtensions.contains(extension)) return 'Only JPG, PNG, or PDF files can be uploaded.';
  if (file.lengthSync() > riderDocumentMaxBytes) return 'The $label file must be 5.0 MB or smaller.';
  return null;
}
