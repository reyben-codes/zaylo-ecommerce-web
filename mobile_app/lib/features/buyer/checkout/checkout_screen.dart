import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/buyer_api.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/form_fields.dart';
import 'package:zaylo_mobile/components/layout.dart';
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

  /// One key per checkout attempt, reused on retries so a dropped connection
  /// after the server placed the order can never create a duplicate.
  final _idempotencyKey = BuyerApi.newIdempotencyKey();

  CheckoutQuote? _quote;
  Object? _error;
  String? _voucherError;
  String? _appliedVoucher;
  int? _addressId;
  String _payment = 'cod';
  bool _quoting = false;
  bool _placing = false;

  BuyerApi get _api => AppScope.read(context).api;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadQuote());
  }

  @override
  void dispose() {
    _voucher.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _loadQuote({String? voucher}) async {
    setState(() {
      _quoting = true;
      _voucherError = null;
    });
    try {
      final quote = await _api.quote(widget.cartItemIds, voucherCode: voucher ?? _appliedVoucher);
      if (!mounted) return;
      setState(() {
        _quote = quote;
        _error = null;
        _appliedVoucher = quote.voucherCode;
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
        idempotencyKey: _idempotencyKey,
        voucherCode: _appliedVoucher,
        notes: _notes.text,
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
      // Stock or the bag changed under us; show the fresh totals.
      if (error.fieldErrors.containsKey('cart') || error.fieldErrors.containsKey('cart_item_ids')) _loadQuote();
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
                      Text(peso(quote.totals.total),
                          style: TextStyle(fontSize: 19, fontWeight: FontWeight.w800, color: ZayloColors.ink)),
                    ]),
                  ),
                  SizedBox(
                    width: 180,
                    child: PrimaryButton(
                      label: 'PLACE ORDER',
                      busy: _placing,
                      onPressed: _quoting || _addressId == null ? null : _placeOrder,
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
                _section('Delivery address', _addressSection(quote)),
                const SizedBox(height: 18),
                _section('Items (${quote.items.fold<int>(0, (sum, item) => sum + item.quantity)})', _itemsSection(quote)),
                const SizedBox(height: 18),
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

  Widget _paymentSection() => Column(children: [
        _PaymentOption(
          icon: Icons.payments_outlined,
          title: 'Cash on Delivery',
          subtitle: 'Pay when your order arrives',
          selected: _payment == 'cod',
          onTap: () => setState(() => _payment = 'cod'),
        ),
        const SizedBox(height: 10),
        _PaymentOption(
          icon: Icons.phone_android_outlined,
          title: 'GCash',
          subtitle: 'Manual payment, pending confirmation',
          selected: _payment == 'gcash',
          onTap: () => setState(() => _payment = 'gcash'),
        ),
      ]);

  Widget _voucherSection() {
    if (_appliedVoucher != null) {
      return SectionCard(
        child: Row(children: [
          Icon(Icons.check_circle, color: ZayloColors.success),
          const SizedBox(width: 10),
          Expanded(
            child: Text('$_appliedVoucher applied: free shipping',
                style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
          ),
          TextButton(onPressed: _quoting ? null : _removeVoucher, child: const Text('Remove')),
        ]),
      );
    }
    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
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
    ]);
  }

  Widget _summarySection(CheckoutQuote quote) => SectionCard(
        child: Column(children: [
          SummaryRow('Subtotal', peso(quote.totals.subtotal)),
          SummaryRow('Shipping', peso(quote.totals.shipping)),
          if (quote.totals.shippingDiscount > 0)
            SummaryRow('Shipping voucher', '-${peso(quote.totals.shippingDiscount)}',
                valueColor: ZayloColors.success),
          Divider(color: ZayloColors.line, height: 20),
          SummaryRow('Total', peso(quote.totals.total), emphasize: true),
          if (_quoting) Padding(padding: EdgeInsets.only(top: 8), child: LinearProgressIndicator(color: ZayloColors.ink)),
        ]),
      );
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
  final VoidCallback onTap;

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
