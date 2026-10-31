import 'package:zaylo_mobile/models/json_parsing.dart';

class OrderItem {
  const OrderItem({
    required this.id,
    required this.productName,
    required this.unitPrice,
    required this.quantity,
    required this.lineTotal,
    this.productSlug,
    this.imageUrl,
    this.canReview = false,
    this.reviewRating,
  });

  final int id;
  final String productName;
  final double unitPrice;
  final int quantity;
  final double lineTotal;
  final String? productSlug;
  final String? imageUrl;

  /// Detail-only: delivered and not rated yet, or the stars the buyer gave.
  final bool canReview;
  final int? reviewRating;

  factory OrderItem.fromJson(Map<String, dynamic> json) => OrderItem(
        id: (json['id'] as num).toInt(),
        productName: json['product_name'] as String,
        unitPrice: parseMoney(json['unit_price']),
        quantity: (json['quantity'] as num).toInt(),
        lineTotal: parseMoney(json['line_total']),
        productSlug: json['product_slug'] as String?,
        imageUrl: json['image_url'] as String?,
        canReview: json['can_review'] as bool? ?? false,
        reviewRating: (json['review_rating'] as num?)?.toInt(),
      );
}

class OrderHistoryEntry {
  const OrderHistoryEntry({required this.status, required this.label, this.note, this.at});

  final String status;
  final String label;
  final String? note;
  final DateTime? at;

  factory OrderHistoryEntry.fromJson(Map<String, dynamic> json) => OrderHistoryEntry(
        status: json['status'] as String,
        label: json['label'] as String,
        note: json['note'] as String?,
        at: parseDate(json['at']),
      );
}

class Order {
  const Order({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.statusLabel,
    required this.statusMessage,
    required this.canCancel,
    this.canConfirmReceipt = false,
    required this.subtotal,
    required this.shippingFee,
    required this.shippingDiscount,
    required this.total,
    required this.itemCount,
    required this.items,
    this.paymentMethod,
    this.voucherCode,
    this.placedAt,
    this.recipientName,
    this.phone,
    this.shippingAddress,
    this.notes,
    this.paymentStatus,
    this.trackingNumber,
    this.autoConfirmAt,
    this.deliveryPhotoUrl,
    this.history = const [],
    this.riderChat,
    this.complaint,
    this.canComplain = false,
    this.canReturn = false,
    this.returnDeadline,
  });

  final int id;
  final String orderNumber;
  final String status;
  final String statusLabel;
  final String statusMessage;
  final bool canCancel;

  /// The rider delivered the order; the buyer finishes it by confirming they
  /// received it, or it completes on its own at [autoConfirmAt].
  final bool canConfirmReceipt;
  final double subtotal;
  final double shippingFee;
  final double shippingDiscount;
  final double total;
  final int itemCount;
  final List<OrderItem> items;
  final String? paymentMethod;
  final String? voucherCode;
  final DateTime? placedAt;

  /// Detail-only fields.
  final String? recipientName;
  final String? phone;
  final String? shippingAddress;
  final String? notes;
  final String? paymentStatus;
  final String? trackingNumber;
  final DateTime? autoConfirmAt;

  /// The rider's photo of the delivered parcel; loading it needs the session's auth headers.
  final String? deliveryPhotoUrl;
  final List<OrderHistoryEntry> history;

  /// Present once a rider has accepted the delivery.
  final RiderChatSummary? riderChat;

  /// The complaint filed about this order, or whether one can be filed.
  final OrderComplaint? complaint;
  final bool canComplain;

  /// Whether the 24-hour return window for damaged or wrong items is active.
  final bool canReturn;
  final DateTime? returnDeadline;

  String get paymentLabel => switch (paymentMethod) {
        'gcash' => 'GCash',
        'cod' => 'Cash on delivery',
        final other? => other.toUpperCase(),
        null => '—',
      };

  factory Order.fromJson(Map<String, dynamic> json) => Order(
        id: (json['id'] as num).toInt(),
        orderNumber: json['order_number'] as String,
        status: json['status'] as String,
        statusLabel: json['status_label'] as String,
        statusMessage: json['status_message'] as String,
        canCancel: json['can_cancel'] as bool? ?? false,
        canConfirmReceipt: json['can_confirm_receipt'] as bool? ?? false,
        subtotal: parseMoney(json['subtotal']),
        shippingFee: parseMoney(json['shipping_fee']),
        shippingDiscount: parseMoney(json['shipping_discount']),
        total: parseMoney(json['total']),
        itemCount: (json['item_count'] as num?)?.toInt() ?? 0,
        items: (json['items'] as List<dynamic>? ?? const [])
            .map((item) => OrderItem.fromJson(item as Map<String, dynamic>))
            .toList(),
        paymentMethod: json['payment_method'] as String?,
        voucherCode: json['voucher_code'] as String?,
        placedAt: parseDate(json['placed_at']),
        recipientName: json['recipient_name'] as String?,
        phone: json['phone'] as String?,
        shippingAddress: json['shipping_address'] as String?,
        notes: json['notes'] as String?,
        paymentStatus: json['payment_status'] as String?,
        trackingNumber: json['tracking_number'] as String?,
        autoConfirmAt: parseDate(json['auto_confirm_at']),
        deliveryPhotoUrl: json['delivery_photo_url'] as String?,
        history: (json['history'] as List<dynamic>? ?? const [])
            .map((item) => OrderHistoryEntry.fromJson(item as Map<String, dynamic>))
            .toList(),
        riderChat: json['rider_chat'] is Map<String, dynamic>
            ? RiderChatSummary.fromJson(json['rider_chat'] as Map<String, dynamic>)
            : null,
        complaint: json['complaint'] is Map<String, dynamic>
            ? OrderComplaint.fromJson(json['complaint'] as Map<String, dynamic>)
            : null,
        canComplain: json['can_complain'] as bool? ?? false,
        canReturn: json['can_return'] as bool? ?? false,
        returnDeadline: parseDate(json['return_deadline']),
      );
}

class OrderComplaint {
  const OrderComplaint({required this.id, required this.status, required this.statusLabel});

  final int id;
  final String status;
  final String statusLabel;

  factory OrderComplaint.fromJson(Map<String, dynamic> json) => OrderComplaint(
        id: (json['id'] as num).toInt(),
        status: json['status'] as String? ?? 'open',
        statusLabel: json['status_label'] as String? ?? 'Open',
      );
}

/// The buyer's chat with the rider delivering an order.
class RiderChatSummary {
  const RiderChatSummary({required this.riderName, required this.open, this.unread = 0});

  final String riderName;

  /// False once the order is delivered or cancelled; the thread stays readable.
  final bool open;
  final int unread;

  factory RiderChatSummary.fromJson(Map<String, dynamic> json) => RiderChatSummary(
        riderName: json['rider_name'] as String? ?? 'Your rider',
        open: json['open'] as bool? ?? false,
        unread: json['unread'] as int? ?? 0,
      );
}

class OrderPage {
  const OrderPage({
    required this.orders,
    required this.counts,
    required this.currentPage,
    required this.lastPage,
  });

  final List<Order> orders;
  final Map<String, int> counts;
  final int currentPage;
  final int lastPage;

  bool get hasMore => currentPage < lastPage;

  factory OrderPage.fromJson(Map<String, dynamic> json) {
    final page = json['orders'] as Map<String, dynamic>;
    return OrderPage(
      orders: (page['data'] as List<dynamic>).map((item) => Order.fromJson(item as Map<String, dynamic>)).toList(),
      counts: (json['counts'] as Map<String, dynamic>).map((key, value) => MapEntry(key, (value as num).toInt())),
      currentPage: (page['current_page'] as num).toInt(),
      lastPage: (page['last_page'] as num).toInt(),
    );
  }
}
