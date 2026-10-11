import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/zaylo_theme.dart';
import '../../../core/widgets/buttons.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/form_fields.dart';
import '../../../core/widgets/layout.dart';
import '../../api/buyer_api.dart';
import '../../models/address_models.dart';
import '../../../shared/address/location_field.dart';
import '../../state/app_state.dart';

/// Adds or edits an address. Pops with the saved [Address].
class AddressFormScreen extends StatefulWidget {
  const AddressFormScreen({super.key, this.address});

  final Address? address;

  @override
  State<AddressFormScreen> createState() => _AddressFormScreenState();
}

/// Matches the web form's "No province / independent locality" option.
const _noProvince = Location(code: '__none__', name: 'No province / independent locality');

class _AddressFormScreenState extends State<AddressFormScreen> {
  final _form = GlobalKey<FormState>();
  late final _recipient = TextEditingController(text: widget.address?.recipientName);
  late final _phone = TextEditingController(text: widget.address?.phone);
  late final _line1 = TextEditingController(text: widget.address?.line1);
  late final _line2 = TextEditingController(text: widget.address?.line2);
  late final _postal = TextEditingController(text: widget.address?.postalCode);
  late String _label = widget.address?.label ?? 'Home';
  late bool _isDefault = widget.address?.isDefault ?? false;

  List<Location> _regions = [];
  List<Location> _provinces = [];
  List<Location> _independentCities = [];
  List<Location> _cities = [];
  List<Location> _barangays = [];
  Location? _region;
  Location? _province;
  Location? _city;
  Location? _barangay;

  bool _loadingLocations = false;
  String? _locationError;
  bool _saving = false;
  Map<String, String> _fieldErrors = {};

  BuyerApi get _api => AppScope.read(context).api;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _initLocations());
  }

  @override
  void dispose() {
    for (final controller in [_recipient, _phone, _line1, _line2, _postal]) {
      controller.dispose();
    }
    super.dispose();
  }

  /// Prefills the profile for new addresses and restores saved locations when editing.
  Future<void> _initLocations() async {
    final user = AppScope.read(context).user;
    if (widget.address == null && user != null) {
      _recipient.text = user.name;
      _phone.text = user.phone ?? '';
    }
    await _guard(() async {
      _regions = await _api.regions();
      final saved = widget.address;
      if (saved == null || !saved.isStructured) return;

      _region = _find(_regions, saved.regionCode);
      if (_region == null) return;
      await _loadRegionChildren(_region!);
      _province = saved.provinceCode == null
          ? (_provinces.isEmpty ? null : _noProvince)
          : _find(_provinces, saved.provinceCode);
      _cities = _province == null || _province == _noProvince
          ? _independentCities
          : await _api.cities(_province!.code);
      _city = _find(_cities, saved.cityCode);
      if (_city == null) return;
      _barangays = await _api.barangays(_city!.code);
      _barangay = _find(_barangays, saved.barangayCode);
    });
  }

  Location? _find(List<Location> items, String? code) => items.where((item) => item.code == code).firstOrNull;

  Future<void> _loadRegionChildren(Location region) async {
    final results = await Future.wait([_api.provinces(region.code), _api.regionCities(region.code)]);
    _provinces = results[0];
    _independentCities = results[1];
    // Regions without provinces (such as NCR) go straight to their cities.
    _cities = _provinces.isEmpty ? _independentCities : [];
  }

  /// Runs a location lookup with a spinner and a retryable error.
  Future<void> _guard(Future<void> Function() load) async {
    setState(() {
      _loadingLocations = true;
      _locationError = null;
    });
    try {
      await load();
    } on ApiException catch (error) {
      _locationError = error.message;
    } finally {
      if (mounted) setState(() => _loadingLocations = false);
    }
  }

  Future<void> _selectRegion(Location region) => _guard(() async {
        _region = region;
        _province = _city = _barangay = null;
        _provinces = _independentCities = _cities = _barangays = [];
        await _loadRegionChildren(region);
      });

  Future<void> _selectProvince(Location province) => _guard(() async {
        _province = province;
        _city = _barangay = null;
        _barangays = [];
        _cities = province == _noProvince ? _independentCities : await _api.cities(province.code);
      });

  Future<void> _selectCity(Location city) => _guard(() async {
        _city = city;
        _barangay = null;
        _barangays = await _api.barangays(city.code);
      });

  bool get _needsProvince => _region != null && _provinces.isNotEmpty;

  List<Location> get _provinceChoices =>
      [..._provinces, if (_independentCities.isNotEmpty) _noProvince];

  Future<void> _save() async {
    setState(() => _fieldErrors = {});
    final locationsChosen = _region != null && _city != null && _barangay != null && (!_needsProvince || _province != null);
    if (!(_form.currentState?.validate() ?? false) || !locationsChosen) {
      if (!locationsChosen) showMessage(context, 'Choose the region, city and barangay.');
      return;
    }
    setState(() => _saving = true);
    try {
      final saved = await _api.saveAddress(
        AddressInput(
          label: _label,
          recipientName: _recipient.text.trim(),
          phone: _phone.text.trim(),
          regionCode: _region!.code,
          provinceCode: _province == null || _province == _noProvince ? null : _province!.code,
          cityCode: _city!.code,
          barangayCode: _barangay!.code,
          line1: _line1.text.trim(),
          line2: _line2.text.trim(),
          postalCode: _postal.text.trim(),
          isDefault: _isDefault,
        ),
        id: widget.address?.id,
      );
      if (!mounted) return;
      showMessage(context, 'Address saved.');
      Navigator.pop(context, saved);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _fieldErrors = error.fieldErrors);
      showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(widget.address == null ? 'Add address' : 'Edit address')),
        body: Form(
          key: _form,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 8, ZayloSpace.page, 32),
            children: [
              const FieldLabel('Address type'),
              Wrap(spacing: 8, children: [
                for (final label in const ['Home', 'Work', 'School', 'Other'])
                  ChoiceChip(
                    label: Text(label),
                    selected: _label == label,
                    showCheckmark: false,
                    selectedColor: ZayloColors.ink,
                    backgroundColor: ZayloColors.white,
                    side: const BorderSide(color: ZayloColors.line),
                    labelStyle: TextStyle(
                        fontWeight: FontWeight.w600, color: _label == label ? ZayloColors.white : ZayloColors.ink),
                    onSelected: (_) => setState(() => _label = label),
                  ),
              ]),
              const SizedBox(height: 20),
              const FieldLabel('Recipient name'),
              TextFormField(
                controller: _recipient,
                textCapitalization: TextCapitalization.words,
                maxLength: 150,
                decoration: zayloInputDecoration('Full name', errorText: _fieldErrors['recipient_name'])
                    .copyWith(counterText: ''),
                validator: (value) => (value ?? '').trim().isEmpty ? 'Enter the recipient name.' : null,
              ),
              const SizedBox(height: 16),
              const FieldLabel('Mobile number'),
              TextFormField(
                controller: _phone,
                keyboardType: TextInputType.phone,
                inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9+ ()-]'))],
                decoration: zayloInputDecoration('09171234567', errorText: _fieldErrors['phone']),
                validator: (value) => RegExp(r'^\+?[0-9 ()-]{7,30}$').hasMatch((value ?? '').trim())
                    ? null
                    : 'Enter a valid phone number.',
              ),
              const SizedBox(height: 24),
              const Eyebrow('Location'),
              const SizedBox(height: 12),
              if (_locationError != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: SectionCard(
                    child: Row(children: [
                      const Icon(Icons.cloud_off_outlined, color: Colors.redAccent),
                      const SizedBox(width: 10),
                      Expanded(child: Text(_locationError!, style: const TextStyle(fontSize: 13))),
                      TextButton(onPressed: _initLocations, child: const Text('Retry')),
                    ]),
                  ),
                ),
              LocationField(
                label: 'Region',
                value: _region,
                options: _regions,
                errorText: _fieldErrors['region_code'],
                enabled: _regions.isNotEmpty,
                onSelected: _selectRegion,
              ),
              if (_needsProvince)
                LocationField(
                  label: 'Province',
                  value: _province,
                  options: _provinceChoices,
                  errorText: _fieldErrors['province_code'],
                  enabled: true,
                  onSelected: _selectProvince,
                ),
              LocationField(
                label: 'City / municipality',
                value: _city,
                options: _cities,
                errorText: _fieldErrors['city_municipality_code'],
                enabled: _cities.isNotEmpty,
                hint: _region == null ? 'Choose a region first' : (_needsProvince && _province == null ? 'Choose a province first' : null),
                onSelected: _selectCity,
              ),
              LocationField(
                label: 'Barangay',
                value: _barangay,
                options: _barangays,
                errorText: _fieldErrors['barangay_code'],
                enabled: _barangays.isNotEmpty,
                hint: _city == null ? 'Choose a city first' : null,
                onSelected: (barangay) => setState(() => _barangay = barangay),
              ),
              if (_loadingLocations)
                const Padding(
                  padding: EdgeInsets.only(bottom: 12),
                  child: LinearProgressIndicator(color: ZayloColors.ink),
                ),
              const FieldLabel('House no., street, building'),
              TextFormField(
                controller: _line1,
                maxLength: 255,
                decoration: zayloInputDecoration('e.g. 12 Mabini St.', errorText: _fieldErrors['line1'])
                    .copyWith(counterText: ''),
                validator: (value) => (value ?? '').trim().isEmpty ? 'Enter your street address.' : null,
              ),
              const SizedBox(height: 16),
              const FieldLabel('Unit, floor, landmark (optional)'),
              TextFormField(
                controller: _line2,
                maxLength: 255,
                decoration: zayloInputDecoration('e.g. Unit 4B, near the chapel', errorText: _fieldErrors['line2'])
                    .copyWith(counterText: ''),
              ),
              const SizedBox(height: 16),
              const FieldLabel('Postal code'),
              TextFormField(
                controller: _postal,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(4)],
                decoration: zayloInputDecoration('1000', errorText: _fieldErrors['postal_code']),
                validator: (value) =>
                    RegExp(r'^\d{4}$').hasMatch(value ?? '') ? null : 'Enter the 4-digit postal code.',
              ),
              const SizedBox(height: 10),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _isDefault,
                activeThumbColor: ZayloColors.ink,
                title: const Text('Use as my default address',
                    style: TextStyle(fontWeight: FontWeight.w600, color: ZayloColors.ink, fontSize: 14)),
                onChanged: widget.address?.isDefault == true ? null : (value) => setState(() => _isDefault = value),
              ),
              const SizedBox(height: 16),
              PrimaryButton(label: 'SAVE ADDRESS', busy: _saving, onPressed: _loadingLocations ? null : _save),
            ],
          ),
        ),
      );
}

/// Tappable field that opens a searchable list; barangay lists can be long.
