export '../../core/models/location.dart';

class Address {
  const Address({
    required this.id,
    required this.label,
    required this.recipientName,
    required this.phone,
    required this.line1,
    required this.formatted,
    required this.isDefault,
    required this.isStructured,
    this.line2,
    this.barangay,
    this.barangayCode,
    this.city,
    this.cityCode,
    this.province,
    this.provinceCode,
    this.regionName,
    this.regionCode,
    this.postalCode,
  });

  final int id;
  final String label;
  final String recipientName;
  final String phone;
  final String line1;
  final String? line2;
  final String? barangay;
  final String? barangayCode;
  final String? city;
  final String? cityCode;
  final String? province;
  final String? provinceCode;
  final String? regionName;
  final String? regionCode;
  final String? postalCode;
  final String formatted;
  final bool isDefault;

  /// False for older addresses saved before PSGC locations; checkout requires true.
  final bool isStructured;

  factory Address.fromJson(Map<String, dynamic> json) => Address(
        id: (json['id'] as num).toInt(),
        label: json['label'] as String? ?? 'Home',
        recipientName: json['recipient_name'] as String? ?? '',
        phone: json['phone'] as String? ?? '',
        line1: json['line1'] as String? ?? '',
        line2: json['line2'] as String?,
        barangay: json['barangay'] as String?,
        barangayCode: json['barangay_code'] as String?,
        city: json['city'] as String?,
        cityCode: json['city_municipality_code'] as String?,
        province: json['province'] as String?,
        provinceCode: json['province_code'] as String?,
        regionName: json['region_name'] as String?,
        regionCode: json['region_code'] as String?,
        postalCode: json['postal_code'] as String?,
        formatted: json['formatted'] as String? ?? '',
        isDefault: json['is_default'] as bool? ?? false,
        isStructured: json['is_structured'] as bool? ?? false,
      );
}

class AddressInput {
  const AddressInput({
    required this.label,
    required this.recipientName,
    required this.phone,
    required this.regionCode,
    required this.cityCode,
    required this.barangayCode,
    required this.line1,
    required this.postalCode,
    this.provinceCode,
    this.line2,
    this.isDefault = false,
  });

  final String label;
  final String recipientName;
  final String phone;
  final String regionCode;
  final String? provinceCode;
  final String cityCode;
  final String barangayCode;
  final String line1;
  final String? line2;
  final String postalCode;
  final bool isDefault;

  Map<String, dynamic> toJson() => {
        'label': label,
        'recipient_name': recipientName,
        'phone': phone,
        'region_code': regionCode,
        if (provinceCode != null) 'province_code': provinceCode,
        'city_municipality_code': cityCode,
        'barangay_code': barangayCode,
        'line1': line1,
        if (line2 != null && line2!.isNotEmpty) 'line2': line2,
        'postal_code': postalCode,
        'is_default': isDefault,
      };
}

/// A PSGC region, province, city/municipality or barangay.
