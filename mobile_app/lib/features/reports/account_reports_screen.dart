import 'package:flutter/material.dart';
import 'package:zaylo_mobile/api/api_client.dart';
import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/feedback.dart';

/// Private account reports, shared by buyer and rider sessions and reviewed by admins.
class AccountReportsScreen extends StatefulWidget {
  const AccountReportsScreen({super.key, required this.api, this.shopId, this.shopName});
  final ApiClient api;
  final int? shopId;
  final String? shopName;

  @override
  State<AccountReportsScreen> createState() => _AccountReportsScreenState();
}

class _AccountReportsScreenState extends State<AccountReportsScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _details = TextEditingController();
  final _order = TextEditingController();
  late Future<Map<String, dynamic>> _feed = widget.api.accountReports();
  String? _category;
  String? _role;
  String? _error;
  bool _sending = false;

  @override
  void dispose() {
    _name.dispose();
    _details.dispose();
    _order.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_sending || !_form.currentState!.validate()) return;
    setState(() { _sending = true; _error = null; });
    try {
      await widget.api.reportAccount({
        if (widget.shopId != null) 'shop_id': widget.shopId,
        'reported_name': _name.text.trim(), 'reported_role': _role, 'category': _category,
        'details': _details.text.trim(), 'order_reference': _order.text.trim(),
      });
      if (!mounted) return;
      _details.clear();
      _order.clear();
      setState(() { _feed = widget.api.accountReports(); });
      showMessage(context, 'Report submitted to the ZAYLO admin team.');
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.fieldErrors.values.isNotEmpty ? error.fieldErrors.values.join('\n') : error.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not submit the report. Check your connection and try again.');
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Report an account')),
    body: FutureBuilder<Map<String, dynamic>>(
      future: _feed,
      builder: (context, snapshot) {
        if (snapshot.hasError) return Center(child: TextButton(onPressed: () => setState(() => _feed = widget.api.accountReports()), child: const Text('Could not load reports. Tap to retry.')));
        if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
        final categories = Map<String, dynamic>.from(snapshot.data!['categories'] as Map);
        final reports = ((snapshot.data!['reports'] as Map)['data'] as List).cast<Map<String, dynamic>>();
        return Form(key: _form, child: ListView(padding: const EdgeInsets.all(20), children: [
          const Text('Report misconduct, harassment, suspected fraud, or a service concern. Your report is visible to you and ZAYLO administrators.'),
          const SizedBox(height: 16),
          if (widget.shopId != null) Text('Reporting seller: ${widget.shopName ?? 'Shop #${widget.shopId}'}')
          else ...[
            TextFormField(controller: _name, maxLength: 120, decoration: const InputDecoration(labelText: 'Account or shop name'), validator: (value) => value!.trim().isEmpty ? 'Enter the account or shop name.' : null),
            DropdownButtonFormField<String>(decoration: const InputDecoration(labelText: 'Role (optional)'), items: [
              for (final role in ['buyer', 'seller', 'rider', 'courier', 'logistics', 'admin']) DropdownMenuItem(value: role, child: Text(role[0].toUpperCase() + role.substring(1))),
            ], onChanged: _sending ? null : (value) => _role = value),
          ],
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(isExpanded: true, decoration: const InputDecoration(labelText: 'Reason'), items: [
            for (final entry in categories.entries) DropdownMenuItem(value: entry.key, child: Text('${entry.value}', overflow: TextOverflow.ellipsis)),
          ], onChanged: _sending ? null : (value) => _category = value, validator: (value) => value == null ? 'Choose a reason.' : null),
          const SizedBox(height: 16),
          TextFormField(controller: _details, maxLength: 3000, minLines: 5, maxLines: 8, decoration: const InputDecoration(labelText: 'What happened?', hintText: 'Describe the incident and when it happened. Do not include passwords or payment PINs.'), validator: (value) => value!.trim().length < 20 ? 'Describe the concern in at least 20 characters.' : null),
          TextFormField(controller: _order, maxLength: 100, decoration: const InputDecoration(labelText: 'Order reference (optional)')),
          if (_error != null) Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
          FilledButton(onPressed: _sending ? null : _submit, child: Text(_sending ? 'Submitting…' : 'Submit report')),
          const SizedBox(height: 24),
          Text('My reports', style: Theme.of(context).textTheme.titleLarge),
          if (reports.isEmpty) const Padding(padding: EdgeInsets.only(top: 12), child: Text('No reports submitted yet.')),
          for (final report in reports) Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('#${report['id']} · ${report['reported_name']}', style: Theme.of(context).textTheme.titleMedium),
            Text('${report['status']}'.replaceAll('_', ' ')),
            const SizedBox(height: 8), Text('${report['details']}'),
            if (report['resolution'] != null) ...[const SizedBox(height: 8), Text('Admin response: ${report['resolution']}')],
          ]))),
        ]));
      },
    ),
  );
}
