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
  });

  final double subtotal;
  final double shipping;
  final double shippingDiscount;
  final double total;

  factory CheckoutTotals.fromJson(Map<String, dynamic> json) => CheckoutTotals(
        subtotal: parseMoney(json['subtotal']),
        shipping: parseMoney(json['shipping']),
        shippingDiscount: parseMoney(json['shipping_discount']),
        total: parseMoney(json['total']),
      );
}

class CheckoutQuote {
  const CheckoutQuote({
    required this.items,
    required this.totals,
    required this.addresses,
    this.voucherCode,
    this.deliveryFrom,
    this.deliveryTo,
    this.identityVerified = true,
  });

  final List<CartItem> items;
  final CheckoutTotals totals;
  final List<Address> addresses;
  final String? voucherCode;
  final DateTime? deliveryFrom;
  final DateTime? deliveryTo;

  /// False until an administrator approves the buyer's ID; orders are refused until then.
  final bool identityVerified;

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
      deliveryFrom: parseDate(delivery?['from']),
      deliveryTo: parseDate(delivery?['to']),
      identityVerified: json['identity_verified'] as bool? ?? true,
    );
  }
}
