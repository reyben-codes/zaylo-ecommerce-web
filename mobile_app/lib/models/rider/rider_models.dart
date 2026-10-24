import 'dart:io';

import 'package:zaylo_mobile/components/address/ph_address_section.dart';

/// Mirrors Rider::VEHICLE_TYPES on the server; used until the sign-up
/// options load, since the server sends the current list.
const riderVehicleTypes = ['Motorcycle', 'Bicycle', 'Car', 'Van', 'Truck'];

/// Mirrors Rider::UNLICENSED_VEHICLES: no license, plate or OR/CR needed.
const riderUnlicensedVehicleTypes = {'Bicycle'};

/// A logistics partner a new rider can apply to.
class LogisticsPartner {
  const LogisticsPartner({required this.id, required this.name, this.serviceArea});

  final int id;
  final String name;
  final String? serviceArea;

  factory LogisticsPartner.fromJson(Map<String, dynamic> json) => LogisticsPartner(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        serviceArea: json['service_area'] as String?,
      );
}

/// GET /mobile/rider/logistics-partners: the choices on the sign-up form.
class RiderSignUpOptions {
  const RiderSignUpOptions({
    required this.partners,
    this.vehicleTypes = riderVehicleTypes,
    this.unlicensedVehicleTypes = riderUnlicensedVehicleTypes,
  });

  final List<LogisticsPartner> partners;
  final List<String> vehicleTypes;
  final Set<String> unlicensedVehicleTypes;

  factory RiderSignUpOptions.fromJson(Map<String, dynamic> json) => RiderSignUpOptions(
        partners: (json['partners'] as List<dynamic>? ?? const [])
            .map((item) => LogisticsPartner.fromJson(item as Map<String, dynamic>))
            .toList(),
        vehicleTypes: (json['vehicle_types'] as List<dynamic>?)?.cast<String>() ?? riderVehicleTypes,
        unlicensedVehicleTypes:
            (json['unlicensed_vehicle_types'] as List<dynamic>?)?.cast<String>().toSet() ?? riderUnlicensedVehicleTypes,
      );
}

/// Mirrors User::GENDERS on the server (value => label).
const riderGenders = {'male': 'Male', 'female': 'Female'};

/// Everything the rider sign-up form collects. Field names in [fields]
/// match RegisterRiderRequest, so server errors map back to the form.
class RiderRegistration {
  const RiderRegistration({
    required this.firstName,
    required this.lastName,
    required this.dateOfBirth,
    required this.gender,
    required this.address,
    required this.email,
    required this.phone,
    required this.password,
    required this.passwordConfirmation,
    required this.logisticsProviderId,
    required this.vehicleType,
    this.licenseNumber = '',
    this.licenseExpiresOn = '',
    this.plateNumber = '',
    this.licenseImage,
    this.orCrImage,
    this.termsAccepted = false,
  });

  final String firstName;
  final String lastName;

  /// `YYYY-MM-DD`, like every date the API accepts.
  final String dateOfBirth;
  final String gender;

  /// Home address (PSGC codes plus street and postal code).
  final PhAddressValue address;
  final String email;
  final String phone;
  final String password;
  final String passwordConfirmation;

  /// The logistics partner reviewing the application.
  final int logisticsProviderId;
  final String vehicleType;

  /// Empty for bicycles, which need no license, plate or OR/CR.
  final String licenseNumber;
  final String licenseExpiresOn;
  final String plateNumber;
  final File? licenseImage;
  final File? orCrImage;

  /// Set once the rider has read the Rider Terms to the end.
  final bool termsAccepted;

  Map<String, String> get fields => {
        'first_name': firstName,
        'last_name': lastName,
        'date_of_birth': dateOfBirth,
        'gender': gender,
        ...address.toFields(),
        'email': email,
        'phone': phone,
        'password': password,
        'password_confirmation': passwordConfirmation,
        'logistics_provider_id': '$logisticsProviderId',
        'vehicle_type': vehicleType,
        'license_number': licenseNumber,
        'license_expires_on': licenseExpiresOn,
        'plate_number': plateNumber,
        'terms_accepted': termsAccepted ? '1' : '0',
      };

  Map<String, File> get files => {
        if (licenseImage != null) 'license_image': licenseImage!,
        if (orCrImage != null) 'or_cr_image': orCrImage!,
      };
}
