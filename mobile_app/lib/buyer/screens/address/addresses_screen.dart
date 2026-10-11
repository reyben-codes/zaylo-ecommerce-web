import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/zaylo_theme.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/layout.dart';
import '../../api/buyer_api.dart';
import '../../models/address_models.dart';
import '../../state/app_state.dart';
import 'address_form_screen.dart';
import 'address_tag.dart';

class AddressesScreen extends StatefulWidget {
  const AddressesScreen({super.key});

  @override
  State<AddressesScreen> createState() => _AddressesScreenState();
}

class _AddressesScreenState extends State<AddressesScreen> {
  List<Address>? _addresses;
  Object? _error;
  int? _busyId;

  BuyerApi get _api => AppScope.read(context).api;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    try {
      final addresses = await _api.addresses();
      if (mounted) {
        setState(() {
          _addresses = addresses;
          _error = null;
        });
      }
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error);
    }
  }

  Future<void> _open([Address? address]) async {
    final saved = await Navigator.of(context).push<Address>(
      MaterialPageRoute(builder: (_) => AddressFormScreen(address: address)),
    );
    if (saved != null) _load();
  }

  Future<void> _run(Address address, Future<List<Address>> Function() action, String done) async {
    setState(() => _busyId = address.id);
    try {
      final addresses = await action();
      if (!mounted) return;
      setState(() => _addresses = addresses);
      showMessage(context, done);
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  Future<void> _delete(Address address) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete this address?'),
        content: Text('${address.label} · ${address.formatted}\n\nOrders already placed are not affected.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            style: TextButton.styleFrom(foregroundColor: Colors.redAccent),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirmed == true && mounted) {
      await _run(address, () => _api.deleteAddress(address.id), 'Address deleted.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final addresses = _addresses;
    return Scaffold(
      appBar: AppBar(title: const Text('Saved addresses')),
      floatingActionButton: addresses == null || addresses.isEmpty
          ? null
          : FloatingActionButton.extended(
              onPressed: _open,
              backgroundColor: ZayloColors.ink,
              foregroundColor: ZayloColors.white,
              icon: const Icon(Icons.add),
              label: const Text('Add address'),
            ),
      body: addresses == null
          ? (_error != null ? LoadError(error: _error, onRetry: _load) : const Loader())
          : addresses.isEmpty
              ? EmptyState(
                  icon: Icons.location_on_outlined,
                  title: 'No saved addresses',
                  message: 'Save your home, work or school address for faster checkout.',
                  actionLabel: 'Add address',
                  onAction: _open,
                )
              : RefreshIndicator(
                  color: ZayloColors.ink,
                  onRefresh: _load,
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(ZayloSpace.page, ZayloSpace.page, ZayloSpace.page, 96),
                    itemCount: addresses.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (context, index) => _AddressCard(
                      address: addresses[index],
                      busy: _busyId == addresses[index].id,
                      onEdit: () => _open(addresses[index]),
                      onDelete: () => _delete(addresses[index]),
                      onMakeDefault: () => _run(addresses[index], () => _api.makeDefaultAddress(addresses[index].id),
                          'Default address updated.'),
                    ),
                  ),
                ),
    );
  }
}

class _AddressCard extends StatelessWidget {
  const _AddressCard({
    required this.address,
    required this.busy,
    required this.onEdit,
    required this.onDelete,
    required this.onMakeDefault,
  });

  final Address address;
  final bool busy;
  final VoidCallback onEdit;
  final VoidCallback onDelete;
  final VoidCallback onMakeDefault;

  @override
  Widget build(BuildContext context) => SectionCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Wrap(spacing: 8, crossAxisAlignment: WrapCrossAlignment.center, children: [
            Text(address.recipientName, style: const TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
            AddressTag(address.label),
            if (address.isDefault) const AddressTag('Default', dark: true),
          ]),
          const SizedBox(height: 4),
          Text(address.phone, style: const TextStyle(fontSize: 13)),
          Text(address.formatted, style: const TextStyle(fontSize: 13)),
          if (!address.isStructured)
            const Padding(
              padding: EdgeInsets.only(top: 6),
              child: Text('Edit this address and choose its locations before using it at checkout.',
                  style: TextStyle(fontSize: 12, color: Colors.redAccent, fontWeight: FontWeight.w600)),
            ),
          const SizedBox(height: 8),
          if (busy)
            const LinearProgressIndicator(color: ZayloColors.ink)
          else
            Row(children: [
              TextButton.icon(
                onPressed: onEdit,
                icon: const Icon(Icons.edit_outlined, size: 17),
                label: const Text('Edit'),
                style: TextButton.styleFrom(foregroundColor: ZayloColors.ink),
              ),
              if (!address.isDefault)
                TextButton(
                  onPressed: onMakeDefault,
                  style: TextButton.styleFrom(foregroundColor: ZayloColors.ink),
                  child: const Text('Set as default'),
                ),
              const Spacer(),
              IconButton(
                tooltip: 'Delete address',
                onPressed: onDelete,
                icon: const Icon(Icons.delete_outline, color: Colors.redAccent),
              ),
            ]),
        ]),
      );
}
