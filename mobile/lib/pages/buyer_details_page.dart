import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class BuyerDetailsPage extends StatefulWidget {
  const BuyerDetailsPage({super.key});

  @override
  State<BuyerDetailsPage> createState() => _BuyerDetailsPageState();
}

class _BuyerDetailsPageState extends State<BuyerDetailsPage> {
  final Color background = const Color(0xFFFAF7F2);
  final Color dark = const Color(0xFF1A1714);
  final Color secondaryText = const Color(0xFF6B5F54);
  final Color border = const Color(0xFFE5DFD8);
  final Color brown = const Color(0xFFB28B6F);

  final firstNameController = TextEditingController();
  final lastNameController = TextEditingController();
  final phoneController = TextEditingController();
  final streetController = TextEditingController();

  String? selectedRegion;
  String? selectedProvince;
  String? selectedCity;
  String? selectedBarangay;

  final List<String> regions = [
    'NCR',
    'Region IV-A - CALABARZON',
    'Region III - Central Luzon',
    'Region V - Bicol Region',
  ];

  final List<String> provinces = [
    'Laguna',
    'Cavite',
    'Batangas',
    'Rizal',
    'Quezon',
  ];

  final List<String> cities = [
    'Santa Cruz',
    'Calamba',
    'Biñan',
    'San Pablo',
    'Los Baños',
  ];

  final List<String> barangays = [
    'Barangay 1',
    'Barangay 2',
    'Barangay 3',
    'Barangay 4',
    'Barangay 5',
  ];

  @override
  void dispose() {
    firstNameController.dispose();
    lastNameController.dispose();
    phoneController.dispose();
    streetController.dispose();
    super.dispose();
  }

  Widget _label(String text, {bool required = true}) {
    return RichText(
      text: TextSpan(
        children: [
          TextSpan(
            text: text,
            style: GoogleFonts.inter(
              fontSize: 11,
              fontWeight: FontWeight.w500,
              color: dark,
            ),
          ),
          if (required)
            TextSpan(
              text: ' *',
              style: GoogleFonts.inter(
                fontSize: 11,
                color: const Color(0xFF9A4D3F),
              ),
            ),
        ],
      ),
    );
  }

  InputDecoration _inputDecoration({
    required String hint,
  }) {
    return InputDecoration(
      hintText: hint,
      hintStyle: GoogleFonts.inter(
        fontSize: 13,
        color: const Color(0xFFB8AEA5),
      ),
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(
        horizontal: 16,
        vertical: 15,
      ),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(4),
        borderSide: BorderSide(color: border),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(4),
        borderSide: BorderSide(color: border),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(4),
        borderSide: BorderSide(
          color: dark,
          width: 1.2,
        ),
      ),
    );
  }

  Widget _dropdown({
    required String hint,
    required String? value,
    required List<String> items,
    required ValueChanged<String?> onChanged,
    bool enabled = true,
  }) {
    return DropdownButtonFormField<String>(
      initialValue: value,
      isExpanded: true,
      decoration: InputDecoration(
        filled: true,
        fillColor: enabled
            ? Colors.white
            : const Color(0xFFF3EFEA),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 15,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(4),
          borderSide: BorderSide(color: border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(4),
          borderSide: BorderSide(color: border),
        ),
        disabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(4),
          borderSide: BorderSide(color: border),
        ),
      ),
      hint: Text(
        hint,
        style: GoogleFonts.inter(
          fontSize: 13,
          color: enabled
              ? const Color(0xFF6B5F54)
              : const Color(0xFFD5CEC6),
        ),
      ),
      icon: Icon(
        Icons.keyboard_arrow_down,
        size: 18,
        color: enabled
            ? secondaryText
            : const Color(0xFFD5CEC6),
      ),
      style: GoogleFonts.inter(
        fontSize: 13,
        color: dark,
      ),
      items: enabled
          ? items
              .map(
                (item) => DropdownMenuItem<String>(
                  value: item,
                  child: Text(item),
                ),
              )
              .toList()
          : null,
      onChanged: enabled ? onChanged : null,
    );
  }

  Widget _progressItem({
    required String number,
    required String title,
    required bool completed,
    required bool active,
  }) {
    return Column(
      children: [
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: completed
                ? brown
                : active
                    ? dark
                    : Colors.transparent,
            border: Border.all(
              color: completed
                  ? brown
                  : active
                      ? dark
                      : border,
            ),
          ),
          child: Center(
            child: Text(
              number,
              style: GoogleFonts.inter(
                fontSize: 10,
                fontWeight: FontWeight.w600,
                color: completed || active
                    ? Colors.white
                    : secondaryText,
              ),
            ),
          ),
        ),
        const SizedBox(height: 5),
        Text(
          title,
          style: GoogleFonts.inter(
            fontSize: 7,
            fontWeight: completed || active
                ? FontWeight.w600
                : FontWeight.w400,
            color: completed || active
                ? dark
                : secondaryText,
          ),
        ),
      ],
    );
  }

  Widget _buildProgress() {
    return Row(
      children: [
        _progressItem(
          number: '✓',
          title: 'Account',
          completed: true,
          active: false,
        ),
        Expanded(
          child: Container(
            height: 1,
            color: border,
          ),
        ),
        _progressItem(
          number: '✓',
          title: 'Personal',
          completed: true,
          active: false,
        ),
        Expanded(
          child: Container(
            height: 1,
            color: border,
          ),
        ),
        _progressItem(
          number: '03',
          title: 'Details',
          completed: false,
          active: true,
        ),
        Expanded(
          child: Container(
            height: 1,
            color: border,
          ),
        ),
        _progressItem(
          number: '04',
          title: 'Complete',
          completed: false,
          active: false,
        ),
      ],
    );
  }

  void _createAccount() {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          'Account creation will be connected to the backend later.',
          style: GoogleFonts.inter(),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: background,
      body: SafeArea(
        child: Column(
          children: [
            // =========================
            // HEADER
            // =========================
            Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: 20,
                vertical: 14,
              ),
              child: Row(
                children: [
                  Image.asset(
                    'assets/images/ZAYLO_LOGO_DARK.png',
                    height: 42,
                  ),
                  const Spacer(),
                  IconButton(
                    onPressed: () {
                      Navigator.pop(context);
                    },
                    icon: Icon(
                      Icons.close,
                      color: dark,
                      size: 22,
                    ),
                  ),
                ],
              ),
            ),

            // =========================
            // CONTENT
            // =========================
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(
                  24,
                  18,
                  24,
                  40,
                ),
                child: Column(
                  crossAxisAlignment:
                      CrossAxisAlignment.start,
                  children: [
                    Text(
                      'ZAYLO ACCOUNT',
                      style: GoogleFonts.inter(
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                        letterSpacing: 2,
                        color: secondaryText,
                      ),
                    ),

                    const SizedBox(height: 10),

                    Text(
                      'Create Your Account',
                      style: GoogleFonts.playfairDisplay(
                        fontSize: 34,
                        fontWeight: FontWeight.w500,
                        color: dark,
                      ),
                    ),

                    const SizedBox(height: 8),

                    Text(
                      'Join ZAYLO and experience fashion made simple.',
                      style: GoogleFonts.inter(
                        fontSize: 13,
                        color: secondaryText,
                        height: 1.5,
                      ),
                    ),

                    const SizedBox(height: 28),

                    _buildProgress(),

                    const SizedBox(height: 30),

                    // =========================
                    // FIRST + LAST NAME
                    // =========================
                    Row(
                      crossAxisAlignment:
                          CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment:
                                CrossAxisAlignment.start,
                            children: [
                              _label('First Name'),
                              const SizedBox(height: 8),
                              TextField(
                                controller:
                                    firstNameController,
                                decoration:
                                    _inputDecoration(
                                  hint: 'First name',
                                ),
                              ),
                            ],
                          ),
                        ),

                        const SizedBox(width: 12),

                        Expanded(
                          child: Column(
                            crossAxisAlignment:
                                CrossAxisAlignment.start,
                            children: [
                              _label('Last Name'),
                              const SizedBox(height: 8),
                              TextField(
                                controller:
                                    lastNameController,
                                decoration:
                                    _inputDecoration(
                                  hint: 'Last name',
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 20),

                    // =========================
                    // PHONE
                    // =========================
                    _label(
                      'Phone Number',
                      required: false,
                    ),

                    const SizedBox(height: 8),

                    TextField(
                      controller: phoneController,
                      keyboardType: TextInputType.phone,
                      decoration: _inputDecoration(
                        hint: '+63 900 000 0000',
                      ),
                    ),

                    const SizedBox(height: 20),

                    // =========================
                    // REGION
                    // =========================
                    _label('Region'),

                    const SizedBox(height: 8),

                    _dropdown(
                      hint: 'Select Region',
                      value: selectedRegion,
                      items: regions,
                      onChanged: (value) {
                        setState(() {
                          selectedRegion = value;
                          selectedProvince = null;
                          selectedCity = null;
                          selectedBarangay = null;
                        });
                      },
                    ),

                    const SizedBox(height: 20),

                    // =========================
                    // PROVINCE
                    // =========================
                    _label('Province'),

                    const SizedBox(height: 8),

                    _dropdown(
                      hint: 'Select Province',
                      value: selectedProvince,
                      items: provinces,
                      enabled: selectedRegion != null,
                      onChanged: (value) {
                        setState(() {
                          selectedProvince = value;
                          selectedCity = null;
                          selectedBarangay = null;
                        });
                      },
                    ),

                    const SizedBox(height: 20),

                    // =========================
                    // CITY
                    // =========================
                    _label('City / Municipality'),

                    const SizedBox(height: 8),

                    _dropdown(
                      hint: 'Select City / Municipality',
                      value: selectedCity,
                      items: cities,
                      enabled: selectedProvince != null,
                      onChanged: (value) {
                        setState(() {
                          selectedCity = value;
                          selectedBarangay = null;
                        });
                      },
                    ),

                    const SizedBox(height: 20),

                    // =========================
                    // BARANGAY
                    // =========================
                    _label('Barangay'),

                    const SizedBox(height: 8),

                    _dropdown(
                      hint: 'Select Barangay',
                      value: selectedBarangay,
                      items: barangays,
                      enabled: selectedCity != null,
                      onChanged: (value) {
                        setState(() {
                          selectedBarangay = value;
                        });
                      },
                    ),

                    const SizedBox(height: 20),

                    // =========================
                    // STREET
                    // =========================
                    _label(
                      'Street / House No. / Unit',
                      required: false,
                    ),

                    const SizedBox(height: 8),

                    TextField(
                      controller: streetController,
                      decoration: _inputDecoration(
                        hint: 'e.g. 123 Rizal St., Unit 4B',
                      ),
                    ),

                    const SizedBox(height: 30),

                    // =========================
                    // BUTTONS
                    // =========================
                    Row(
                      children: [
                        Expanded(
                          child: SizedBox(
                            height: 52,
                            child: OutlinedButton(
                              onPressed: () {
                                Navigator.pop(context);
                              },
                              style: OutlinedButton.styleFrom(
                                foregroundColor:
                                    secondaryText,
                                side: BorderSide(
                                  color: border,
                                ),
                                shape:
                                    RoundedRectangleBorder(
                                  borderRadius:
                                      BorderRadius.circular(4),
                                ),
                              ),
                              child: Text(
                                'Back',
                                style: GoogleFonts.inter(
                                  fontSize: 12,
                                  fontWeight:
                                      FontWeight.w500,
                                ),
                              ),
                            ),
                          ),
                        ),

                        const SizedBox(width: 12),

                        Expanded(
                          flex: 2,
                          child: SizedBox(
                            height: 52,
                            child: ElevatedButton(
                              onPressed: _createAccount,
                              style:
                                  ElevatedButton.styleFrom(
                                backgroundColor: dark,
                                foregroundColor:
                                    Colors.white,
                                elevation: 0,
                                shape:
                                    RoundedRectangleBorder(
                                  borderRadius:
                                      BorderRadius.circular(4),
                                ),
                              ),
                              child: Text(
                                'CREATE ACCOUNT',
                                style: GoogleFonts.inter(
                                  fontSize: 11,
                                  fontWeight:
                                      FontWeight.w600,
                                  letterSpacing: 1.2,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 22),

                    // =========================
                    // SIGN IN
                    // =========================
                    Center(
                      child: Wrap(
                        alignment: WrapAlignment.center,
                        children: [
                          Text(
                            'Already have an account? ',
                            style: GoogleFonts.inter(
                              fontSize: 12,
                              color: secondaryText,
                            ),
                          ),
                          GestureDetector(
                            onTap: () {
                              Navigator.popUntil(
                                context,
                                (route) => route.isFirst,
                              );
                            },
                            child: Text(
                              'Sign in',
                              style: GoogleFonts.inter(
                                fontSize: 12,
                                fontWeight:
                                    FontWeight.w600,
                                color: dark,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}