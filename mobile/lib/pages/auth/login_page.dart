import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'buyer_registration_page.dart';
import '../../services/api_service.dart';
import '../buyer/buyer_shell_page.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  bool _obscurePassword = true;
  bool _rememberMe = true;
  bool _isLoading = false;

  final TextEditingController _emailController =
      TextEditingController();

  final TextEditingController _passwordController =
      TextEditingController();

      Future<void> _login() async {
  final email = _emailController.text.trim();
  final password = _passwordController.text;

  if (email.isEmpty || password.isEmpty) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Please enter your email and password.'),
      ),
    );
    return;
  }

  setState(() {
    _isLoading = true;
  });

  try {
    final data = await ApiService.login(
      email: email,
      password: password,
    );

    if (!mounted) return;

    final token = data['token'] as String?;

    if (token == null || token.isEmpty) {
      throw Exception('Login token was not returned.');
}

if (!mounted) return;

Navigator.pushReplacement(
  context,
  MaterialPageRoute(
    builder: (_) => BuyerShellPage(token: token),
  ),
);
  } catch (e) {
    if (!mounted) return;

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          e.toString().replaceFirst('Exception: ', ''),
        ),
      ),
    );
  } finally {
    if (mounted) {
      setState(() {
        _isLoading = false;
      });
    }
  }
}

  
  static const dark = Color(0xFF1A1714);
  static const secondaryText = Color(0xFF6B5F54);
  static const borderColor = Color(0xFFE5DFD8);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,

      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(
            horizontal: 24,
            vertical: 20,
          ),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [

              // =================================================
              // TOP BAR
              // =================================================

              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [

                  // ZAYLO LOGO
                  Image.asset(
                    'assets/images/ZAYLO_LOGO_DARK.png',
                    height: 42,
                  ),

                  // CLOSE BUTTON
                  IconButton(
                    onPressed: () {
                      Navigator.pop(context);
                    },
                    icon: const Icon(
                      Icons.close,
                      size: 23,
                    ),
                    color: dark,
                  ),
                ],
              ),

              const SizedBox(height: 55),

              // =================================================
              // ACCOUNT LABEL
              // =================================================

              const Text(
                'ZAYLO ACCOUNT',
                style: TextStyle(
                  fontSize: 11,
                  letterSpacing: 2.2,
                  color: secondaryText,
                  fontWeight: FontWeight.w400,
                ),
              ),

              const SizedBox(height: 14),

              // =================================================
              // TITLE
              // =================================================

              Text(
                'Welcome Back',
                style: GoogleFonts.playfairDisplay(
                  fontSize: 38,
                  fontWeight: FontWeight.w600,
                  color: dark,
                  height: 1.1,
                ),
              ),

              const SizedBox(height: 10),

              // =================================================
              // SUBTITLE
              // =================================================

              const Text(
                'Sign in to continue to your ZAYLO experience.',
                style: TextStyle(
                  fontSize: 14,
                  height: 1.5,
                  color: secondaryText,
                ),
              ),

              const SizedBox(height: 38),

              // =================================================
              // EMAIL
              // =================================================

              const Text(
                'Email Address',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                  color: dark,
                ),
              ),

              const SizedBox(height: 9),

              TextField(
              controller: _emailController,
              keyboardType: TextInputType.emailAddress,

                decoration: InputDecoration(
                  hintText: 'Enter your email address',

                  hintStyle: const TextStyle(
                    fontSize: 13,
                    color: Color(0xFFB8B0A8),
                  ),

                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 18,
                    vertical: 16,
                  ),

                  enabledBorder: OutlineInputBorder(
                    borderSide: const BorderSide(
                      color: borderColor,
                    ),
                    borderRadius: BorderRadius.zero,
                  ),

                  focusedBorder: const OutlineInputBorder(
                    borderSide: BorderSide(
                      color: dark,
                    ),
                    borderRadius: BorderRadius.zero,
                  ),
                ),
              ),

              const SizedBox(height: 22),

              // =================================================
              // PASSWORD
              // =================================================

              const Text(
                'Password',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                  color: dark,
                ),
              ),

              const SizedBox(height: 9),

              TextField(
                controller: _passwordController,
                obscureText: _obscurePassword,

                decoration: InputDecoration(
                  hintText: 'Enter your password',

                  hintStyle: const TextStyle(
                    fontSize: 13,
                    color: Color(0xFFB8B0A8),
                  ),

                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 18,
                    vertical: 16,
                  ),

                  suffixIcon: IconButton(
                    onPressed: () {
                      setState(() {
                        _obscurePassword = !_obscurePassword;
                      });
                    },

                    icon: Icon(
                      _obscurePassword
                          ? Icons.visibility_outlined
                          : Icons.visibility_off_outlined,
                      size: 20,
                    ),

                    color: secondaryText,
                  ),

                  enabledBorder: OutlineInputBorder(
                    borderSide: const BorderSide(
                      color: borderColor,
                    ),
                    borderRadius: BorderRadius.zero,
                  ),

                  focusedBorder: const OutlineInputBorder(
                    borderSide: BorderSide(
                      color: dark,
                    ),
                    borderRadius: BorderRadius.zero,
                  ),
                ),
              ),

              const SizedBox(height: 14),

              // =================================================
              // REMEMBER ME + FORGOT PASSWORD
              // =================================================

              Row(
                children: [

                  Checkbox(
                    value: _rememberMe,

                    onChanged: (value) {
                      setState(() {
                        _rememberMe = value ?? false;
                      });
                    },

                    activeColor: dark,

                    materialTapTargetSize:
                        MaterialTapTargetSize.shrinkWrap,

                    visualDensity: VisualDensity.compact,
                  ),

                  const Text(
                    'Remember me',
                    style: TextStyle(
                      fontSize: 12,
                      color: secondaryText,
                    ),
                  ),

                  const Spacer(),

                  TextButton(
                    onPressed: () {
                      // Temporary muna
                    },

                    style: TextButton.styleFrom(
                      foregroundColor: secondaryText,
                      padding: EdgeInsets.zero,
                      minimumSize: Size.zero,
                      tapTargetSize:
                          MaterialTapTargetSize.shrinkWrap,
                    ),

                    child: const Text(
                      'Forgot Password?',
                      style: TextStyle(
                        fontSize: 12,
                      ),
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 22),

              // =================================================
              // SIGN IN BUTTON
              // =================================================

              SizedBox(
                width: double.infinity,
                height: 50,

                child: ElevatedButton(
                  onPressed: _isLoading ? null : _login,

                  style: ElevatedButton.styleFrom(
                    backgroundColor: dark,
                    foregroundColor: Colors.white,
                    elevation: 0,

                    shape: const RoundedRectangleBorder(
                      borderRadius: BorderRadius.zero,
                    ),
                  ),

                  child: const Text(
                    'SIGN IN',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      letterSpacing: 1.5,
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 30),

              // =================================================
              // OR CONTINUE WITH
              // =================================================

              Row(
                children: [

                  const Expanded(
                    child: Divider(
                      color: borderColor,
                    ),
                  ),

                  Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                    ),

                    child: const Text(
                      'OR CONTINUE WITH',
                      style: TextStyle(
                        fontSize: 10,
                        color: secondaryText,
                        letterSpacing: 0.5,
                      ),
                    ),
                  ),

                  const Expanded(
                    child: Divider(
                      color: borderColor,
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 22),

              // =================================================
              // GOOGLE BUTTON
              // =================================================

              SizedBox(
                width: double.infinity,
                height: 50,

                child: OutlinedButton(
                  onPressed: () {
                    // Google login later
                  },

                  style: OutlinedButton.styleFrom(
                    foregroundColor: dark,

                    side: const BorderSide(
                      color: borderColor,
                    ),

                    shape: const RoundedRectangleBorder(
                      borderRadius: BorderRadius.zero,
                    ),
                  ),

                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [

                      Text(
                        'G',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w600,
                          color: Colors.red.shade600,
                        ),
                      ),

                      const SizedBox(width: 12),

                      const Text(
                        'Google',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 28),

              // =================================================
              // REGISTER LINK
              // =================================================

              Center(
                child: Wrap(
                  alignment: WrapAlignment.center,
                  children: [

                    const Text(
                      "Don't have an account? ",
                      style: TextStyle(
                        fontSize: 12,
                        color: secondaryText,
                      ),
                    ),

                    GestureDetector(
                      onTap: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const BuyerRegistrationPage(),
                          ),
                        );
                      },

                      child: const Text(
                        'Create an account',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: dark,
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 30),
            ],
          ),
        ),
      ),
    );
  }
}