import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'login_page.dart';

class BuyerVerificationPage extends StatefulWidget {
  const BuyerVerificationPage({super.key});

  @override
  State<BuyerVerificationPage> createState() =>
      _BuyerVerificationPageState();
}

class _BuyerVerificationPageState
    extends State<BuyerVerificationPage> {
  static const background = Color(0xFFFAF7F2);
  static const dark = Color(0xFF1A1714);
  static const secondaryText = Color(0xFF6B5F54);
  static const border = Color(0xFFE5DFD8);
  static const brown = Color(0xFFB28B6F);

  final TextEditingController codeController =
      TextEditingController();

  @override
  void dispose() {
    codeController.dispose();
    super.dispose();
  }

void _verifyEmail() {
  final code = codeController.text.trim();

  if (code.isEmpty) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'Please enter your six-digit verification code.',
        ),
      ),
    );
    return;
  }

  if (code.length != 6) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'Please enter all six digits of your verification code.',
        ),
      ),
    );
    return;
  }

  // Verification successful
  Navigator.pushAndRemoveUntil(
    context,
    MaterialPageRoute(
      builder: (_) => const LoginPage(),
    ),
    (route) => false,
  );
}

  void _resendCode() {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'A new verification code will be sent to your email.',
        ),
      ),
    );
  }

  InputDecoration _inputDecoration() {
    return InputDecoration(
      hintText: '0  0  0  0  0  0',
      hintStyle: GoogleFonts.inter(
        fontSize: 20,
        fontWeight: FontWeight.w500,
        letterSpacing: 3,
        color: const Color(0xFFB8AEA5),
      ),
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(
        horizontal: 18,
        vertical: 18,
      ),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(4),
        borderSide: const BorderSide(
          color: border,
        ),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(4),
        borderSide: const BorderSide(
          color: border,
        ),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(4),
        borderSide: const BorderSide(
          color: dark,
          width: 1.2,
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
            // HEADER
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
                    icon: const Icon(
                      Icons.close,
                      color: dark,
                      size: 22,
                    ),
                  ),
                ],
              ),
            ),

            // CONTENT
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
                      'Verify Your Email',
                      style: GoogleFonts.playfairDisplay(
                        fontSize: 34,
                        fontWeight: FontWeight.w500,
                        color: dark,
                      ),
                    ),

                    const SizedBox(height: 8),

                    Text(
                      'Confirm your email address to finish creating your ZAYLO account.',
                      style: GoogleFonts.inter(
                        fontSize: 13,
                        color: secondaryText,
                        height: 1.5,
                      ),
                    ),

                    const SizedBox(height: 28),

                    // PROGRESS
                    _buildProgress(),

                    const SizedBox(height: 32),

                    // VERIFICATION SENT
                    Text(
                      'Your verification code has been sent.',
                      style: GoogleFonts.inter(
                        fontSize: 14,
                        fontWeight: FontWeight.w500,
                        color: dark,
                        height: 1.5,
                      ),
                    ),

                    const SizedBox(height: 24),

                    // CODE LABEL
                    Text(
                      'Email verification code',
                      style: GoogleFonts.inter(
                        fontSize: 12,
                        fontWeight: FontWeight.w500,
                        color: dark,
                      ),
                    ),

                    const SizedBox(height: 8),

                    // CODE INPUT
                    TextField(
                      controller: codeController,
                      keyboardType: TextInputType.number,
                      textInputAction:
                          TextInputAction.done,
                      maxLength: 6,
                      textAlign: TextAlign.center,
                      style: GoogleFonts.inter(
                        fontSize: 20,
                        fontWeight: FontWeight.w600,
                        letterSpacing: 7,
                        color: dark,
                      ),
                      decoration: _inputDecoration().copyWith(
                        counterText: '',
                      ),
                    ),

                    const SizedBox(height: 10),

                    // CODE INFORMATION
                    Text(
                      'Your code expires after 10 minutes. '
                      'You can paste all six digits here.',
                      style: GoogleFonts.inter(
                        fontSize: 11,
                        color: secondaryText,
                        height: 1.5,
                      ),
                    ),

                    const SizedBox(height: 28),

                    // VERIFY BUTTON
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: ElevatedButton(
                        onPressed: _verifyEmail,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: dark,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius:
                                BorderRadius.circular(4),
                          ),
                        ),
                        child: Text(
                          'VERIFY EMAIL & CONTINUE',
                          style: GoogleFonts.inter(
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                            letterSpacing: 1.3,
                          ),
                        ),
                      ),
                    ),

                    const SizedBox(height: 24),

                    // RESEND INFORMATION
                    Text(
                      "Didn't receive a code? Check your spam folder "
                      "or request another. Please wait one minute "
                      "between requests.",
                      style: GoogleFonts.inter(
                        fontSize: 11,
                        color: secondaryText,
                        height: 1.6,
                      ),
                    ),

                    const SizedBox(height: 16),

                    // RESEND BUTTON
                    SizedBox(
                      width: double.infinity,
                      height: 48,
                      child: OutlinedButton(
                        onPressed: _resendCode,
                        style: OutlinedButton.styleFrom(
                          foregroundColor: dark,
                          backgroundColor: Colors.white,
                          side: const BorderSide(
                            color: border,
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius:
                                BorderRadius.circular(4),
                          ),
                        ),
                        child: Text(
                          'RESEND CODE',
                          style: GoogleFonts.inter(
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                            letterSpacing: 1.3,
                          ),
                        ),
                      ),
                    ),

                    const SizedBox(height: 22),

                    // BACK
                    Center(
                      child: GestureDetector(
                        onTap: () {
                          Navigator.pop(context);
                        },
                        child: Text(
                          'Back to sign-in details',
                          style: GoogleFonts.inter(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: dark,
                          ),
                        ),
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

  Widget _buildProgress() {
    return Row(
      children: [
        // STEP 1
        _buildProgressItem(
          number: '✓',
          title: 'Details',
          completed: true,
        ),

        Expanded(
          child: Container(
            height: 1,
            color: brown,
          ),
        ),

        // STEP 2
        _buildProgressItem(
          number: '✓',
          title: 'Sign in',
          completed: true,
        ),

        Expanded(
          child: Container(
            height: 1,
            color: brown,
          ),
        ),

        // STEP 3
        _buildProgressItem(
          number: '✓',
          title: 'Verify',
          completed: true,
        ),
      ],
    );
  }

  Widget _buildProgressItem({
    required String number,
    required String title,
    required bool completed,
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
                : Colors.transparent,
            border: Border.all(
              color: completed
                  ? brown
                  : border,
            ),
          ),
          child: Center(
            child: Text(
              number,
              style: GoogleFonts.inter(
                fontSize: 10,
                fontWeight: FontWeight.w600,
                color: completed
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
            fontWeight: completed
                ? FontWeight.w600
                : FontWeight.w400,
            color: completed
                ? dark
                : secondaryText,
          ),
        ),
      ],
    );
  }
}
