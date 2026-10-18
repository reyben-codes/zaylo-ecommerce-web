/// JSON shapes from MobileRiderDashboardController (GET /mobile/rider/dashboard).
library;

import 'package:zaylo_mobile/components/address/ph_address_section.dart';
import 'package:zaylo_mobile/models/support_models.dart';

double _money(Object? value) => (value as num?)?.toDouble() ?? 0;
DateTime? _date(Object? value) => value is String ? DateTime.tryParse(value)?.toLocal() : null;

/// The rider's home address as returned by the API.
class RiderAddress {
  const RiderAddress({required this.value, required this.formatted});

  final PhAddressValue value;
  final String formatted;

  static RiderAddress? fromJson(Map<String, dynamic>? json) {
    if (json == null || json['region_code'] == null) return null;
    return RiderAddress(
      value: PhAddressValue(
        regionCode: json['region_code'] as String,
        provinceCode: json['province_code'] as String?,
        cityCode: json['city_municipality_code'] as String? ?? '',
        barangayCode: json['barangay_code'] as String? ?? '',
        line1: json['line1'] as String? ?? '',
        postalCode: json['postal_code'] as String? ?? '',
      ),
      formatted: json['formatted'] as String? ?? '',
    );
  }
}

class RiderProfile {
  const RiderProfile({
    required this.name,
    required this.email,
    this.phone,
    this.gender,
    this.genderLabel,
    this.address,
    this.logisticsPartner,
    this.vehicleLabel,
    this.plateNumber,
    this.licenseNumber,
    this.licenseExpiresOn,
    this.licenseDaysLeft,
    this.licenseExpiringSoon = false,
  });

  final String name;
  final String email;
  final String? phone;
  final String? gender;
  final String? genderLabel;
  final RiderAddress? address;

  /// The logistics partner that approved the rider and assigns their parcels.
  final String? logisticsPartner;
  final String? vehicleLabel;
  final String? plateNumber;
  final String? licenseNumber;
  final DateTime? licenseExpiresOn;

  /// Days until the license expires; negative once it has expired.
  final int? licenseDaysLeft;
  final bool licenseExpiringSoon;

  String get firstName => name.split(' ').first;

  factory RiderProfile.fromJson(Map<String, dynamic> json) => RiderProfile(
        name: json['name'] as String? ?? 'Rider',
        email: json['email'] as String? ?? '',
        phone: json['phone'] as String?,
        gender: json['gender'] as String?,
        genderLabel: json['gender_label'] as String?,
        address: RiderAddress.fromJson(json['address'] as Map<String, dynamic>?),
        logisticsPartner: json['logistics_partner'] as String?,
        vehicleLabel: json['vehicle_label'] as String?,
        plateNumber: json['plate_number'] as String?,
        licenseNumber: json['license_number'] as String?,
        licenseExpiresOn: _date(json['license_expires_on']),
        licenseDaysLeft: json['license_days_left'] as int?,
        licenseExpiringSoon: json['license_expiring_soon'] as bool? ?? false,
      );
}

class RiderStats {
  const RiderStats({
    this.available = 0,
    this.active = 0,
    this.completed = 0,
    this.completedToday = 0,
    this.earningsToday = 0,
    this.earningsTotal = 0,
  });

  final int available;
  final int active;
  final int completed;
  final int completedToday;

  /// Delivery fees from orders delivered today / ever.
  final double earningsToday;
  final double earningsTotal;

  factory RiderStats.fromJson(Map<String, dynamic> json) => RiderStats(
        available: json['available'] as int? ?? 0,
        active: json['active'] as int? ?? 0,
        completed: json['completed'] as int? ?? 0,
        completedToday: json['completed_today'] as int? ?? 0,
        earningsToday: _money(json['earnings_today']),
        earningsTotal: _money(json['earnings_total']),
      );
}

/// One line of the parcel: what the rider collects from the seller.
/// A step the rider can take on a delivery, e.g. "Could not deliver".
class RiderDeliveryAction {
  const RiderDeliveryAction({required this.status, required this.label});

  final String status;
  final String label;

  factory RiderDeliveryAction.fromJson(Map<String, dynamic> json) =>
      RiderDeliveryAction(status: json['status'] as String? ?? '', label: json['label'] as String? ?? '');
}

class RiderDeliveryItem {
  const RiderDeliveryItem({required this.name, required this.quantity});

  final String name;
  final int quantity;

  factory RiderDeliveryItem.fromJson(Map<String, dynamic> json) =>
      RiderDeliveryItem(name: json['name'] as String? ?? 'Item', quantity: json['quantity'] as int? ?? 1);
}

/// Where the rider collects the parcel: the seller's store.
class RiderPickup {
  const RiderPickup({required this.storeName, this.phone, this.address});

  final String storeName;
  final String? phone;
  final String? address;

  factory RiderPickup.fromJson(Map<String, dynamic> json) => RiderPickup(
        storeName: json['store_name'] as String? ?? 'Seller',
        phone: json['phone'] as String?,
        address: json['address'] as String?,
      );
}

class RiderDelivery {
  const RiderDelivery({
    required this.id,
    required this.trackingNumber,
    required this.status,
    required this.statusLabel,
    this.nextStatus,
    this.nextActionLabel,
    this.orderNumber,
    this.shippingAddress,
    this.recipientName,
    this.recipientPhone,
    this.notes,
    this.paymentMethod,
    this.cashToCollect = 0,
    this.deliveryFee = 0,
    this.pickedUpAt,
    this.deliveredAt,
    this.items = const [],
    this.pickup,
    this.chatOpen = false,
    this.actions = const [],
    this.unreadMessages = 0,
  });

  final int id;
  final String trackingNumber;
  final String status;
  final String statusLabel;

  /// The status the rider can move this delivery to, or null when done.
  final String? nextStatus;
  final String? nextActionLabel;
  final String? orderNumber;
  final String? shippingAddress;

  /// Shared only after the rider accepts the delivery.
  final String? recipientName;
  final String? recipientPhone;
  final String? notes;
  final String? paymentMethod;
  final double cashToCollect;
  final double deliveryFee;
  final DateTime? pickedUpAt;
  final DateTime? deliveredAt;
  final List<RiderDeliveryItem> items;
  final RiderPickup? pickup;

  /// The rider can message the buyer: the delivery is theirs and in progress.
  final bool chatOpen;

  /// Every step available now; the first is the main button ([nextStatus]).
  final List<RiderDeliveryAction> actions;
  final int unreadMessages;

  int get itemCount => items.fold(0, (sum, item) => sum + item.quantity);

  factory RiderDelivery.fromJson(Map<String, dynamic> json) => RiderDelivery(
        id: json['id'] as int,
        trackingNumber: json['tracking_number'] as String? ?? '',
        status: json['status'] as String? ?? '',
        statusLabel: json['status_label'] as String? ?? '',
        nextStatus: json['next_status'] as String?,
        nextActionLabel: json['next_action_label'] as String?,
        orderNumber: json['order_number'] as String?,
        shippingAddress: json['shipping_address'] as String?,
        recipientName: json['recipient_name'] as String?,
        recipientPhone: json['recipient_phone'] as String?,
        notes: json['notes'] as String?,
        paymentMethod: json['payment_method'] as String?,
        cashToCollect: _money(json['cash_to_collect']),
        deliveryFee: _money(json['delivery_fee']),
        pickedUpAt: _date(json['picked_up_at']),
        deliveredAt: _date(json['delivered_at']),
        items: (json['items'] as List<dynamic>? ?? const [])
            .map((item) => RiderDeliveryItem.fromJson(item as Map<String, dynamic>))
            .toList(),
        pickup: json['pickup'] is Map<String, dynamic>
            ? RiderPickup.fromJson(json['pickup'] as Map<String, dynamic>)
            : null,
        chatOpen: json['chat_open'] as bool? ?? false,
        actions: (json['actions'] as List<dynamic>? ?? const [])
            .map((item) => RiderDeliveryAction.fromJson(item as Map<String, dynamic>))
            .toList(),
        unreadMessages: json['unread_messages'] as int? ?? 0,
      );
}

class RiderDashboard {
  const RiderDashboard({
    required this.rider,
    required this.stats,
    this.active = const [],
    this.recent = const [],
    this.deliveryModeMessage,
    this.announcements = const [],
  });

  final RiderProfile rider;
  final RiderStats stats;

  /// Parcels the logistics partner assigned to the rider, still in progress.
  final List<RiderDelivery> active;
  final List<RiderDelivery> recent;

  /// Set while sellers deliver their own orders and riders have nothing to do.
  final String? deliveryModeMessage;

  /// Live notices from the ZAYLO admin for riders.
  final List<Announcement> announcements;

  static List<RiderDelivery> _deliveries(Object? list) =>
      (list as List<dynamic>? ?? const []).map((item) => RiderDelivery.fromJson(item as Map<String, dynamic>)).toList();

  factory RiderDashboard.fromJson(Map<String, dynamic> json) => RiderDashboard(
        rider: RiderProfile.fromJson(json['rider'] as Map<String, dynamic>? ?? const {}),
        stats: RiderStats.fromJson(json['stats'] as Map<String, dynamic>? ?? const {}),
        active: _deliveries(json['active']),
        recent: _deliveries(json['recent']),
        deliveryModeMessage: json['delivery_mode_message'] as String?,
        announcements: Announcement.listFrom(json['announcements']),
      );
}
