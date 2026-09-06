import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'buyer_registration_page.dart';

class RegisterPage extends StatefulWidget {
  const RegisterPage({super.key});

  @override
  State<RegisterPage> createState() => _RegisterPageState();
}

class _RegisterPageState extends State<RegisterPage> {
  final Color background = const Color(0xFFFAF7F2);
  final Color dark = const Color(0xFF1A1714);
  final Color secondaryText = const Color(0xFF6B5F54);
  final Color border = const Color(0xFFE5DFD8);
  final Color brown = const Color(0xFFB28B6F);

  String selectedRole = 'Buyer';

  final List<Map<String, dynamic>> roles = [
    {
      'name': 'Buyer',
      'description': 'Shop for luxury fashion items',
      'icon': Icons.person_outline,
    },
    {
      'name': 'Seller',
      'description': 'List and sell your fashion products',
      'icon': Icons.storefront_outlined,
    },
    {
      'name': 'Courier / Rider',
      'description': 'Deliver orders and earn commissions',
      'icon': Icons.two_wheeler_outlined,
    },
  ];

        void _continue() {
          if (selectedRole == 'Buyer') {
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (context) => const BuyerRegistrationPage(),
              ),
            );
          } else {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(
                  '$selectedRole registration is next.',
                  style: GoogleFonts.inter(),
                ),
              ),
            );
          }
        }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: background,
      body: SafeArea(
        child: Column(
          children: [
            // =========================
            // TOP BAR
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
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // LABEL
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

                    // TITLE
                    Text(
                      'Create Your Account',
                      style: GoogleFonts.playfairDisplay(
                        fontSize: 34,
                        fontWeight: FontWeight.w500,
                        color: dark,
                      ),
                    ),

                    const SizedBox(height: 8),

                    // SUBTITLE
                    Text(
                      'Join ZAYLO and experience fashion made simple.',
                      style: GoogleFonts.inter(
                        fontSize: 13,
                        color: secondaryText,
                        height: 1.5,
                      ),
                    ),

                    const SizedBox(height: 28),

                    // =========================
                    // PROGRESS INDICATOR
                    // =========================
                    Row(
                      children: [
                        _buildStep(
                          number: '01',
                          title: 'Account',
                          active: true,
                        ),

                        Expanded(
                          child: Container(
                            height: 1,
                            color: border,
                          ),
                        ),

                        _buildStep(
                          number: '02',
                          title: 'Personal',
                          active: false,
                        ),

                        Expanded(
                          child: Container(
                            height: 1,
                            color: border,
                          ),
                        ),

                        _buildStep(
                          number: '03',
                          title: 'Details',
                          active: false,
                        ),

                        Expanded(
                          child: Container(
                            height: 1,
                            color: border,
                          ),
                        ),

                        _buildStep(
                          number: '04',
                          title: 'Complete',
                          active: false,
                        ),
                      ],
                    ),

                    const SizedBox(height: 32),

                    // =========================
                    // ROLE SELECTION
                    // =========================
                    ...roles.map(
                      (role) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _buildRoleCard(
                          name: role['name'],
                          description: role['description'],
                          icon: role['icon'],
                        ),
                      ),
                    ),

                    const SizedBox(height: 10),

                    // =========================
                    // CONTINUE BUTTON
                    // =========================
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: ElevatedButton(
                        onPressed: _continue,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: dark,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: const RoundedRectangleBorder(
                            borderRadius: BorderRadius.zero,
                          ),
                        ),
                        child: Text(
                          'CONTINUE',
                          style: GoogleFonts.inter(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            letterSpacing: 1.5,
                          ),
                        ),
                      ),
                    ),

                    const SizedBox(height: 22),

                    // =========================
                    // LOGIN LINK
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
                              Navigator.pop(context);
                            },
                            child: Text(
                              'Sign in',
                              style: GoogleFonts.inter(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
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
  // STEP WIDGET
  // =========================

  Widget _buildStep({
    required String number,
    required String title,
    required bool active,
  }) {
    return Column(
      children: [
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: active ? dark : Colors.transparent,
            border: Border.all(
              color: active ? dark : border,
            ),
          ),
          child: Center(
            child: Text(
              number,
              style: GoogleFonts.inter(
                fontSize: 10,
                fontWeight: FontWeight.w600,
                color: active ? Colors.white : secondaryText,
              ),
            ),
          ),
        ),

        const SizedBox(height: 5),

        Text(
          title,
          style: GoogleFonts.inter(
            fontSize: 8,
            fontWeight: active ? FontWeight.w600 : FontWeight.w400,
            color: active ? dark : secondaryText,
          ),
        ),
      ],
    );
  }

  // =========================
  // ROLE CARD
  // =========================

  Widget _buildRoleCard({
    required String name,
    required String description,
    required IconData icon,
  }) {
    final bool isSelected = selectedRole == name;

    return GestureDetector(
      onTap: () {
        setState(() {
          selectedRole = name;
        });
      },
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFF8F4EF) : Colors.white,
          border: Border.all(
            color: isSelected ? dark : border,
            width: isSelected ? 1.2 : 1,
          ),
        ),
        child: Row(
          children: [
            // ICON
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: isSelected
                    ? dark
                    : const Color(0xFFF5F1EB),
              ),
              child: Icon(
                icon,
                size: 22,
                color: isSelected ? Colors.white : secondaryText,
              ),
            ),

            const SizedBox(width: 14),

            // TEXT
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    style: GoogleFonts.inter(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: dark,
                    ),
                  ),

                  const SizedBox(height: 4),

                  Text(
                    description,
                    style: GoogleFonts.inter(
                      fontSize: 11,
                      color: secondaryText,
                    ),
                  ),
                ],
              ),
            ),

            // CHECK
            if (isSelected)
              Container(
                width: 24,
                height: 24,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: brown,
                ),
                child: const Icon(
                  Icons.check,
                  size: 15,
                  color: Colors.white,
                ),
              ),
          ],
        ),
      ),
    );
  }
}