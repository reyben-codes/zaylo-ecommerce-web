/// The rider wallet from GET /mobile/rider/wallet.
library;

import 'package:zaylo_mobile/models/json_parsing.dart';

/// The commission one delivered parcel earned.
class RiderCommissionEntry {
  const RiderCommissionEntry({required this.id, required this.amount, this.trackingNumber, this.createdAt});

  final int id;
  final double amount;
  final String? trackingNumber;
  final DateTime? createdAt;

  factory RiderCommissionEntry.fromJson(Map<String, dynamic> json) => RiderCommissionEntry(
        id: json['id'] as int,
        amount: parseMoney(json['amount']),
        trackingNumber: json['tracking_number'] as String?,
        createdAt: parseDate(json['created_at']),
      );
}

/// A request to send wallet money to GCash; the rider's logistics partner sends it.
class RiderWithdrawalEntry {
  const RiderWithdrawalEntry({
    required this.id,
    required this.amount,
    required this.gcashNumber,
    required this.gcashName,
    required this.status,
    required this.statusLabel,
    this.reference,
    this.rejectionReason,
    this.createdAt,
  });

  final int id;
  final double amount;

  /// Formatted, e.g. "0917 123 4567".
  final String gcashNumber;
  final String gcashName;

  /// pending, paid or rejected.
  final String status;
  final String statusLabel;

  /// The GCash reference number, once sent.
  final String? reference;
  final String? rejectionReason;
  final DateTime? createdAt;

  factory RiderWithdrawalEntry.fromJson(Map<String, dynamic> json) => RiderWithdrawalEntry(
        id: json['id'] as int,
        amount: parseMoney(json['amount']),
        gcashNumber: json['gcash_number'] as String? ?? '',
        gcashName: json['gcash_name'] as String? ?? '',
        status: json['status'] as String? ?? 'pending',
        statusLabel: json['status_label'] as String? ?? 'Processing',
        reference: json['reference'] as String?,
        rejectionReason: json['rejection_reason'] as String?,
        createdAt: parseDate(json['created_at']),
      );
}

class RiderWallet {
  const RiderWallet({
    required this.balance,
    required this.earned,
    required this.pending,
    required this.withdrawn,
    required this.parcels,
    required this.commissionPerParcel,
    required this.minWithdrawal,
    this.gcashNumber,
    this.gcashName,
    this.hasSavedAccount = false,
    this.commissions = const [],
    this.withdrawals = const [],
    this.note,
  });

  /// What the rider can withdraw now.
  final double balance;
  final double earned;

  /// Requested and waiting to be sent.
  final double pending;

  /// Already sent to GCash.
  final double withdrawn;
  final int parcels;
  final double commissionPerParcel;
  final double minWithdrawal;

  /// Where withdrawals go: the account the rider saved, or else the one they used last.
  final String? gcashNumber;
  final String? gcashName;

  /// Whether the rider saved a GCash account; withdrawals need one.
  final bool hasSavedAccount;
  final List<RiderCommissionEntry> commissions;
  final List<RiderWithdrawalEntry> withdrawals;
  final String? note;

  bool get canWithdraw => balance >= minWithdrawal;

  factory RiderWallet.fromJson(Map<String, dynamic> json) {
    final gcash = json['gcash'] as Map<String, dynamic>?;
    return RiderWallet(
      balance: parseMoney(json['balance']),
      earned: parseMoney(json['earned']),
      pending: parseMoney(json['pending']),
      withdrawn: parseMoney(json['withdrawn']),
      parcels: json['parcels'] as int? ?? 0,
      commissionPerParcel: parseMoney(json['commission_per_parcel']),
      minWithdrawal: parseMoney(json['min_withdrawal']),
      gcashNumber: gcash?['number'] as String?,
      gcashName: gcash?['name'] as String?,
      hasSavedAccount: gcash?['saved'] as bool? ?? false,
      commissions: (json['commissions'] as List<dynamic>? ?? const [])
          .map((item) => RiderCommissionEntry.fromJson(item as Map<String, dynamic>))
          .toList(),
      withdrawals: (json['withdrawals'] as List<dynamic>? ?? const [])
          .map((item) => RiderWithdrawalEntry.fromJson(item as Map<String, dynamic>))
          .toList(),
      note: json['note'] as String?,
    );
  }
}
