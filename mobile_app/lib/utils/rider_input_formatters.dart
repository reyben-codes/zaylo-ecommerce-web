import 'package:flutter/services.dart';

import 'package:zaylo_mobile/utils/rider_validators.dart';

/// Names: letters and spaces only, at most 50 characters, first letter of
/// every word capitalized as the rider types or pastes.
final riderNameInputFormatters = <TextInputFormatter>[
  FilteringTextInputFormatter.allow(RegExp(r'[\p{L}\p{M} ]', unicode: true)),
  LengthLimitingTextInputFormatter(riderNameMaxLength),
  const _CapitalizeWordsFormatter(),
];

/// Email: spaces are never valid, so they are dropped as typed.
final riderEmailInputFormatters = <TextInputFormatter>[
  FilteringTextInputFormatter.deny(RegExp(r'\s')),
];

/// Mobile number: digits only, at most 11.
final riderPhoneInputFormatters = <TextInputFormatter>[
  FilteringTextInputFormatter.digitsOnly,
  LengthLimitingTextInputFormatter(11),
];

/// License number: types as A00-00-000000, uppercasing the letter and adding
/// the dashes automatically.
final riderLicenseInputFormatters = <TextInputFormatter>[const _LicenseNumberFormatter()];

/// Plate number: up to 7 letters/digits, letters uppercased.
final riderPlateInputFormatters = <TextInputFormatter>[
  FilteringTextInputFormatter.allow(RegExp('[A-Za-z0-9]')),
  LengthLimitingTextInputFormatter(riderPlateLength),
  const _UppercaseFormatter(),
];

class _UppercaseFormatter extends TextInputFormatter {
  const _UppercaseFormatter();

  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) =>
      newValue.copyWith(text: newValue.text.toUpperCase());
}

/// Uppercases the first letter of each word and leaves the rest as typed,
/// so names like "McArthur" are kept.
class _CapitalizeWordsFormatter extends TextInputFormatter {
  const _CapitalizeWordsFormatter();

  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) {
    final text = newValue.text.replaceAllMapped(
      RegExp(r'(^|\s)(\p{Ll})', unicode: true),
      (match) => '${match[1]}${match[2]!.toUpperCase()}',
    );
    // Uppercasing can change length for a few letters (e.g. "ß" -> "SS"); keep the
    // caret at the end in that case rather than pointing past the text.
    if (text.length == newValue.text.length) return newValue.copyWith(text: text);
    return newValue.copyWith(text: text, selection: TextSelection.collapsed(offset: text.length));
  }
}

/// Keeps only the characters the format allows (one letter, then up to 10
/// digits) and re-inserts the dashes, leaving the caret at the end.
class _LicenseNumberFormatter extends TextInputFormatter {
  const _LicenseNumberFormatter();

  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) {
    final raw = newValue.text.toUpperCase().replaceAll(RegExp('[^A-Z0-9]'), '');
    if (raw.isEmpty) return const TextEditingValue();

    // The first character must be a letter; anything else is kept so the
    // validator can explain the format instead of the key doing nothing.
    final first = raw[0];
    final digits = raw.substring(1).replaceAll(RegExp(r'\D'), '');
    final capped = digits.length > 10 ? digits.substring(0, 10) : digits;

    final buffer = StringBuffer(first);
    for (var i = 0; i < capped.length; i++) {
      if (i == 2 || i == 4) buffer.write('-');
      buffer.write(capped[i]);
    }
    final text = buffer.toString();
    return TextEditingValue(text: text, selection: TextSelection.collapsed(offset: text.length));
  }
}
