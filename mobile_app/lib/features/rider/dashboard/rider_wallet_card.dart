import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/models/rider/rider_wallet_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

/// The rider's wallet on the Earnings screen: the balance from delivery fees,
/// a button to cash it out to GCash, and the latest cash-outs. GCash is
/// simulated on the server, which the card says plainly.
class RiderWalletCard extends StatefulWidget {
  const RiderWalletCard({super.key, required this.api, this.phone, this.name});

  final RiderApi api;

  /// Filled into the cash-out form to save typing.
  final String? phone;
  final String? name;

  @override
  State<RiderWalletCard> createState() => _RiderWalletCardState();
}

class _RiderWalletCardState extends State<RiderWalletCard> {
  late Future<RiderWallet> _wallet = widget.api.wallet();

  Future<void> _cashOut(RiderWallet wallet) async {
    final result = await showModalBottomSheet<WalletCashoutResult>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      backgroundColor: ZayloColors.white,
      builder: (_) => _CashOutSheet(api: widget.api, wallet: wallet, phone: widget.phone, name: widget.name),
    );
    if (result == null || !mounted) return;
    setState(() => _wallet = Future.value(result.wallet));
    showMessage(context, result.message, duration: const Duration(seconds: 5));
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<RiderWallet>(
        future: _wallet,
        builder: (context, snapshot) {
          if (!snapshot.hasData) {
            // The earnings page still works if the wallet cannot load (e.g. an older server).
            if (snapshot.hasError) return const SizedBox.shrink();
            return const SectionCard(child: SizedBox(height: 96, child: Loader()));
          }
          final wallet = snapshot.requireData;
          return SectionCard(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Icon(Icons.account_balance_wallet_outlined, color: ZayloColors.bronze),
                const SizedBox(width: 8),
                Text('Wallet', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                const Spacer(),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(color: ZayloColors.cream, borderRadius: BorderRadius.circular(99)),
                  child: Text('Demo GCash', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: ZayloColors.bronze)),
                ),
              ]),
              const SizedBox(height: 12),
              Text('Balance', style: TextStyle(color: ZayloColors.muted, fontSize: 12)),
              Semantics(
                label: 'Wallet balance ${peso(wallet.balance)}',
                excludeSemantics: true,
                child: Text(peso(wallet.balance),
                    style: TextStyle(color: ZayloColors.success, fontSize: 28, fontWeight: FontWeight.w800)),
              ),
              const SizedBox(height: 4),
              Text('${peso(wallet.credits)} earned · ${peso(wallet.cashedOut)} cashed out',
                  style: TextStyle(color: ZayloColors.muted, fontSize: 12)),
              const SizedBox(height: 14),
              PrimaryButton(
                label: 'Cash out to GCash',
                icon: Icons.send_outlined,
                onPressed: wallet.canCashOut ? () => _cashOut(wallet) : null,
              ),
              if (!wallet.available)
                _Note('Cash-outs turn on after ZAYLO updates its server.')
              else if (!wallet.canCashOut)
                _Note('You can cash out once your balance reaches ${peso(wallet.minimum, cents: false)}.'),
              if (wallet.demoNote != null) _Note(wallet.demoNote!),
              if (wallet.history.isNotEmpty) ...[
                const SizedBox(height: 14),
                Text('Recent cash-outs', style: const TextStyle(fontWeight: FontWeight.w700)),
                for (final cashout in wallet.history.take(5))
                  Padding(
                    padding: const EdgeInsets.only(top: 10),
                    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Icon(Icons.check_circle, size: 18, color: ZayloColors.success),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text('${peso(cashout.amount)} to GCash ${cashout.gcashNumber}',
                              style: const TextStyle(fontWeight: FontWeight.w600)),
                          Text('${dateTimeLabel(cashout.createdAt)} · ${cashout.reference}',
                              style: TextStyle(color: ZayloColors.muted, fontSize: 12)),
                        ]),
                      ),
                    ]),
                  ),
              ],
            ]),
          );
        },
      );
}

class _Note extends StatelessWidget {
  const _Note(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 8),
        child: Text(text, style: TextStyle(color: ZayloColors.muted, fontSize: 12)),
      );
}

/// Amount, GCash number and account name, then a confirmation before sending.
class _CashOutSheet extends StatefulWidget {
  const _CashOutSheet({required this.api, required this.wallet, this.phone, this.name});

  final RiderApi api;
  final RiderWallet wallet;
  final String? phone;
  final String? name;

  @override
  State<_CashOutSheet> createState() => _CashOutSheetState();
}

class _CashOutSheetState extends State<_CashOutSheet> {
  final _form = GlobalKey<FormState>();
  late final _amount = TextEditingController();
  late final _number = TextEditingController(text: RegExp(r'^09\d{9}$').hasMatch(widget.phone ?? '') ? widget.phone : '');
  late final _name = TextEditingController(text: widget.name ?? '');
  bool _sending = false;
  Map<String, String> _serverErrors = const {};

  @override
  void dispose() {
    _amount.dispose();
    _number.dispose();
    _name.dispose();
    super.dispose();
  }

  String? _checkAmount(String? value) {
    final amount = double.tryParse((value ?? '').replaceAll(',', ''));
    if (amount == null) return 'Enter an amount.';
    if (amount < widget.wallet.minimum) return 'The smallest cash-out is ${peso(widget.wallet.minimum, cents: false)}.';
    if (amount > widget.wallet.limit) return 'You can cash out up to ${peso(widget.wallet.limit)}.';
    return _serverErrors['amount'];
  }

  Future<void> _send() async {
    setState(() => _serverErrors = const {});
    if (!(_form.currentState?.validate() ?? false)) return;
    final amount = double.parse(_amount.text.replaceAll(',', ''));
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cash out to GCash?'),
        content: Text('${peso(amount)} goes to ${_number.text} (${_name.text.trim()}). This is a demo transfer: no real money is sent.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Not now')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Cash out')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _sending = true);
    try {
      final result = await widget.api.cashOut(amount: amount, gcashNumber: _number.text, gcashName: _name.text.trim());
      if (mounted) Navigator.of(context).pop(result);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _serverErrors = error.fieldErrors);
      if (error.fieldErrors.isEmpty) showMessage(context, error.message);
      _form.currentState?.validate();
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) => Padding(
        padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.viewInsetsOf(context).bottom),
        child: Form(
          key: _form,
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Cash out to GCash', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 4),
            Text('Available: ${peso(widget.wallet.balance)}', style: TextStyle(color: ZayloColors.muted)),
            const SizedBox(height: 16),
            TextFormField(
              controller: _amount,
              autofocus: true,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]'))],
              decoration: InputDecoration(
                labelText: 'Amount (₱)',
                helperText: '${peso(widget.wallet.minimum, cents: false)} to ${peso(widget.wallet.limit)}',
              ),
              validator: _checkAmount,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _number,
              keyboardType: TextInputType.phone,
              autofillHints: const [AutofillHints.telephoneNumberNational],
              maxLength: 11,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: const InputDecoration(labelText: 'GCash number', hintText: '09XXXXXXXXX', counterText: ''),
              validator: (value) => RegExp(r'^09\d{9}$').hasMatch(value ?? '')
                  ? _serverErrors['gcash_number']
                  : 'Enter the 11-digit GCash mobile number, starting with 09.',
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _name,
              textCapitalization: TextCapitalization.words,
              autofillHints: const [AutofillHints.name],
              maxLength: 100,
              decoration: const InputDecoration(labelText: 'Name on the GCash account', counterText: ''),
              validator: (value) => (value ?? '').trim().length < 2 ? 'Enter the name on the GCash account.' : _serverErrors['gcash_name'],
            ),
            const SizedBox(height: 18),
            PrimaryButton(label: 'Cash out', icon: Icons.send_outlined, busy: _sending, onPressed: _send),
          ]),
        ),
      );
}
