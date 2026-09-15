import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class SellerDetailsPage extends StatefulWidget {
  const SellerDetailsPage({super.key});

  @override
  State<SellerDetailsPage> createState() => _SellerDetailsPageState();
}

class _SellerDetailsPageState extends State<SellerDetailsPage> {
  final Color background = const Color(0xFFFAF7F2);
  final Color dark = const Color(0xFF1A1714);
  final Color secondaryText = const Color(0xFF6B5F54);
  final Color border = const Color(0xFFE5DFD8);
  final Color brown = const Color(0xFFB28B6F);

  final storeNameController = TextEditingController();
  final fullNameController = TextEditingController();
  final phoneController = TextEditingController();
  final addressController = TextEditingController();

  @override
  void dispose() {
    storeNameController.dispose();
    fullNameController.dispose();
    phoneController.dispose();
    addressController.dispose();
    super.dispose();
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
        vertical: 16,
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

  Widget _label(String text) {
    return RichText(
      text: TextSpan(
        children: [
          TextSpan(
            text: text,
            style: GoogleFonts.inter(
              fontSize: 12,
              fontWeight: FontWeight.w500,
              color: dark,
            ),
          ),
          TextSpan(
            text: ' *',
            style: GoogleFonts.inter(
              fontSize: 12,
              color: const Color(0xFF9A4D3F),
            ),
          ),
        ],
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
                  20,
                  24,
                  40,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'SELLER ACCOUNT',
                      style: GoogleFonts.inter(
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                        letterSpacing: 2,
                        color: secondaryText,
                      ),
                    ),

                    const SizedBox(height: 10),

                    Text(
                      'Seller Details',
                      style: GoogleFonts.playfairDisplay(
                        fontSize: 34,
                        fontWeight: FontWeight.w500,
                        color: dark,
                      ),
                    ),

                    const SizedBox(height: 8),

                    Text(
                      'Tell us more about you and your store.',
                      style: GoogleFonts.inter(
                        fontSize: 13,
                        color: secondaryText,
                        height: 1.5,
                      ),
                    ),

                    const SizedBox(height: 28),

                    // =========================
                    // PROGRESS
                    // =========================
                    _buildProgress(),

                    const SizedBox(height: 32),

                    // =========================
                    // STORE NAME
                    // =========================
                    _label('Store Name'),

                    const SizedBox(height: 8),

                    TextField(
                      controller: storeNameController,
                      textInputAction: TextInputAction.next,
                      decoration: _inputDecoration(
                        hint: 'Enter your store name',
                      ),
                    ),

                    const SizedBox(height: 20),

                    // =========================
                    // FULL NAME
                    // =========================
                    _label('Full Name'),

                    const SizedBox(height: 8),

                    TextField(
                      controller: fullNameController,
                      textInputAction: TextInputAction.next,
                      decoration: _inputDecoration(
                        hint: 'Enter your full name',
                      ),
                    ),

                    const SizedBox(height: 20),

                    // =========================
                    // PHONE
                    // =========================
                    _label('Phone Number'),

                    const SizedBox(height: 8),

                    TextField(
                      controller: phoneController,
                      keyboardType: TextInputType.phone,
                      textInputAction: TextInputAction.next,
                      decoration: _inputDecoration(
                        hint: '+63 9XX XXX XXXX',
                      ),
                    ),

                    const SizedBox(height: 20),

                    // =========================
                    // ADDRESS
                    // =========================
                    _label('Business Address'),

                    const SizedBox(height: 8),

                    TextField(
                      controller: addressController,
                      maxLines: 3,
                      textInputAction: TextInputAction.done,
                      decoration: _inputDecoration(
                        hint: 'Enter your business address',
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
                                foregroundColor: secondaryText,
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
                              onPressed: () {
                                // Next step will be Seller Complete Page
                                ScaffoldMessenger.of(context)
                                    .showSnackBar(
                                  const SnackBar(
                                    content: Text(
                                      'Seller details saved.',
                                    ),
                                  ),
                                );
                              },
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
                                'CONTINUE',
                                style: GoogleFonts.inter(
                                  fontSize: 11,
                                  fontWeight:
                                      FontWeight.w600,
                                  letterSpacing: 1.5,
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

  // =========================
  // PROGRESS INDICATOR
  // =========================

  Widget _buildProgress() {
    return Row(
      children: [
        _buildProgressItem(
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

        _buildProgressItem(
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

        _buildProgressItem(
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

        _buildProgressItem(
          number: '04',
          title: 'Complete',
          completed: false,
          active: false,
        ),
      ],
    );
  }

  Widget _buildProgressItem({
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
            fontWeight: active || completed
                ? FontWeight.w600
                : FontWeight.w400,
            color: active || completed
                ? dark
                : secondaryText,
          ),
        ),
      ],
    );
  }
}