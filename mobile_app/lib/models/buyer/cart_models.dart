import 'package:zaylo_mobile/models/buyer/address_models.dart';
import 'package:zaylo_mobile/models/buyer/catalog_models.dart';
import 'package:zaylo_mobile/models/json_parsing.dart';

class CartItem {
  const CartItem({
    required this.id,
    required this.quantity,
    required this.unitPrice,
    required this.lineTotal,
    required this.availableStock,
    this.variantName,
    this.sellerId,
    this.sellerName,
    this.product,
  });

  final int id;
  final int quantity;
  final double unitPrice;
  final double lineTotal;
  final int availableStock;
  final String? variantName;
  final int? sellerId;
  final String? sellerName;
  final Product? product;

  factory CartItem.fromJson(Map<String, dynamic> json) => CartItem(
        id: (json['id'] as num).toInt(),
        quantity: (json['quantity'] as num).toInt(),
        unitPrice: parseMoney(json['unit_price']),
        lineTotal: parseMoney(json['line_total']),
        availableStock: (json['available_stock'] as num).toInt(),
        variantName: json['variant_name'] as String?,
        sellerId: (json['seller_id'] as num?)?.toInt(),
        sellerName: json['seller_name'] as String?,
        product: json['product'] == null ? null : Product.fromJson(json['product'] as Map<String, dynamic>),
      );
}

class CartSummary {
  const CartSummary({required this.items, required this.count, required this.subtotal, this.addedItemId});

  const CartSummary.empty()
      : items = const [],
        count = 0,
        subtotal = 0,
        addedItemId = null;

  final List<CartItem> items;
  final int count;
  final double subtotal;

  /// Set after an add-to-bag call, so "Buy now" can check out just that line.
  final int? addedItemId;

  factory CartSummary.fromJson(Map<String, dynamic> json) => CartSummary(
        items: (json['items'] as List<dynamic>)
            .map((item) => CartItem.fromJson(item as Map<String, dynamic>))
            .toList(),
        count: (json['count'] as num).toInt(),
        subtotal: parseMoney(json['subtotal']),
        addedItemId: (json['added_item_id'] as num?)?.toInt(),
      );
}

class CheckoutTotals {
  const CheckoutTotals({
    required this.subtotal,
    required this.shipping,
    required this.shippingDiscount,
    required this.total,
    this.voucherDiscount = 0,
  });

  final double subtotal;
  final double shipping;
  final double shippingDiscount;

  /// A discount voucher's pesos off the products of shops that joined it.
  final double voucherDiscount;
  final double total;

  factory CheckoutTotals.fromJson(Map<String, dynamic> json) => CheckoutTotals(
        subtotal: parseMoney(json['subtotal']),
        shipping: parseMoney(json['shipping']),
        shippingDiscount: parseMoney(json['shipping_discount']),
        voucherDiscount: parseMoney(json['voucher_discount']),
        total: parseMoney(json['total']),
      );
}

/// A courier truck the buyer can choose at checkout, as listed by the server.
class CourierOption {
  const CourierOption({required this.key, required this.name, required this.description});

  final String key;
  final String name;
  final String description;

  factory CourierOption.fromJson(Map<String, dynamic> json) => CourierOption(
        key: json['key'] as String,
        name: json['name'] as String,
        description: json['description'] as String? ?? '',
      );
}

/// What one logistics partner charges per seller to one saved address.
class ShippingRate {
  const ShippingRate({required this.fee, required this.label, this.zone});

  final double fee;

  /// How far the address is from the partner's hub, e.g. "Same province".
  final String label;
  final String? zone;

  factory ShippingRate.fromJson(Map<String, dynamic> json) => ShippingRate(
        fee: parseMoney(json['fee']),
        label: json['label'] as String? ?? 'Standard rate',
        zone: json['zone'] as String?,
      );
}

/// A logistics partner the buyer can pick at checkout. The shipping fee depends on how far the delivery address is
/// from the partner's hub, so each partner lists its fee to every saved address.
class LogisticsPartnerOption {
  const LogisticsPartnerOption({required this.id, required this.name, this.hub = '', this.rates = const {}});

  final int id;
  final String name;

  /// Where the partner's hub is, e.g. "City of Bacoor, Cavite".
  final String hub;

  /// Keyed by address id.
  final Map<int, ShippingRate> rates;

  factory LogisticsPartnerOption.fromJson(Map<String, dynamic> json) => LogisticsPartnerOption(
        id: json['id'] as int,
        name: json['name'] as String,
        hub: json['hub'] as String? ?? '',
        rates: {
          for (final MapEntry(:key, :value) in (json['rates'] as Map<String, dynamic>? ?? const {}).entries)
            if (int.tryParse(key) case final id?) id: ShippingRate.fromJson(value as Map<String, dynamic>),
        },
      );
}

class CheckoutQuote {
  const CheckoutQuote({
    required this.items,
    required this.totals,
    required this.addresses,
    this.voucherCode,
    this.voucherLabel,
    this.deliveryFrom,
    this.deliveryTo,
    this.identityVerified = true,
    this.identityRequiredFor = const [],
    this.couriers = const [],
    this.logisticsPartners = const [],
    this.defaultPartnerId,
    this.sellerCount = 1,
    this.gcashSellers = const [],
    this.availableVouchers = const [],
  });

  final List<CartItem> items;

  /// Each seller's own GCash account (null when they have none) and their items subtotal.
  final List<GcashSeller> gcashSellers;

  /// GCash can be chosen only when every seller in the order has a GCash account.
  bool get gcashAvailable => gcashSellers.isNotEmpty && gcashSellers.every((seller) => seller.number != null);
  final CheckoutTotals totals;
  final List<Address> addresses;
  final String? voucherCode;

  /// What the applied voucher does, e.g. "10% off, up to ₱100".
  final String? voucherLabel;
  final DateTime? deliveryFrom;
  final DateTime? deliveryTo;

  /// False when the selected items include 18+ products and the buyer's ID is not approved yet; such orders are
  /// refused until it is. Other products need no ID.
  final bool identityVerified;

  /// The 18+ products that need the approved ID.
  final List<String> identityRequiredFor;

  /// Courier trucks to choose from; the first is the default.
  final List<CourierOption> couriers;

  /// Partners to choose from, with their fees; empty when the server charges a flat fee.
  final List<LogisticsPartnerOption> logisticsPartners;

  /// The partner nearest the first usable address, which [totals] were priced with.
  final int? defaultPartnerId;

  /// Shipping is charged once per seller.
  final int sellerCount;
  final List<AvailableVoucher> availableVouchers;

  /// The totals with [partnerId] delivering to [addressId], or the server's [totals] when that pair has no rate.
  CheckoutTotals totalsFor(int? partnerId, int? addressId) {
    final rate = logisticsPartners.where((partner) => partner.id == partnerId).firstOrNull?.rates[addressId];
    if (rate == null) return totals;
    final shipping = rate.fee * sellerCount;
    final discount = totals.shippingDiscount > 0 ? shipping : 0.0;
    return CheckoutTotals(
      subtotal: totals.subtotal,
      shipping: shipping,
      shippingDiscount: discount,
      voucherDiscount: totals.voucherDiscount,
      total: totals.subtotal + shipping - discount - totals.voucherDiscount,
    );
  }

  factory CheckoutQuote.fromJson(Map<String, dynamic> json) {
    final delivery = json['estimated_delivery'] as Map<String, dynamic>?;
    return CheckoutQuote(
      items: (json['items'] as List<dynamic>)
          .map((item) => CartItem.fromJson(item as Map<String, dynamic>))
          .toList(),
      totals: CheckoutTotals.fromJson(json['totals'] as Map<String, dynamic>),
      addresses: (json['addresses'] as List<dynamic>)
          .map((item) => Address.fromJson(item as Map<String, dynamic>))
          .toList(),
      voucherCode: json['voucher_code'] as String?,
      voucherLabel: json['voucher_label'] as String?,
      deliveryFrom: parseDate(delivery?['from']),
      deliveryTo: parseDate(delivery?['to']),
      identityVerified: json['identity_verified'] as bool? ?? true,
      identityRequiredFor: (json['identity_required_for'] as List<dynamic>? ?? const []).map((name) => '$name').toList(),
      couriers: (json['couriers'] as List<dynamic>? ?? const [])
          .map((item) => CourierOption.fromJson(item as Map<String, dynamic>))
          .toList(),
      logisticsPartners: (json['logistics_partners'] as List<dynamic>? ?? const [])
          .map((item) => LogisticsPartnerOption.fromJson(item as Map<String, dynamic>))
          .toList(),
      defaultPartnerId: json['default_logistics_provider_id'] as int?,
      sellerCount: json['seller_count'] as int? ?? 1,
      gcashSellers: (json['gcash_sellers'] as List<dynamic>? ?? const [])
          .map((item) => GcashSeller.fromJson(item as Map<String, dynamic>))
          .toList(),
      availableVouchers: (json['available_vouchers'] as List<dynamic>? ?? const [])
          .map((item) => AvailableVoucher.fromJson(item as Map<String, dynamic>))
          .toList(),
    );
  }
}

class AvailableVoucher {
  const AvailableVoucher({
    required this.id,
    required this.code,
    this.name,
    required this.type,
    required this.isFreeShipping,
    required this.offerLabel,
    required this.description,
    this.minSpend,
    this.expiresAt,
    this.canApply = true,
    this.reasonCannotApply,
    this.estimatedDiscount = 0,
    this.shopName = 'All shops',
    this.isClaimed = false,
    this.canClaim = false,
    this.isFullyClaimed = false,
    this.remainingClaims,
    this.usageLimit,
  });

  final int id;
  final String code;
  final String? name;
  final String type;
  final bool isFreeShipping;
  final String offerLabel;
  final String description;
  final double? minSpend;
  final String? expiresAt;
  final bool canApply;
  final String? reasonCannotApply;
  final double estimatedDiscount;
  final String shopName;
  final bool isClaimed;
  final bool canClaim;
  final bool isFullyClaimed;
  final int? remainingClaims;
  final int? usageLimit;

  factory AvailableVoucher.fromJson(Map<String, dynamic> json) => AvailableVoucher(
    id: (json['id'] as num?)?.toInt() ?? 0,
    code: json['code'] as String? ?? '',
    name: json['name'] as String?,
    type: json['type'] as String? ?? 'fixed',
    isFreeShipping: json['is_free_shipping'] as bool? ?? false,
    offerLabel: json['offer_label'] as String? ?? '',
    description: json['description'] as String? ?? '',
    minSpend: json['min_spend'] != null ? parseMoney(json['min_spend']) : null,
    expiresAt: json['expires_at'] as String?,
    canApply: json['can_apply'] as bool? ?? true,
    reasonCannotApply: json['reason_cannot_apply'] as String?,
    estimatedDiscount: parseMoney(json['estimated_discount']),
    shopName: json['shop_name'] as String? ?? 'All shops',
    isClaimed: json['is_claimed'] as bool? ?? false,
    canClaim: json['can_claim'] as bool? ?? false,
    isFullyClaimed: json['is_fully_claimed'] as bool? ?? false,
    remainingClaims: (json['remaining_claims'] as num?)?.toInt(),
    usageLimit: (json['usage_limit'] as num?)?.toInt(),
  );
}

/// A seller the buyer pays by GCash: their own account and the items subtotal (shipping is added on top).
class GcashSeller {
  const GcashSeller({required this.sellerId, required this.shop, required this.subtotal, this.discount = 0, this.number, this.name});

  final int sellerId;
  final String shop;
  final double subtotal;

  /// The voucher discount this shop gives (it joined the voucher).
  final double discount;
  final String? number;
  final String? name;

  factory GcashSeller.fromJson(Map<String, dynamic> json) {
    final account = json['account'] as Map<String, dynamic>?;
    return GcashSeller(
      sellerId: (json['seller_id'] as num).toInt(),
      shop: json['shop'] as String? ?? 'Seller',
      subtotal: parseMoney(json['subtotal']),
      discount: parseMoney(json['discount']),
      number: account?['number'] as String?,
      name: account?['name'] as String?,
    );
  }
}
