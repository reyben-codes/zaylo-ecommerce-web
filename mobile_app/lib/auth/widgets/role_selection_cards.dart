import 'package:flutter/material.dart';

import 'package:zaylo_mobile/auth/buyer/login_screen.dart';
import 'package:zaylo_mobile/auth/buyer/registration_screen.dart';
import 'package:zaylo_mobile/auth/rider/rider_login_screen.dart';
import 'package:zaylo_mobile/auth/rider/rider_registration_screen.dart';
import 'package:zaylo_mobile/auth/widgets/auth_layout.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

enum AuthRole { buyer, rider }

/// Squared selection cards displayed horizontally at the top of authentication screens.
/// Allows the user to switch between Buyer and Rider accounts with one tap.
class AuthRoleCards extends StatelessWidget {
  const AuthRoleCards({
    super.key,
    required this.selectedRole,
    required this.isLogin,
  });

  final AuthRole selectedRole;
  final bool isLogin;

  void _onSelect(BuildContext context, AuthRole role) {
    if (role == selectedRole) return;
    Widget target;
    if (isLogin) {
      target = switch (role) {
        AuthRole.buyer => const LoginScreen(),
        AuthRole.rider => const RiderLoginScreen(),
      };
    } else {
      target = switch (role) {
        AuthRole.buyer => const RegistrationScreen(),
        AuthRole.rider => const RiderRegistrationScreen(),
      };
    }
    Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => target));
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            isLogin ? 'Sign in as' : 'Create account for',
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, letterSpacing: 0.5, color: Colors.grey),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _RoleSquareCard(
                  title: 'Buyer',
                  icon: Icons.shopping_bag_outlined,
                  isSelected: selectedRole == AuthRole.buyer,
                  onTap: () => _onSelect(context, AuthRole.buyer),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _RoleSquareCard(
                  title: 'Rider',
                  icon: Icons.two_wheeler_outlined,
                  isSelected: selectedRole == AuthRole.rider,
                  onTap: () => _onSelect(context, AuthRole.rider),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _RoleSquareCard extends StatelessWidget {
  const _RoleSquareCard({
    required this.title,
    required this.icon,
    required this.isSelected,
    required this.onTap,
  });

  final String title;
  final IconData icon;
  final bool isSelected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final activeBg = ZayloColors.ink;
    final activeFg = ZayloColors.white;
    final inactiveBg = ZayloColors.sand.withValues(alpha: 0.35);
    final inactiveFg = ZayloColors.ink;

    return Material(
      color: isSelected ? activeBg : inactiveBg,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: isSelected ? ZayloColors.ink : ZayloColors.line,
              width: isSelected ? 2 : 1,
            ),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 24, color: isSelected ? activeFg : inactiveFg),
              const SizedBox(height: 6),
              Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontWeight: isSelected ? FontWeight.w700 : FontWeight.w600,
                  fontSize: 13,
                  color: isSelected ? activeFg : inactiveFg,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Dedicated selection cards screen for choosing which account to create or sign in to.
class AccountTypeSelectionScreen extends StatelessWidget {
  const AccountTypeSelectionScreen({super.key, required this.isLogin});

  final bool isLogin;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return AuthScaffold(
      children: [
        AuthMasthead(registering: !isLogin),
        const SizedBox(height: 32),
        Eyebrow(isLogin ? 'ZAYLO PORTAL' : 'JOIN ZAYLO'),
        const SizedBox(height: 9),
        Text(
          isLogin ? 'Sign In to ZAYLO' : 'Create New Account',
          style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 7),
        Text(
          isLogin
              ? 'Select your account type to continue to the sign in portal:'
              : 'How will you use ZAYLO? Select which account you would like to create:',
          style: theme.textTheme.bodyMedium,
        ),
        const SizedBox(height: 24),
        _SelectionOptionCard(
          icon: Icons.shopping_bag_outlined,
          title: 'Buyer',
          subtitle: isLogin
              ? 'Sign in to shop everyday essentials, track orders, and view your favorites.'
              : 'Shop everyday essentials, save your favorites, and track your parcel deliveries.',
          onTap: () {
            Navigator.of(context).pushReplacement(
              MaterialPageRoute(builder: (_) => isLogin ? const LoginScreen() : const RegistrationScreen()),
            );
          },
        ),
        const SizedBox(height: 14),
        _SelectionOptionCard(
          icon: Icons.two_wheeler_outlined,
          title: 'Delivery Rider',
          subtitle: isLogin
              ? 'Sign in to deliver parcels to buyers, scan orders, and track your wallet.'
              : 'Apply to deliver packages to buyers with your vehicle and earn commissions.',
          onTap: () {
            Navigator.of(context).pushReplacement(
              MaterialPageRoute(
                builder: (_) => isLogin
                    ? const RiderLoginScreen()
                    : const RiderRegistrationScreen(),
              ),
            );
          },
        ),
        const SizedBox(height: 28),
        Center(
          child: TextButton(
            onPressed: () {
              Navigator.of(context).pushReplacement(
                MaterialPageRoute(
                  builder: (_) => AccountTypeSelectionScreen(isLogin: !isLogin),
                ),
              );
            },
            child: Text(
              isLogin
                  ? "Don't have an account? Create new account"
                  : 'Already have an account? Sign in',
            ),
          ),
        ),
      ],
    );
  }
}

class _SelectionOptionCard extends StatelessWidget {
  const _SelectionOptionCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Material(
      color: ZayloColors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: ZayloColors.line, width: 1.5),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.03),
                blurRadius: 8,
                offset: const Offset(0, 3),
              ),
            ],
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: ZayloColors.sand,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: ZayloColors.ink, size: 24),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: ZayloColors.ink,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontSize: 13,
                        color: ZayloColors.muted,
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              const Padding(
                padding: EdgeInsets.only(top: 12),
                child: Icon(Icons.arrow_forward_ios, size: 14, color: Colors.grey),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
