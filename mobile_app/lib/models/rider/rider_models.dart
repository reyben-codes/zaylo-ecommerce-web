import 'dart:io';

import 'package:zaylo_mobile/components/address/ph_address_section.dart';

/// Mirrors RiderApplication::VEHICLE_TYPES on the server (value => label).
const riderVehicleTypes = {
  'motorcycle': 'Motorcycle',
  'tricycle': 'Tricycle',
  'car': 'Car',
  'van': 'Van',
};

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
    required this.licenseNumber,
    required this.licenseExpiresOn,
    required this.vehicleType,
    required this.plateNumber,
    required this.licenseImage,
    required this.orCrImage,
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
  final String licenseNumber;
  final String licenseExpiresOn;
  final String vehicleType;
  final String plateNumber;
  final File licenseImage;
  final File orCrImage;

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
        'license_number': licenseNumber,
        'license_expires_on': licenseExpiresOn,
        'vehicle_type': vehicleType,
        'plate_number': plateNumber,
        'terms_accepted': termsAccepted ? '1' : '0',
      };

  Map<String, File> get files => {'license_image': licenseImage, 'or_cr_image': orCrImage};
}
