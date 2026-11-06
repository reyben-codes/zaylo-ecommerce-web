import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/form_fields.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/models/rider/rider_wallet_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

/// The rider's wallet: the commission from every delivered parcel, and withdrawals to GCash, which the rider's
/// logistics partner sends. Opened from the Account tab and the Earnings screen.
class RiderWalletScreen extends StatefulWidget {
  const RiderWalletScreen({super.key, required this.api});

  final RiderApi api;

  @override
  State<RiderWalletScreen> createState() => _RiderWalletScreenState();
}

class _RiderWalletScreenState extends State<RiderWalletScreen> {
  late Future<RiderWallet> _wallet = widget.api.wallet();

  Future<void> _reload() async {
    final wallet = widget.api.wallet();
    setState(() {
      _wallet = wallet;
    });
    try {
      await wallet;
    } on Exception {
      // Shown by the FutureBuilder's error state.
    }
  }

  Future<void> _withdraw(RiderWallet wallet) async {
    final result = await showModalBottomSheet<(String, RiderWallet)>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      backgroundColor: ZayloColors.paper,
      builder: (_) => _WithdrawSheet(api: widget.api, wallet: wallet),
    );
    if (result == null || !mounted) return;
    final (message, updated) = result;
    setState(() {
      _wallet = Future.value(updated);
    });
    showMessage(context, message, duration: const Duration(seconds: 5));
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: ZayloColors.white,
        appBar: AppBar(backgroundColor: ZayloColors.white, title: const Text('Wallet')),
        body: FutureBuilder<RiderWallet>(
          future: _wallet,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done && !snapshot.hasData) return const Loader();
            if (snapshot.hasError) {
              return LoadError(error: snapshot.error, onRetry: _reload, title: 'Could not load your wallet');
            }
            final wallet = snapshot.requireData;
            return RefreshIndicator(
              color: ZayloColors.ink,
              onRefresh: _reload,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
                children: [
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(color: ZayloColors.ink, borderRadius: BorderRadius.circular(18)),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('Available balance', style: TextStyle(color: ZayloColors.onInkMuted)),
                      const SizedBox(height: 6),
                      Text(peso(wallet.balance),
                          style: TextStyle(color: ZayloColors.white, fontSize: 30, fontWeight: FontWeight.w800)),
                      const SizedBox(height: 4),
                      Text('${peso(wallet.commissionPerParcel)} for every parcel you deliver',
                          style: TextStyle(color: ZayloColors.white)),
                      const SizedBox(height: 16),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton.icon(
                          onPressed: wallet.canWithdraw ? () => _withdraw(wallet) : null,
                          style: FilledButton.styleFrom(
                            backgroundColor: ZayloColors.white,
                            foregroundColor: ZayloColors.ink,
                            disabledBackgroundColor: ZayloColors.white.withValues(alpha: .4),
                            disabledForegroundColor: ZayloColors.ink.withValues(alpha: .6),
                            minimumSize: const Size.fromHeight(46),
                          ),
                          icon: const Icon(Icons.account_balance_wallet_outlined),
                          label: const Text('WITHDRAW TO GCASH'),
                        ),
                      ),
                      if (!wallet.canWithdraw) ...[
                        const SizedBox(height: 8),
                        Text('You can withdraw once you have ${peso(wallet.minWithdrawal)} or more.',
                            style: TextStyle(fontSize: 12, color: ZayloColors.onInkMuted)),
                      ],
                    ]),
                  ),
                  const SizedBox(height: 12),
                  Row(children: [
                    Expanded(child: _Stat(label: 'Earned', value: peso(wallet.earned), note: '${wallet.parcels} parcels')),
                    const SizedBox(width: 10),
                    Expanded(child: _Stat(label: 'Processing', value: peso(wallet.pending))),
                    const SizedBox(width: 10),
                    Expanded(child: _Stat(label: 'Withdrawn', value: peso(wallet.withdrawn))),
                  ]),
                  const SizedBox(height: 22),
                  _heading(context, 'Withdrawals'),
                  if (wallet.withdrawals.isEmpty)
                    const _Empty('Your GCash withdrawals appear here.')
                  else
                    SectionCard(
                      padding: EdgeInsets.zero,
                      child: Column(children: [for (final withdrawal in wallet.withdrawals) _WithdrawalTile(withdrawal)]),
                    ),
                  const SizedBox(height: 22),
                  _heading(context, 'Commissions'),
                  if (wallet.commissions.isEmpty)
                    const _Empty('Deliver a parcel to earn your first commission.')
                  else
                    SectionCard(
                      padding: EdgeInsets.zero,
                      child: Column(children: [
                        for (final commission in wallet.commissions)
                          ListTile(
                            dense: true,
                            leading: const Icon(Icons.inventory_2_outlined),
                            title: Text(commission.trackingNumber ?? 'Delivered parcel'),
                            subtitle: commission.createdAt == null ? null : Text(shortDate(commission.createdAt)),
                            trailing: Text('+${peso(commission.amount)}',
                                style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.success)),
                          ),
                      ]),
                    ),
                  if (wallet.note != null) ...[
                    const SizedBox(height: 14),
                    Text(wallet.note!, style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
                  ],
                ],
              ),
            );
          },
        ),
      );

  Widget _heading(BuildContext context, String text) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: Text(text, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
      );
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.value, this.note});

  final String label;
  final String value;
  final String? note;

  @override
  Widget build(BuildContext context) => SectionCard(
        padding: const EdgeInsets.all(12),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
          const SizedBox(height: 4),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(value, style: TextStyle(fontWeight: FontWeight.w800, color: ZayloColors.ink)),
          ),
          if (note != null) Text(note!, style: TextStyle(fontSize: 11, color: ZayloColors.muted)),
        ]),
      );
}

class _Empty extends StatelessWidget {
  const _Empty(this.message);

  final String message;

  @override
  Widget build(BuildContext context) => SectionCard(
        child: Text(message, style: TextStyle(color: ZayloColors.muted)),
      );
}

class _WithdrawalTile extends StatelessWidget {
  const _WithdrawalTile(this.withdrawal);

  final RiderWithdrawalEntry withdrawal;

  @override
  Widget build(BuildContext context) {
    final color = switch (withdrawal.status) {
      'paid' => ZayloColors.success,
      'rejected' => ZayloColors.danger,
      _ => ZayloColors.warning,
    };
    final detail = switch (withdrawal.status) {
      'paid' when withdrawal.reference != null => 'Ref. ${withdrawal.reference}',
      'rejected' when withdrawal.rejectionReason != null => '${withdrawal.rejectionReason} The amount is back in your wallet.',
      _ => 'Your logistics partner will send it soon.',
    };
    return ListTile(
      leading: Icon(Icons.phone_android_outlined, color: color),
      title: Text('${peso(withdrawal.amount)} to ${withdrawal.gcashNumber}', style: const TextStyle(fontWeight: FontWeight.w700)),
      subtitle: Text([if (withdrawal.createdAt != null) shortDate(withdrawal.createdAt), detail].join(' · ')),
      trailing: Text(withdrawal.statusLabel, style: TextStyle(fontWeight: FontWeight.w700, color: color, fontSize: 12)),
    );
  }
}

/// Amount and GCash account for a withdrawal. Pops the confirmation and updated wallet once it is requested.
class _WithdrawSheet extends StatefulWidget {
  const _WithdrawSheet({required this.api, required this.wallet});

  final RiderApi api;
  final RiderWallet wallet;

  @override
  State<_WithdrawSheet> createState() => _WithdrawSheetState();
}

class _WithdrawSheetState extends State<_WithdrawSheet> {
  static final _gcashNumber = RegExp(r'^09\d{9}$');

  final _formKey = GlobalKey<FormState>();
  late final _amount = TextEditingController(text: widget.wallet.balance.toStringAsFixed(2));
  late final _number = TextEditingController(text: widget.wallet.gcashNumber ?? '');
  late final _name = TextEditingController(text: widget.wallet.gcashName ?? '');
  bool _sending = false;
  Map<String, String> _errors = const {};

  @override
  void dispose() {
    _amount.dispose();
    _number.dispose();
    _name.dispose();
    super.dispose();
  }

  String? _amountError(String? value) {
    final amount = double.tryParse((value ?? '').replaceAll(',', ''));
    if (amount == null) return 'Enter an amount.';
    if (amount < widget.wallet.minWithdrawal) return 'The minimum withdrawal is ${peso(widget.wallet.minWithdrawal)}.';
    if (amount > widget.wallet.balance) return 'You can withdraw up to ${peso(widget.wallet.balance)}.';
    return null;
  }

  Future<void> _submit() async {
    setState(() => _errors = const {});
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _sending = true);
    try {
      final result = await widget.api.withdraw(
        amount: double.parse(_amount.text.replaceAll(',', '')),
        gcashNumber: _number.text.trim(),
        gcashName: _name.text.trim(),
      );
      if (mounted) Navigator.of(context).pop(result);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _errors = error.fieldErrors);
      if (error.fieldErrors.isEmpty || error.fieldErrors.containsKey('withdrawal')) {
        showMessage(context, error.fieldErrors['withdrawal'] ?? error.message);
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) => Padding(
        padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.viewInsetsOf(context).bottom),
        child: SingleChildScrollView(
          child: Form(
            key: _formKey,
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Text('Withdraw to GCash', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
              const SizedBox(height: 6),
              Text('Available: ${peso(widget.wallet.balance)} · minimum ${peso(widget.wallet.minWithdrawal)}',
                  style: TextStyle(color: ZayloColors.muted)),
              const SizedBox(height: 18),
              const FieldLabel('Amount'),
              TextFormField(
                controller: _amount,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.]'))],
                decoration: zayloInputDecoration('0.00', errorText: _errors['amount']).copyWith(prefixText: '₱ '),
                validator: _amountError,
              ),
              const SizedBox(height: 14),
              const FieldLabel('GCash number'),
              TextFormField(
                controller: _number,
                keyboardType: TextInputType.phone,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(11)],
                decoration: zayloInputDecoration('09XXXXXXXXX', errorText: _errors['gcash_number']),
                validator: (value) =>
                    _gcashNumber.hasMatch(value ?? '') ? null : 'Enter your 11-digit GCash number starting with 09.',
              ),
              const SizedBox(height: 14),
              const FieldLabel('Name on the GCash account'),
              TextFormField(
                controller: _name,
                textCapitalization: TextCapitalization.words,
                maxLength: 100,
                decoration: zayloInputDecoration('e.g. Juan Dela Cruz', errorText: _errors['gcash_name']),
                validator: (value) => (value ?? '').trim().length < 2 ? 'Enter the name on your GCash account.' : null,
              ),
              const SizedBox(height: 6),
              Text('Your logistics partner sends the money, usually within 1–2 business days. Check that the number and name match your GCash account.',
                  style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
              const SizedBox(height: 16),
              PrimaryButton(label: 'REQUEST WITHDRAWAL', busy: _sending, onPressed: _submit),
            ]),
          ),
        ),
      );
}
