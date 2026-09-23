import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'buyer_details_page.dart';

class BuyerRegistrationPage extends StatefulWidget {
  final String role;

  const BuyerRegistrationPage({
    super.key,
    this.role = 'Buyer',
  });

  @override
  State<BuyerRegistrationPage> createState() =>
      _BuyerRegistrationPageState();
}

class _BuyerRegistrationPageState
    extends State<BuyerRegistrationPage> {
  final Color background = const Color(0xFFFAF7F2);
  final Color dark = const Color(0xFF1A1714);
  final Color secondaryText = const Color(0xFF6B5F54);
  final Color border = const Color(0xFFE5DFD8);
  final Color brown = const Color(0xFFB28B6F);

  final firstNameController = TextEditingController();
  final lastNameController = TextEditingController();
  final phoneController = TextEditingController();

  @override
  void dispose() {
    firstNameController.dispose();
    lastNameController.dispose();
    phoneController.dispose();
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

  void _continueToSignIn() {
  final firstName = firstNameController.text.trim();
  final lastName = lastNameController.text.trim();
  final phone = phoneController.text.trim();

  if (firstName.isEmpty ||
      lastName.isEmpty ||
      phone.isEmpty) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'Please complete all required fields.',
        ),
      ),
    );
    return;
  }

  Navigator.push(
    context,
    MaterialPageRoute(
      builder: (_) => const BuyerDetailsPage(),
    ),
  );
}

  void _continueWithGoogle() {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'Google sign-in will be available soon.',
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
                  20,
                  24,
                  40,
                ),
                child: Column(
                  crossAxisAlignment:
                      CrossAxisAlignment.start,
                  children: [
                    // =========================
                    // LABEL
                    // =========================
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

                    // =========================
                    // TITLE
                    // =========================
                    Text(
                      'Tell Us About You',
                      style:
                          GoogleFonts.playfairDisplay(
                        fontSize: 34,
                        fontWeight: FontWeight.w500,
                        color: dark,
                      ),
                    ),

                    const SizedBox(height: 8),

                    Text(
                      'Enter your details to create your ZAYLO account.',
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
                    // FIRST NAME
                    // =========================
                    _label('First Name'),

                    const SizedBox(height: 8),

                    TextField(
                      controller: firstNameController,
                      textInputAction:
                          TextInputAction.next,
                      textCapitalization:
                          TextCapitalization.words,
                      decoration: _inputDecoration(
                        hint: 'Enter your first name',
                      ),
                    ),

                    const SizedBox(height: 20),

                    // =========================
                    // LAST NAME
                    // =========================
                    _label('Last Name'),

                    const SizedBox(height: 8),

                    TextField(
                      controller: lastNameController,
                      textInputAction:
                          TextInputAction.next,
                      textCapitalization:
                          TextCapitalization.words,
                      decoration: _inputDecoration(
                        hint: 'Enter your last name',
                      ),
                    ),

                    const SizedBox(height: 20),

                    // =========================
                    // PHONE NUMBER
                    // =========================
                    _label('Phone Number'),

                    const SizedBox(height: 8),

                    TextField(
                      controller: phoneController,
                      keyboardType:
                          TextInputType.phone,
                      textInputAction:
                          TextInputAction.done,
                      decoration: _inputDecoration(
                        hint: 'Enter your phone number',
                      ),
                    ),

                    const SizedBox(height: 30),

                    // =========================
                    // CONTINUE
                    // =========================
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: ElevatedButton(
                        onPressed: _continueToSignIn,
                        style:
                            ElevatedButton.styleFrom(
                          backgroundColor: dark,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape:
                              RoundedRectangleBorder(
                            borderRadius:
                                BorderRadius.circular(4),
                          ),
                        ),
                        child: Text(
                          'CONTINUE TO SIGN-IN DETAILS',
                          style: GoogleFonts.inter(
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                            letterSpacing: 1.2,
                          ),
                        ),
                      ),
                    ),

                    const SizedBox(height: 22),

                    // =========================
                    // OR
                    // =========================
                    Row(
                      children: [
                        Expanded(
                          child: Divider(
                            color: border,
                          ),
                        ),
                        Padding(
                          padding:
                              const EdgeInsets.symmetric(
                            horizontal: 14,
                          ),
                          child: Text(
                            'OR',
                            style: GoogleFonts.inter(
                              fontSize: 10,
                              color: secondaryText,
                              letterSpacing: 1,
                            ),
                          ),
                        ),
                        Expanded(
                          child: Divider(
                            color: border,
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 22),

                    // =========================
                    // GOOGLE
                    // =========================
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: OutlinedButton(
                        onPressed: _continueWithGoogle,
                        style:
                            OutlinedButton.styleFrom(
                          foregroundColor: dark,
                          side: BorderSide(
                            color: border,
                          ),
                          shape:
                              RoundedRectangleBorder(
                            borderRadius:
                                BorderRadius.circular(4),
                          ),
                        ),
                        child: Row(
                          mainAxisAlignment:
                              MainAxisAlignment.center,
                          children: [
                            Text(
                              'G',
                              style: GoogleFonts.inter(
                                fontSize: 19,
                                fontWeight:
                                    FontWeight.w700,
                                color:
                                    Colors.red.shade600,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Text(
                              'Continue with Google',
                              style: GoogleFonts.inter(
                                fontSize: 12,
                                fontWeight:
                                    FontWeight.w500,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),

                    const SizedBox(height: 24),

                    // =========================
                    // SIGN IN
                    // =========================
                    Center(
                      child: Wrap(
                        alignment:
                            WrapAlignment.center,
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
                              Navigator.pop(
                                context,
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
        // DETAILS - COMPLETED / CURRENT
        _buildProgressItem(
          number: '✓',
          title: 'Details',
          completed: true,
          active: false,
        ),

        Expanded(
          child: Container(
            height: 1,
            color: border,
          ),
        ),

        // SIGN IN
        _buildProgressItem(
          number: '02',
          title: 'Sign in',
          completed: false,
          active: false,
        ),

        Expanded(
          child: Container(
            height: 1,
            color: border,
          ),
        ),

        // VERIFY
        _buildProgressItem(
          number: '03',
          title: 'Verify',
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
}
