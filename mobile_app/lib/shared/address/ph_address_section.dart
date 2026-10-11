import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/models/location.dart';
import '../../core/theme/zaylo_theme.dart';
import '../../core/widgets/form_fields.dart';
import 'location_field.dart';

/// Matches the web form's "No province / independent locality" option, for
/// regions that have both provinces and cities outside any province.
const noProvinceLocation = Location(code: '__none__', name: 'No province / independent locality');

/// A saved Philippine address, as codes plus the street and postal code.
class PhAddressValue {
  const PhAddressValue({
    required this.regionCode,
    this.provinceCode,
    required this.cityCode,
    required this.barangayCode,
    required this.line1,
    required this.postalCode,
  });

  final String regionCode;
  final String? provinceCode;
  final String cityCode;
  final String barangayCode;
  final String line1;
  final String postalCode;

  /// Field names the API expects (RiderAddressRules on the server).
  Map<String, String> toFields() => {
        'region_code': regionCode,
        if (provinceCode != null) 'province_code': provinceCode!,
        'city_municipality_code': cityCode,
        'barangay_code': barangayCode,
        'line1': line1,
        'postal_code': postalCode,
      };
}

/// Holds the choices in a [PhAddressSection] so the parent can read them on
/// submit (and keep them when a multi-step form moves between steps).
class PhAddressController extends ChangeNotifier {
  PhAddressController({PhAddressValue? initial})
      : initial = initial,
        line1 = TextEditingController(text: initial?.line1),
        postalCode = TextEditingController(text: initial?.postalCode);

  /// Restored into the dropdowns the first time the section loads.
  final PhAddressValue? initial;
  final TextEditingController line1;
  final TextEditingController postalCode;

  Location? region;
  Location? province;
  Location? city;
  Location? barangay;

  List<Location> regions = [];
  List<Location> provinces = [];
  List<Location> independentCities = [];
  List<Location> cities = [];
  List<Location> barangays = [];
  bool _loaded = false;

  /// The complete address, or null while something is still missing.
  PhAddressValue? get value {
    if (region == null || city == null || barangay == null) return null;
    if (provinces.isNotEmpty && province == null) return null;
    return PhAddressValue(
      regionCode: region!.code,
      provinceCode: province == null || province == noProvinceLocation ? null : province!.code,
      cityCode: city!.code,
      barangayCode: barangay!.code,
      line1: line1.text.trim(),
      postalCode: postalCode.text.trim(),
    );
  }

  void _changed() => notifyListeners();

  @override
  void dispose() {
    line1.dispose();
    postalCode.dispose();
    super.dispose();
  }
}

/// Region -> province -> city/municipality -> barangay dropdowns (PSGC, from
/// the API), then house/street and postal code. Validates with the enclosing
/// [Form]; [errors] shows server messages keyed by API field name.
class PhAddressSection extends StatefulWidget {
  const PhAddressSection({super.key, required this.api, required this.controller, this.errors = const {}});

  final ApiClient api;
  final PhAddressController controller;
  final Map<String, String> errors;

  @override
  State<PhAddressSection> createState() => _PhAddressSectionState();
}

class _PhAddressSectionState extends State<PhAddressSection> {
  bool _loading = false;
  String? _loadError;

  PhAddressController get _c => widget.controller;
  ApiClient get _api => widget.api;

  @override
  void initState() {
    super.initState();
    if (!_c._loaded) _restore();
  }

  Future<void> _guard(Future<void> Function() load) async {
    setState(() {
      _loading = true;
      _loadError = null;
    });
    try {
      await load();
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Address lookup failed: ${error.debugDescription}');
      _loadError = error.message;
    } finally {
      if (mounted) setState(() => _loading = false);
      _c._changed();
    }
  }

  Location? _find(List<Location> items, String? code) => items.where((item) => item.code == code).firstOrNull;

  /// Loads regions, then walks down to a saved address if there is one.
  Future<void> _restore() => _guard(() async {
        _c.regions = await _api.regions();
        _c._loaded = true;
        final saved = _c.initial;
        if (saved == null) return;
        _c.region = _find(_c.regions, saved.regionCode);
        if (_c.region == null) return;
        await _loadRegionChildren(_c.region!);
        _c.province = saved.provinceCode == null
            ? (_c.provinces.isEmpty ? null : noProvinceLocation)
            : _find(_c.provinces, saved.provinceCode);
        _c.cities = _c.province == null || _c.province == noProvinceLocation
            ? _c.independentCities
            : await _api.cities(_c.province!.code);
        _c.city = _find(_c.cities, saved.cityCode);
        if (_c.city == null) return;
        _c.barangays = await _api.barangays(_c.city!.code);
        _c.barangay = _find(_c.barangays, saved.barangayCode);
      });

  Future<void> _loadRegionChildren(Location region) async {
    final results = await Future.wait([_api.provinces(region.code), _api.regionCities(region.code)]);
    _c.provinces = results[0];
    _c.independentCities = results[1];
    // Regions without provinces (such as NCR) go straight to their cities.
    _c.cities = _c.provinces.isEmpty ? _c.independentCities : [];
  }

  Future<void> _selectRegion(Location region) => _guard(() async {
        _c.region = region;
        _c.province = _c.city = _c.barangay = null;
        _c.provinces = _c.independentCities = _c.cities = _c.barangays = [];
        await _loadRegionChildren(region);
      });

  Future<void> _selectProvince(Location province) => _guard(() async {
        _c.province = province;
        _c.city = _c.barangay = null;
        _c.barangays = [];
        _c.cities = province == noProvinceLocation ? _c.independentCities : await _api.cities(province.code);
      });

  Future<void> _selectCity(Location city) => _guard(() async {
        _c.city = city;
        _c.barangay = null;
        _c.barangays = await _api.barangays(city.code);
      });

  void _selectBarangay(Location barangay) {
    setState(() => _c.barangay = barangay);
    _c._changed();
  }

  /// A [LocationField] that also takes part in the enclosing form's validation.
  Widget _field({
    required String label,
    required Location? value,
    required List<Location> options,
    required bool enabled,
    required ValueChanged<Location> onSelected,
    required String apiField,
    required String requiredMessage,
    bool required = true,
  }) =>
      FormField<Location>(
        // Rebuilt with the current choice so validation sees it.
        key: ValueKey('$apiField:${value?.code}'),
        initialValue: value,
        validator: (current) => required && current == null ? requiredMessage : null,
        builder: (state) => LocationField(
          label: label,
          value: value,
          options: options,
          enabled: enabled && !_loading,
          onSelected: (choice) {
            state.didChange(choice);
            onSelected(choice);
          },
          errorText: state.errorText ?? widget.errors[apiField],
        ),
      );

  @override
  Widget build(BuildContext context) {
    final provinceOptions = [
      ..._c.provinces,
      if (_c.provinces.isNotEmpty && _c.independentCities.isNotEmpty) noProvinceLocation,
    ];
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      if (_loadError != null)
        Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Row(children: [
            const Icon(Icons.error_outline, color: Colors.redAccent, size: 18),
            const SizedBox(width: 8),
            Expanded(child: Text(_loadError!, style: const TextStyle(color: Colors.redAccent))),
            TextButton(onPressed: _c.regions.isEmpty ? _restore : () => setState(() => _loadError = null), child: const Text('Retry')),
          ]),
        ),
      _field(
        label: 'Region',
        value: _c.region,
        options: _c.regions,
        enabled: _c.regions.isNotEmpty,
        onSelected: _selectRegion,
        apiField: 'region_code',
        requiredMessage: 'Choose your region.',
      ),
      if (provinceOptions.isNotEmpty)
        _field(
          label: 'Province',
          value: _c.province,
          options: provinceOptions,
          enabled: true,
          onSelected: _selectProvince,
          apiField: 'province_code',
          requiredMessage: 'Choose your province.',
        ),
      _field(
        label: 'City / Municipality',
        value: _c.city,
        options: _c.cities,
        enabled: _c.cities.isNotEmpty,
        onSelected: _selectCity,
        apiField: 'city_municipality_code',
        requiredMessage: 'Choose your city or municipality.',
      ),
      _field(
        label: 'Barangay',
        value: _c.barangay,
        options: _c.barangays,
        enabled: _c.barangays.isNotEmpty,
        onSelected: _selectBarangay,
        apiField: 'barangay_code',
        requiredMessage: 'Choose your barangay.',
      ),
      if (_loading)
        const Padding(
          padding: EdgeInsets.only(bottom: 12),
          child: LinearProgressIndicator(minHeight: 2, color: ZayloColors.ink),
        ),
      const FieldLabel('House number, building, and street'),
      TextFormField(
        controller: _c.line1,
        textCapitalization: TextCapitalization.words,
        autofillHints: const [AutofillHints.streetAddressLine1],
        maxLength: 255,
        decoration: zayloInputDecoration('e.g. 12 Rizal St., Purok 3', errorText: widget.errors['line1'])
            .copyWith(counterText: ''),
        validator: (value) => (value ?? '').trim().isEmpty ? 'Enter your house number and street.' : null,
      ),
      const SizedBox(height: 18),
      const FieldLabel('Postal code'),
      TextFormField(
        controller: _c.postalCode,
        keyboardType: TextInputType.number,
        autofillHints: const [AutofillHints.postalCode],
        inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(4)],
        decoration: zayloInputDecoration('4027', errorText: widget.errors['postal_code']),
        validator: (value) => RegExp(r'^\d{4}$').hasMatch(value ?? '') ? null : 'Enter your 4-digit postal code.',
      ),
    ]);
  }
}
