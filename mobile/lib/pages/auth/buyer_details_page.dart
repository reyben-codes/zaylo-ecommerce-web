import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'buyer_verification_page.dart';


class BuyerDetailsPage extends StatefulWidget {
  const BuyerDetailsPage({super.key});

  @override
  State<BuyerDetailsPage> createState() => _BuyerDetailsPageState();
}

class _BuyerDetailsPageState extends State<BuyerDetailsPage> {
  static const background = Color(0xFFFAF7F2);
  static const dark = Color(0xFF1A1714);
  static const secondaryText = Color(0xFF6B5F54);
  static const border = Color(0xFFE5DFD8);
  static const brown = Color(0xFFB28B6F);

  final TextEditingController emailController =
      TextEditingController();

  final TextEditingController passwordController =
      TextEditingController();

  final TextEditingController confirmPasswordController =
      TextEditingController();

  bool obscurePassword = true;
  bool obscureConfirmPassword = true;

  @override
  void dispose() {
    emailController.dispose();
    passwordController.dispose();
    confirmPasswordController.dispose();
    super.dispose();
  }

  InputDecoration _inputDecoration({
    required String hint,
    Widget? suffixIcon,
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
      suffixIcon: suffixIcon,
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

  void _sendVerificationCode() {
  final email = emailController.text.trim();
  final password = passwordController.text;
  final confirmPassword =
      confirmPasswordController.text;

  if (email.isEmpty ||
      password.isEmpty ||
      confirmPassword.isEmpty) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'Please complete all required fields.',
        ),
      ),
    );
    return;
  }

  if (!email.contains('@')) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'Please enter a valid email address.',
        ),
      ),
    );
    return;
  }

  if (password.length < 8) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'Password must be at least 8 characters.',
        ),
      ),
    );
    return;
  }

  if (password != confirmPassword) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'Passwords do not match.',
        ),
      ),
    );
    return;
  }

  Navigator.push(
    context,
    MaterialPageRoute(
      builder: (_) => const BuyerVerificationPage(),
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
                      'Set Up Your Sign-In',
                      style: GoogleFonts.playfairDisplay(
                        fontSize: 34,
                        fontWeight: FontWeight.w500,
                        color: dark,
                      ),
                    ),

                    const SizedBox(height: 8),

                    Text(
                      'Create the email and password you will use to access your ZAYLO account.',
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

                    // EMAIL
                    _label('Email Address'),

                    const SizedBox(height: 8),

                    TextField(
                      controller: emailController,
                      keyboardType:
                          TextInputType.emailAddress,
                      textInputAction:
                          TextInputAction.next,
                      decoration: _inputDecoration(
                        hint: 'your@email.com',
                      ),
                    ),

                    const SizedBox(height: 20),

                    // PASSWORD
                    _label('Password'),

                    const SizedBox(height: 8),

                    TextField(
                      controller: passwordController,
                      obscureText: obscurePassword,
                      textInputAction:
                          TextInputAction.next,
                      decoration: _inputDecoration(
                        hint: 'Create a password',
                        suffixIcon: IconButton(
                          onPressed: () {
                            setState(() {
                              obscurePassword =
                                  !obscurePassword;
                            });
                          },
                          icon: Icon(
                            obscurePassword
                                ? Icons.visibility_outlined
                                : Icons.visibility_off_outlined,
                            color: secondaryText,
                            size: 20,
                          ),
                        ),
                      ),
                    ),

                    const SizedBox(height: 8),

                    Text(
                      'Use at least 8 characters.',
                      style: GoogleFonts.inter(
                        fontSize: 11,
                        color: secondaryText,
                      ),
                    ),

                    const SizedBox(height: 20),

                    // CONFIRM PASSWORD
                    _label('Confirm Password'),

                    const SizedBox(height: 8),

                    TextField(
                      controller:
                          confirmPasswordController,
                      obscureText:
                          obscureConfirmPassword,
                      textInputAction:
                          TextInputAction.done,
                      decoration: _inputDecoration(
                        hint: 'Repeat your password',
                        suffixIcon: IconButton(
                          onPressed: () {
                            setState(() {
                              obscureConfirmPassword =
                                  !obscureConfirmPassword;
                            });
                          },
                          icon: Icon(
                            obscureConfirmPassword
                                ? Icons.visibility_outlined
                                : Icons.visibility_off_outlined,
                            color: secondaryText,
                            size: 20,
                          ),
                        ),
                      ),
                    ),

                    const SizedBox(height: 12),

                    // VERIFICATION INFORMATION
                    Text(
                      "We'll send a six-digit verification code to this email address. "
                      "You'll need the code to finish creating your ZAYLO account.",
                      style: GoogleFonts.inter(
                        fontSize: 11,
                        color: secondaryText,
                        height: 1.5,
                      ),
                    ),

                    const SizedBox(height: 28),

                    // BUTTONS
                    Row(
                      children: [
                        Expanded(
                          child: SizedBox(
                            height: 52,
                            child: OutlinedButton(
                              onPressed: () {
                                Navigator.pop(context);
                              },
                              style:
                                  OutlinedButton.styleFrom(
                                foregroundColor:
                                    secondaryText,
                                side: const BorderSide(
                                  color: border,
                                ),
                                shape:
                                    RoundedRectangleBorder(
                                  borderRadius:
                                      BorderRadius.circular(4),
                                ),
                              ),
                              child: Text(
                                'BACK',
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

                        const SizedBox(width: 12),

                        Expanded(
                          flex: 2,
                          child: SizedBox(
                            height: 52,
                            child: ElevatedButton(
                              onPressed:
                                  _sendVerificationCode,
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
                                'SEND VERIFICATION CODE',
                                style: GoogleFonts.inter(
                                  fontSize: 9.5,
                                  fontWeight:
                                      FontWeight.w600,
                                  letterSpacing: 1.1,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 26),

                    // OR DIVIDER
                    Row(
                      children: [
                        const Expanded(
                          child: Divider(
                            color: border,
                            thickness: 1,
                          ),
                        ),
                        Padding(
                          padding:
                              const EdgeInsets.symmetric(
                            horizontal: 16,
                          ),
                          child: Text(
                            'OR',
                            style: GoogleFonts.inter(
                              fontSize: 10,
                              fontWeight: FontWeight.w500,
                              color: secondaryText,
                              letterSpacing: 1,
                            ),
                          ),
                        ),
                        const Expanded(
                          child: Divider(
                            color: border,
                            thickness: 1,
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 20),

                    // GOOGLE
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: OutlinedButton(
                        onPressed: _continueWithGoogle,
                        style: OutlinedButton.styleFrom(
                          foregroundColor: dark,
                          backgroundColor: Colors.white,
                          side: const BorderSide(
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
                                    FontWeight.w600,
                                color: Colors.red.shade600,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Text(
                              'Continue with Google',
                              style: GoogleFonts.inter(
                                fontSize: 13,
                                fontWeight:
                                    FontWeight.w500,
                                color: dark,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),

                    const SizedBox(height: 22),

                    // SIGN IN
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

  Widget _buildProgress() {
    return Row(
      children: [
        // STEP 1
        _buildProgressItem(
          number: '✓',
          title: 'Details',
          completed: true,
          active: false,
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
          active: false,
        ),

        Expanded(
          child: Container(
            height: 1,
            color: border,
          ),
        ),

        // STEP 3
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
