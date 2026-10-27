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
    this.avatarUrl,
  });

  final String name;

  /// The profile photo, or null to show initials.
  final String? avatarUrl;
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
        avatarUrl: json['avatar_url'] as String?,
      );
}

class RiderStats {
  const RiderStats({
    this.available = 0,
    this.active = 0,
    this.awaitingAcceptance = 0,
    this.completed = 0,
    this.completedToday = 0,
    this.earningsToday = 0,
    this.earningsTotal = 0,
  });

  final int available;
  final int active;

  /// New assignments the rider has not accepted or declined yet.
  final int awaitingAcceptance;
  final int completed;
  final int completedToday;

  /// Delivery fees from orders delivered today / ever.
  final double earningsToday;
  final double earningsTotal;

  factory RiderStats.fromJson(Map<String, dynamic> json) => RiderStats(
        available: json['available'] as int? ?? 0,
        active: json['active'] as int? ?? 0,
        awaitingAcceptance: json['awaiting_acceptance'] as int? ?? 0,
        completed: json['completed'] as int? ?? 0,
        completedToday: json['completed_today'] as int? ?? 0,
        earningsToday: _money(json['earnings_today']),
        earningsTotal: _money(json['earnings_total']),
      );
}

/// One line of the parcel: what the rider collects from the seller.
/// A step the rider can take on a delivery, e.g. "Could not deliver".
class RiderDeliveryAction {
  const RiderDeliveryAction({required this.status, required this.label, this.needsScan = false});

  final String status;
  final String label;

  /// Taken by scanning the waybill's barcode or QR code (starting and finishing a delivery).
  final bool needsScan;

  factory RiderDeliveryAction.fromJson(Map<String, dynamic> json) => RiderDeliveryAction(
        status: json['status'] as String? ?? '',
        label: json['label'] as String? ?? '',
        needsScan: json['needs_scan'] as bool? ?? false,
      );
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

/// A reason the rider picks when declining an assignment or reporting a failed attempt.
class RiderReason {
  const RiderReason(this.code, this.label);

  final String code;
  final String label;

  /// "Other" asks the rider to describe what happened.
  bool get needsNote => code == 'other';

  static List<RiderReason> listFrom(Object? json, List<RiderReason> fallback) {
    final list = (json as List<dynamic>? ?? const [])
        .map((item) => item as Map<String, dynamic>)
        .map((item) => RiderReason(item['code'] as String? ?? 'other', item['label'] as String? ?? 'Other reason'))
        .toList();
    return list.isEmpty ? fallback : list;
  }
}

/// The reasons offered by the server (GET /mobile/rider/dashboard), with the same lists built in for older servers.
class RiderReasons {
  const RiderReasons({this.decline = defaultDecline, this.failed = defaultFailed});

  final List<RiderReason> decline;
  final List<RiderReason> failed;

  static const defaultDecline = [
    RiderReason('too_far', 'Outside my delivery area'),
    RiderReason('too_many', 'I have too many parcels'),
    RiderReason('vehicle', 'Vehicle problem'),
    RiderReason('too_large', 'Parcel too large or heavy for my vehicle'),
    RiderReason('unavailable', 'Not available today'),
    RiderReason('other', 'Other reason'),
  ];

  static const defaultFailed = [
    RiderReason('buyer_cancelled', 'Buyer cancelled the order at delivery'),
    RiderReason('buyer_refused', 'Buyer refused to accept the parcel'),
    RiderReason('buyer_unavailable', 'Buyer was not available to receive it'),
    RiderReason('unreachable', 'Could not reach the buyer by phone'),
    RiderReason('wrong_address', 'Wrong or incomplete address'),
    RiderReason('no_cash', 'Buyer could not pay the cash on delivery'),
    RiderReason('rescheduled', 'Buyer asked to deliver another day'),
    RiderReason('unsafe', 'Area unsafe or not accessible'),
    RiderReason('other', 'Other reason'),
  ];

  factory RiderReasons.fromJson(Map<String, dynamic>? json) => RiderReasons(
        decline: RiderReason.listFrom(json?['decline'], defaultDecline),
        failed: RiderReason.listFrom(json?['failed'], defaultFailed),
      );
}

/// Why the last delivery attempt failed.
class RiderFailure {
  const RiderFailure({this.reason, this.note, this.at});

  final String? reason;
  final String? note;
  final DateTime? at;

  /// "Buyer was not available to receive it (gate was locked)".
  String get summary => [reason ?? 'Delivery failed', if (note != null && note!.isNotEmpty) '($note)'].join(' ');

  static RiderFailure? fromJson(Object? json) => json is Map<String, dynamic>
      ? RiderFailure(reason: json['reason'] as String?, note: json['note'] as String?, at: _date(json['at']))
      : null;
}

/// One thing the rider did with a parcel, for its details screen.
class RiderTimelineEntry {
  const RiderTimelineEntry({required this.action, required this.label, this.reason, this.note, this.at});

  final String action;
  final String label;
  final String? reason;
  final String? note;
  final DateTime? at;

  factory RiderTimelineEntry.fromJson(Map<String, dynamic> json) => RiderTimelineEntry(
        action: json['action'] as String? ?? '',
        label: json['label'] as String? ?? '',
        reason: json['reason'] as String?,
        note: json['note'] as String?,
        at: _date(json['at']),
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
    this.scanCode = '',
    this.needsAcceptance = false,
    this.lastFailure,
    this.timeline = const [],
    this.hasDeliveryPhoto = false,
    this.canAddDeliveryPhoto = false,
  });

  final int id;
  final String trackingNumber;

  /// The tracking number in the waybill's barcode and QR code.
  final String scanCode;
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

  /// A new assignment: the rider accepts or declines it before anything else.
  final bool needsAcceptance;

  /// Why the last attempt failed, while it matters (the parcel is still with the rider).
  final RiderFailure? lastFailure;

  /// Everything the rider did with the parcel; only on the details screen.
  final List<RiderTimelineEntry> timeline;

  /// Proof of delivery: the rider's photo of the parcel at the door, shown to the seller and buyer.
  final bool hasDeliveryPhoto;

  /// The parcel was delivered in the last day, so a photo can still be added or replaced.
  final bool canAddDeliveryPhoto;

  int get itemCount => items.fold(0, (sum, item) => sum + item.quantity);

  /// Whether the main next step is taken by scanning the parcel.
  bool get nextNeedsScan => actions.isNotEmpty && actions.first.needsScan;

  /// Whether [code], read from a waybill, is this delivery's.
  bool matchesCode(String code) {
    final scanned = code.trim().toUpperCase();
    return scanned.isNotEmpty && (scanned == scanCode.toUpperCase() || scanned == trackingNumber.toUpperCase());
  }

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
        scanCode: json['scan_code'] as String? ?? json['tracking_number'] as String? ?? '',
        needsAcceptance: json['needs_acceptance'] as bool? ?? false,
        lastFailure: RiderFailure.fromJson(json['last_failure']),
        timeline: (json['timeline'] as List<dynamic>? ?? const [])
            .map((item) => RiderTimelineEntry.fromJson(item as Map<String, dynamic>))
            .toList(),
        hasDeliveryPhoto: json['has_delivery_photo'] as bool? ?? false,
        canAddDeliveryPhoto: json['can_add_delivery_photo'] as bool? ?? false,
      );
}

/// What happened when the rider scanned a parcel.
class RiderScanResult {
  const RiderScanResult({required this.message, required this.step, required this.delivery});

  final String message;

  /// out_for_delivery or delivered.
  final String step;
  final RiderDelivery delivery;

  factory RiderScanResult.fromJson(Map<String, dynamic> json) => RiderScanResult(
        message: json['message'] as String? ?? '',
        step: json['step'] as String? ?? '',
        delivery: RiderDelivery.fromJson(json['delivery'] as Map<String, dynamic>),
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
    this.reasons = const RiderReasons(),
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

  /// Reasons for declining an assignment or reporting a failed attempt.
  final RiderReasons reasons;

  /// New assignments waiting for the rider's answer, then the ones in progress.
  List<RiderDelivery> get newAssignments => active.where((delivery) => delivery.needsAcceptance).toList();
  List<RiderDelivery> get inProgress => active.where((delivery) => !delivery.needsAcceptance).toList();

  static List<RiderDelivery> _deliveries(Object? list) =>
      (list as List<dynamic>? ?? const []).map((item) => RiderDelivery.fromJson(item as Map<String, dynamic>)).toList();

  factory RiderDashboard.fromJson(Map<String, dynamic> json) => RiderDashboard(
        rider: RiderProfile.fromJson(json['rider'] as Map<String, dynamic>? ?? const {}),
        stats: RiderStats.fromJson(json['stats'] as Map<String, dynamic>? ?? const {}),
        active: _deliveries(json['active']),
        recent: _deliveries(json['recent']),
        deliveryModeMessage: json['delivery_mode_message'] as String?,
        announcements: Announcement.listFrom(json['announcements']),
        reasons: RiderReasons.fromJson(json['reasons'] as Map<String, dynamic>?),
      );
}
