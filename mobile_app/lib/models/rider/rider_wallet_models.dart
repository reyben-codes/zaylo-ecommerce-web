/// The rider's wallet: delivery fees earned, cashed out to GCash, and what is
/// left. Cash-outs are simulated on the server (no real money moves yet).
class RiderWallet {
  const RiderWallet({
    required this.available,
    required this.balance,
    required this.credits,
    required this.cashedOut,
    required this.minimum,
    required this.maximum,
    required this.history,
    this.source,
    this.demoNote,
  });

  factory RiderWallet.fromJson(Map<String, dynamic> json) => RiderWallet(
        available: json['available'] as bool? ?? false,
        balance: (json['balance'] as num? ?? 0).toDouble(),
        credits: (json['credits'] as num? ?? 0).toDouble(),
        cashedOut: (json['cashed_out'] as num? ?? 0).toDouble(),
        minimum: (json['minimum'] as num? ?? 100).toDouble(),
        maximum: (json['maximum'] as num? ?? 50000).toDouble(),
        source: json['source'] as String?,
        demoNote: json['demo_note'] as String?,
        history: [
          for (final row in json['history'] as List? ?? const [])
            if (row is Map<String, dynamic>) WalletCashout.fromJson(row),
        ],
      );

  /// False until the server's database has the wallet table.
  final bool available;
  final double balance;
  final double credits;
  final double cashedOut;
  final double minimum;
  final double maximum;
  final String? source;
  final String? demoNote;
  final List<WalletCashout> history;

  bool get canCashOut => available && balance >= minimum;

  /// The most the rider can send right now.
  double get limit => balance < maximum ? balance : maximum;

  RiderWallet copyWith({double? balance, double? cashedOut, List<WalletCashout>? history}) => RiderWallet(
        available: available,
        balance: balance ?? this.balance,
        credits: credits,
        cashedOut: cashedOut ?? this.cashedOut,
        minimum: minimum,
        maximum: maximum,
        source: source,
        demoNote: demoNote,
        history: history ?? this.history,
      );
}

class WalletCashout {
  const WalletCashout({
    required this.id,
    required this.amount,
    required this.gcashNumber,
    required this.gcashName,
    required this.reference,
    this.createdAt,
  });

  factory WalletCashout.fromJson(Map<String, dynamic> json) => WalletCashout(
        id: json['id'] as int? ?? 0,
        amount: (json['amount'] as num? ?? 0).toDouble(),
        gcashNumber: json['gcash_number'] as String? ?? '',
        gcashName: json['gcash_name'] as String? ?? '',
        reference: json['reference'] as String? ?? '',
        createdAt: DateTime.tryParse(json['created_at'] as String? ?? '')?.toLocal(),
      );

  final int id;
  final double amount;

  /// Masked by the server, e.g. `0917 ••• 4567`.
  final String gcashNumber;
  final String gcashName;
  final String reference;
  final DateTime? createdAt;
}

/// The server's answer to a cash-out: its message, and the wallet after it.
class WalletCashoutResult {
  const WalletCashoutResult({required this.message, required this.wallet});

  final String message;
  final RiderWallet wallet;
}
