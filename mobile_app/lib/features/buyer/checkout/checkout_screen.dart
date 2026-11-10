import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/buyer_api.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/form_fields.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/features/buyer/account/identity_screen.dart';
import 'package:zaylo_mobile/features/buyer/address/address_form_screen.dart';
import 'package:zaylo_mobile/features/buyer/address/address_tag.dart';
import 'package:zaylo_mobile/features/buyer/checkout/order_success_screen.dart';
import 'package:zaylo_mobile/models/buyer/address_models.dart';
import 'package:zaylo_mobile/models/buyer/cart_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key, required this.cartItemIds});

  final List<int> cartItemIds;

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final _voucher = TextEditingController();
  final _notes = TextEditingController();

  /// GCash reference numbers typed at checkout, one per seller (each seller has their own GCash and order).
  final Map<int, TextEditingController> _gcashRefs = {};

  TextEditingController _gcashRef(int sellerId) => _gcashRefs.putIfAbsent(sellerId, TextEditingController.new);

  /// One key per checkout attempt, reused on retries so a dropped connection
  /// after the server placed the order can never create a duplicate.
  final _idempotencyKey = BuyerApi.newIdempotencyKey();

  CheckoutQuote? _quote;
  Object? _error;
  String? _voucherError;
  String? _appliedVoucher;
  int? _addressId;
  String _payment = 'cod';
  String? _courier;

  /// The partner the buyer tapped; until then, the nearest one to the chosen address.
  int? _pickedPartnerId;
  bool _quoting = false;
  bool _placing = false;

  BuyerApi get _api => AppScope.read(context).api;

  /// The partner delivering the order: the buyer's pick, or the one with the lowest fee to the chosen address.
  int? _partnerId(CheckoutQuote quote) {
    final partners = quote.logisticsPartners;
    if (partners.any((partner) => partner.id == _pickedPartnerId)) return _pickedPartnerId;
    if (partners.isEmpty) return null;
    final byFee = [...partners]
      ..sort((a, b) => (a.rates[_addressId]?.fee ?? double.infinity).compareTo(b.rates[_addressId]?.fee ?? double.infinity));
    return byFee.first.id;
  }

  CheckoutTotals _totals(CheckoutQuote quote) => quote.totalsFor(_partnerId(quote), _addressId);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadQuote());
  }

  @override
  void dispose() {
    _voucher.dispose();
    _notes.dispose();
    for (final controller in _gcashRefs.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _loadQuote({String? voucher}) async {
    setState(() {
      _quoting = true;
      _voucherError = null;
    });
    try {
      final quote = await _api.quote(widget.cartItemIds, voucherCode: voucher ?? _appliedVoucher, paymentMethod: _payment);
      if (!mounted) return;
      setState(() {
        _quote = quote;
        _error = null;
        _appliedVoucher = quote.voucherCode;
        if (quote.couriers.isNotEmpty && !quote.couriers.any((courier) => courier.key == _courier)) {
          _courier = quote.couriers.first.key;
        }
        final addresses = quote.addresses.where((address) => address.isStructured).toList();
        if (_addressId == null || !addresses.any((address) => address.id == _addressId)) {
          _addressId = addresses.isEmpty
              ? null
              : (addresses.where((address) => address.isDefault).firstOrNull ?? addresses.first).id;
        }
      });
    } on ApiException catch (error) {
      if (!mounted) return;
      if (error.fieldErrors.containsKey('voucher_code')) {
        setState(() => _voucherError = error.fieldErrors['voucher_code']);
      } else {
        setState(() => _error = error);
      }
    } finally {
      if (mounted) setState(() => _quoting = false);
    }
  }

  void _applyVoucher() {
    FocusScope.of(context).unfocus();
    final code = _voucher.text.trim();
    if (code.isEmpty) return;
    _loadQuote(voucher: code);
  }

  void _applySpecificVoucher(String code) {
    FocusScope.of(context).unfocus();
    _voucher.text = code;
    _loadQuote(voucher: code);
  }

  void _removeVoucher() {
    _voucher.clear();
    _appliedVoucher = null;
    _loadQuote(voucher: '');
  }

  Future<void> _addAddress() async {
    final saved = await Navigator.of(context).push<Address>(
      MaterialPageRoute(builder: (_) => const AddressFormScreen()),
    );
    if (saved != null && mounted) {
      _addressId = saved.id;
      await _loadQuote();
    }
  }

  Future<void> _editAddress(Address address) async {
    final saved = await Navigator.of(context).push<Address>(
      MaterialPageRoute(builder: (_) => AddressFormScreen(address: address)),
    );
    if (saved != null && mounted) {
      _addressId = saved.id;
      await _loadQuote();
    }
  }

  /// Orders need an approved ID; the upload happens here instead of failing at Place order.
  Future<void> _verifyIdentity() async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const IdentityScreen()));
    if (mounted) await _loadQuote();
  }

  Future<void> _placeOrder() async {
    final addressId = _addressId;
    if (addressId == null) {
      showMessage(context, 'Add a delivery address to continue.');
      return;
    }
    setState(() => _placing = true);
    final state = AppScope.read(context);
    try {
      final orders = await _api.placeOrder(
        cartItemIds: widget.cartItemIds,
        addressId: addressId,
        paymentMethod: _payment,
        courierChoice: _courier,
        logisticsProviderId: _quote == null ? null : _partnerId(_quote!),
        idempotencyKey: _idempotencyKey,
        voucherCode: _appliedVoucher,
        notes: _notes.text,
        gcashReferences: _payment != 'gcash'
            ? const {}
            : {
                for (final entry in _gcashRefs.entries)
                  if (entry.value.text.trim().isNotEmpty) entry.key: entry.value.text.replaceAll(' ', ''),
              },
      );
      await state.refreshCart().catchError((_) {});
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => OrderSuccessScreen(orders: orders, paymentMethod: _payment)),
        (route) => route.isFirst,
      );
    } on ApiException catch (error) {
      if (!mounted) return;
      showMessage(context, error.message);
      // Stock, the bag, or the ID status changed under us; show the fresh state.
      if (const ['cart', 'cart_item_ids', 'identity'].any(error.fieldErrors.containsKey)) _loadQuote();
    } finally {
      if (mounted) setState(() => _placing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final quote = _quote;
    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      bottomNavigationBar: quote == null
          ? null
          : Container(
              decoration: BoxDecoration(
                color: ZayloColors.paper,
                border: Border(top: BorderSide(color: ZayloColors.line)),
              ),
              padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 12, ZayloSpace.page, 12),
              child: SafeArea(
                top: false,
                child: Row(children: [
                  Expanded(
                    child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('Total', style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
                      Text(peso(_totals(quote).total),
                          style: TextStyle(fontSize: 19, fontWeight: FontWeight.w800, color: ZayloColors.ink)),
                    ]),
                  ),
                  SizedBox(
                    width: 180,
                    child: PrimaryButton(
                      label: 'PLACE ORDER',
                      busy: _placing,
                      onPressed: _quoting || _addressId == null || !quote.identityVerified ? null : _placeOrder,
                    ),
                  ),
                ]),
              ),
            ),
      body: quote == null
          ? (_error != null
              ? LoadError(error: _error, onRetry: _loadQuote, title: 'Checkout unavailable')
              : const Loader())
          : ListView(
              padding: const EdgeInsets.all(ZayloSpace.page),
              children: [
                if (!quote.identityVerified) ...[
                  _IdentityRequired(products: quote.identityRequiredFor, onVerify: _verifyIdentity),
                  const SizedBox(height: 18),
                ],
                _section('Delivery address', _addressSection(quote)),
                const SizedBox(height: 18),
                _section('Items (${quote.items.fold<int>(0, (sum, item) => sum + item.quantity)})', _itemsSection(quote)),
                const SizedBox(height: 18),
                if (quote.logisticsPartners.isNotEmpty) ...[
                  _section('Logistics partner', _partnerSection(quote)),
                  const SizedBox(height: 18),
                ],
                if (quote.couriers.isNotEmpty) ...[
                  _section('Delivery courier truck', _courierSection(quote)),
                  const SizedBox(height: 18),
                ],
                _section('Payment method', _paymentSection()),
                const SizedBox(height: 18),
                _section('Voucher', _voucherSection()),
                const SizedBox(height: 18),
                _section(
                  'Note to seller',
                  TextField(
                    controller: _notes,
                    maxLength: 500,
                    maxLines: 3,
                    minLines: 2,
                    decoration: zayloInputDecoration('Optional: delivery instructions or requests'),
                  ),
                ),
                const SizedBox(height: 18),
                _section('Order summary', _summarySection(quote)),
                const SizedBox(height: 12),
              ],
            ),
    );
  }

  Widget _section(String title, Widget child) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Eyebrow(title),
        const SizedBox(height: 9),
        child,
      ]);

  Widget _addressSection(CheckoutQuote quote) {
    if (quote.addresses.isEmpty) {
      return SectionCard(
        child: Column(children: [
          Text('Where should we deliver?',
              style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
          const SizedBox(height: 4),
          const Text('Add an address to place your order.', style: TextStyle(fontSize: 13)),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: _addAddress,
            style: secondaryButtonStyle,
            icon: const Icon(Icons.add_location_alt_outlined),
            label: const Text('ADD ADDRESS'),
          ),
        ]),
      );
    }
    return Column(children: [
      for (final address in quote.addresses)
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: _AddressOption(
            address: address,
            selected: address.id == _addressId,
            onSelect: address.isStructured ? () => setState(() => _addressId = address.id) : null,
            onEdit: () => _editAddress(address),
          ),
        ),
      Align(
        alignment: Alignment.centerLeft,
        child: TextButton.icon(
          onPressed: _addAddress,
          icon: const Icon(Icons.add, size: 18),
          label: const Text('Add another address'),
        ),
      ),
    ]);
  }

  Widget _itemsSection(CheckoutQuote quote) {
    final bySeller = <String, List<CartItem>>{};
    for (final item in quote.items) {
      bySeller.putIfAbsent(item.sellerName ?? 'ZAYLO seller', () => []).add(item);
    }
    return SectionCard(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 6),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        for (final entry in bySeller.entries) ...[
          Row(children: [
            Icon(Icons.storefront_outlined, size: 16, color: ZayloColors.muted),
            const SizedBox(width: 6),
            Expanded(
              child: Text(entry.key,
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: ZayloColors.ink)),
            ),
          ]),
          const SizedBox(height: 8),
          for (final item in entry.value)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Row(children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: SizedBox(width: 56, height: 56, child: NetworkImageBox(url: item.product?.imageUrl)),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(item.product?.name ?? 'Product',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(fontWeight: FontWeight.w600, color: ZayloColors.ink, fontSize: 13)),
                    if (item.variantName != null)
                      Text(item.variantName!, style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
                    Text('${item.quantity} × ${peso(item.unitPrice)}',
                        style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
                  ]),
                ),
                Text(peso(item.lineTotal), style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
              ]),
            ),
        ],
        if (quote.deliveryFrom != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Row(children: [
              Icon(Icons.local_shipping_outlined, size: 16, color: ZayloColors.muted),
              const SizedBox(width: 6),
              Expanded(
                child: Text('Estimated delivery ${shortDate(quote.deliveryFrom)} – ${shortDate(quote.deliveryTo)}',
                    style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
              ),
            ]),
          ),
      ]),
    );
  }

  Widget _partnerSection(CheckoutQuote quote) {
    final chosen = _partnerId(quote);
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text("The shipping fee depends on how far your address is from the partner's hub.",
          style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
      const SizedBox(height: 10),
      for (final (index, partner) in quote.logisticsPartners.indexed) ...[
        if (index > 0) const SizedBox(height: 10),
        _PaymentOption(
          icon: Icons.warehouse_outlined,
          title: partner.name,
          subtitle: [
            if (partner.hub.isNotEmpty) 'Hub: ${partner.hub}',
            switch (partner.rates[_addressId]) {
              final rate? => '${peso(rate.fee)} per seller · ${rate.label}',
              null => 'Choose an address to see the fee',
            },
          ].join(' · '),
          selected: chosen == partner.id,
          onTap: () => setState(() => _pickedPartnerId = partner.id),
        ),
      ],
    ]);
  }

  Widget _courierSection(CheckoutQuote quote) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('Choose which courier truck picks up from the seller and delivers to your home with parcel scanning.',
            style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
        const SizedBox(height: 10),
        for (final (index, courier) in quote.couriers.indexed) ...[
          if (index > 0) const SizedBox(height: 10),
          _PaymentOption(
            icon: switch (courier.key) {
              'flash_express' => Icons.bolt_outlined,
              'jnt_express' => Icons.local_shipping_outlined,
              _ => Icons.local_shipping,
            },
            title: courier.name,
            subtitle: courier.description,
            selected: _courier == courier.key,
            onTap: () => setState(() => _courier = courier.key),
          ),
        ],
      ]);

  Widget _paymentSection() {
    final quote = _quote;
    final sellers = quote?.gcashSellers ?? const <GcashSeller>[];
    // Older servers send no GCash accounts: keep the plain option.
    final gcashKnown = sellers.isNotEmpty;
    final gcashOk = !gcashKnown || quote!.gcashAvailable;
    final missing = sellers.where((seller) => seller.number == null).map((seller) => seller.shop).join(', ');
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      _PaymentOption(
        icon: Icons.payments_outlined,
        title: 'Cash on Delivery',
        subtitle: 'Pay when your order arrives',
        selected: _payment == 'cod',
        onTap: () {
          if (_payment == 'cod') return;
          setState(() => _payment = 'cod');
          _loadQuote();
        },
      ),
      const SizedBox(height: 10),
      Opacity(
        opacity: gcashOk ? 1 : .55,
        child: _PaymentOption(
          icon: Icons.phone_android_outlined,
          title: 'GCash',
          subtitle: gcashOk ? "Send to each seller's GCash, then enter the reference number" : '$missing has not set up GCash yet',
          selected: _payment == 'gcash',
          onTap: gcashOk
              ? () {
                  if (_payment == 'gcash') return;
                  setState(() => _payment = 'gcash');
                  _loadQuote();
                }
              : null,
        ),
      ),
      if (_payment == 'gcash' && gcashKnown && gcashOk) ...[
        const SizedBox(height: 12),
        _gcashSellers(quote!, sellers),
      ],
    ]);
  }

  /// Each seller's GCash, what to send them (their items plus their shipping), and a reference field.
  Widget _gcashSellers(CheckoutQuote quote, List<GcashSeller> sellers) {
    final totals = _totals(quote);
    final perSeller = quote.sellerCount == 0 ? 0.0 : (totals.shipping - totals.shippingDiscount) / quote.sellerCount;
    return SectionCard(
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text(
          "Send each seller's amount to their GCash, then type the 13-digit reference number from your GCash receipt. "
          'You can also add it later from My orders. Unpaid GCash orders are cancelled after 24 hours.',
          style: TextStyle(fontSize: 12.5),
        ),
        for (final seller in sellers) ...[
          Divider(color: ZayloColors.line, height: 24),
          Text(seller.shop, style: TextStyle(fontWeight: FontWeight.w800, color: ZayloColors.ink)),
          const SizedBox(height: 2),
          Text('GCash ${seller.number} · ${seller.name ?? seller.shop}', style: const TextStyle(fontSize: 13)),
          Text('Send ${peso(seller.subtotal - seller.discount + perSeller)} (items + shipping${seller.discount > 0 ? ' − voucher' : ''})',
              style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: ZayloColors.ink)),
          const SizedBox(height: 8),
          TextField(
            controller: _gcashRef(seller.sellerId),
            keyboardType: TextInputType.number,
            maxLength: 13,
            inputFormatters: [
              FilteringTextInputFormatter.digitsOnly,
              LengthLimitingTextInputFormatter(13),
            ],
            decoration: const InputDecoration(
              labelText: 'GCash reference no. (optional now)',
              hintText: '13-digit reference no.',
              counterText: '',
            ),
          ),
        ],
      ]),
    );
  }

  Widget _voucherSection() {
    final available = _quote?.availableVouchers ?? const [];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (_appliedVoucher != null) ...[
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: ZayloColors.success.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: ZayloColors.success.withValues(alpha: 0.3)),
            ),
            child: Row(
              children: [
                Icon(Icons.check_circle, color: ZayloColors.success, size: 20),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '$_appliedVoucher applied',
                        style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink, fontSize: 13),
                      ),
                      Text(
                        _quote?.voucherLabel ?? 'Discount voucher applied',
                        style: TextStyle(color: ZayloColors.muted, fontSize: 12),
                      ),
                    ],
                  ),
                ),
                TextButton(
                  onPressed: _quoting ? null : _removeVoucher,
                  style: TextButton.styleFrom(
                    foregroundColor: Colors.red.shade700,
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                  child: const Text('Remove', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 12)),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
        ],
        if (available.isNotEmpty) ...[
          Text(
            'Available vouchers (${available.length})',
            style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink, fontSize: 13),
          ),
          const SizedBox(height: 8),
          ...available.map((v) {
            final isApplied = _appliedVoucher?.toUpperCase() == v.code.toUpperCase();
            return Container(
              margin: const EdgeInsets.only(bottom: 8),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: isApplied
                    ? ZayloColors.success.withValues(alpha: 0.08)
                    : (v.canApply ? ZayloColors.paper : ZayloColors.sand.withValues(alpha: 0.3)),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(
                  color: isApplied
                      ? ZayloColors.success
                      : (v.canApply ? ZayloColors.cardBorder : ZayloColors.line.withValues(alpha: 0.5)),
                ),
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                    decoration: BoxDecoration(
                      color: isApplied ? ZayloColors.success : (v.canApply ? ZayloColors.taupe : ZayloColors.muted),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          v.isFreeShipping ? Icons.local_shipping : Icons.discount,
                          color: Colors.white,
                          size: 16,
                        ),
                        const SizedBox(height: 2),
                        Text(
                          v.offerLabel,
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 10),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          v.name != null && v.name!.isNotEmpty ? '${v.code} · ${v.name}' : v.code,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(fontWeight: FontWeight.w800, color: ZayloColors.ink, fontSize: 13),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          v.description,
                          style: TextStyle(color: ZayloColors.muted, fontSize: 11),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          v.shopName + (v.expiresAt != null ? ' · Ends ${v.expiresAt}' : ''),
                          style: TextStyle(color: ZayloColors.onInkFaint, fontSize: 10),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  if (isApplied)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                      decoration: BoxDecoration(
                        color: ZayloColors.success.withValues(alpha: 0.15),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.check, size: 14, color: ZayloColors.success),
                          const SizedBox(width: 3),
                          Text(
                            'Applied',
                            style: TextStyle(
                              color: ZayloColors.success,
                              fontWeight: FontWeight.w700,
                              fontSize: 11,
                            ),
                          ),
                        ],
                      ),
                    )
                  else if (v.canApply)
                    OutlinedButton(
                      onPressed: _quoting ? null : () => _applySpecificVoucher(v.code),
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        minimumSize: Size.zero,
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        side: BorderSide(color: ZayloColors.ink),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
                      ),
                      child: Text(
                        'Apply',
                        style: TextStyle(color: ZayloColors.ink, fontWeight: FontWeight.w700, fontSize: 11),
                      ),
                    )
                  else
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: ZayloColors.sand.withValues(alpha: 0.5),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        v.reasonCannotApply ?? 'Unavailable',
                        style: TextStyle(color: ZayloColors.muted, fontSize: 10),
                      ),
                    ),
                ],
              ),
            );
          }),
          const SizedBox(height: 8),
        ],
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(
            child: TextField(
              controller: _voucher,
              textCapitalization: TextCapitalization.characters,
              textInputAction: TextInputAction.done,
              onSubmitted: (_) => _applyVoucher(),
              decoration: zayloInputDecoration('Enter voucher code', errorText: _voucherError),
            ),
          ),
          const SizedBox(width: 10),
          SizedBox(
            height: 50,
            child: OutlinedButton(
              onPressed: _quoting ? null : _applyVoucher,
              style: secondaryButtonStyle.copyWith(
                padding: const WidgetStatePropertyAll(EdgeInsets.symmetric(horizontal: 18)),
              ),
              child: const Text('APPLY'),
            ),
          ),
        ]),
      ],
    );
  }

  Widget _summarySection(CheckoutQuote quote) {
    final totals = _totals(quote);
    return SectionCard(
        child: Column(children: [
          SummaryRow('Subtotal', peso(totals.subtotal)),
          SummaryRow(quote.sellerCount > 1 ? 'Shipping Fee (₱120 × ${quote.sellerCount} sellers)' : 'Shipping Fee', peso(totals.shipping)),
          if (totals.shippingDiscount > 0)
            SummaryRow('Shipping voucher', '-${peso(totals.shippingDiscount)}',
                valueColor: ZayloColors.success),
          if (totals.voucherDiscount > 0)
            SummaryRow('Voucher ${quote.voucherCode ?? ''}'.trim(), '-${peso(totals.voucherDiscount)}', valueColor: ZayloColors.success),
          Divider(color: ZayloColors.line, height: 20),
          SummaryRow(_payment == 'cod' ? 'Total COD Amount' : 'Total', peso(totals.total), emphasize: true),
          if (_quoting) Padding(padding: EdgeInsets.only(top: 8), child: LinearProgressIndicator(color: ZayloColors.ink)),
        ]),
      );
  }
}

class _AddressOption extends StatelessWidget {
  const _AddressOption({required this.address, required this.selected, required this.onSelect, required this.onEdit});

  final Address address;
  final bool selected;
  final VoidCallback? onSelect;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) => Material(
        color: ZayloColors.paper,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: BorderSide(color: selected ? ZayloColors.ink : ZayloColors.line, width: selected ? 1.6 : 1),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: onSelect ?? onEdit,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(6, 10, 4, 10),
            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(8, 10, 10, 0),
                child: Icon(
                  selected ? Icons.radio_button_checked : Icons.radio_button_off,
                  color: onSelect == null ? ZayloColors.line : ZayloColors.ink,
                  semanticLabel: selected ? 'Selected address' : 'Not selected',
                ),
              ),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const SizedBox(height: 10),
                  Wrap(spacing: 8, crossAxisAlignment: WrapCrossAlignment.center, children: [
                    Text(address.recipientName,
                        style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
                    AddressTag(address.label),
                    if (address.isDefault) const AddressTag('Default', dark: true),
                  ]),
                  const SizedBox(height: 3),
                  Text(address.phone, style: const TextStyle(fontSize: 12)),
                  Text(address.formatted, style: const TextStyle(fontSize: 12)),
                  if (!address.isStructured)
                    const Padding(
                      padding: EdgeInsets.only(top: 6),
                      child: Text('Update this address with its Philippine locations to use it.',
                          style: TextStyle(fontSize: 12, color: Colors.redAccent, fontWeight: FontWeight.w600)),
                    ),
                ]),
              ),
              IconButton(tooltip: 'Edit address', onPressed: onEdit, icon: const Icon(Icons.edit_outlined, size: 19)),
            ]),
          ),
        ),
      );
}

class _PaymentOption extends StatelessWidget {
  const _PaymentOption({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final bool selected;

  /// Null when the option cannot be chosen.
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Material(
        color: ZayloColors.paper,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: BorderSide(color: selected ? ZayloColors.ink : ZayloColors.line, width: selected ? 1.6 : 1),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(children: [
              CircleAvatar(
                radius: 19,
                backgroundColor: selected ? ZayloColors.ink : ZayloColors.sand,
                child: Icon(icon, size: 19, color: selected ? ZayloColors.white : ZayloColors.ink),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(title, style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
                  Text(subtitle, style: const TextStyle(fontSize: 12)),
                ]),
              ),
              Icon(selected ? Icons.radio_button_checked : Icons.radio_button_off,
                  color: selected ? ZayloColors.ink : ZayloColors.line),
            ]),
          ),
        ),
      );
}

/// Shown when the selected items include 18+ products and the buyer's ID is not approved yet.
class _IdentityRequired extends StatelessWidget {
  const _IdentityRequired({required this.products, required this.onVerify});

  final List<String> products;
  final VoidCallback onVerify;

  @override
  Widget build(BuildContext context) => SectionCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Icon(Icons.badge_outlined, color: ZayloColors.warning),
            const SizedBox(width: 10),
            Expanded(
              child: Text('18+ items need an approved ID',
                  style: TextStyle(fontWeight: FontWeight.w800, color: ZayloColors.ink)),
            ),
          ]),
          const SizedBox(height: 6),
          Text(
              '${products.isEmpty ? 'Some items are' : '${products.join(', ')} ${products.length == 1 ? 'is' : 'are'}'} for adults only. '
              'Upload a government ID once to order 18+ items, or go back to your cart and leave them out of this order.',
              style: const TextStyle(fontSize: 13)),
          const SizedBox(height: 12),
          OutlinedButton(onPressed: onVerify, style: secondaryButtonStyle, child: const Text('VERIFY MY ID')),
        ]),
      );
}
