import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/features/buyer/messages/seller_inbox_screen.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// The floating chat button on Home: opens the buyer's messages with sellers,
/// with a badge for unread replies. Signed-in buyers only. With [visibility],
/// it shrinks away while that says hidden (e.g. while the page scrolls).
class SellerMessagesButton extends StatefulWidget {
  const SellerMessagesButton({super.key, this.visibility});

  final ValueListenable<bool>? visibility;

  @override
  State<SellerMessagesButton> createState() => _SellerMessagesButtonState();
}

class _SellerMessagesButtonState extends State<SellerMessagesButton> {
  int _unread = 0;
  int? _loadedForUser;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final userId = AppScope.of(context).user?.id;
    if (userId != null && userId != _loadedForUser) {
      _loadedForUser = userId;
      _loadUnread();
    }
  }

  Future<void> _loadUnread() async {
    try {
      final unread = await AppScope.read(context).api.sellerUnreadCount();
      if (mounted) setState(() => _unread = unread);
    } on ApiException {
      // The button still opens the inbox without a badge.
    }
  }

  Future<void> _open() async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const SellerInboxScreen()));
    if (mounted) await _loadUnread();
  }

  @override
  Widget build(BuildContext context) {
    final visibility = widget.visibility;
    if (visibility == null) return _button();
    // Reduced motion: appear and disappear without the animation.
    final duration = MediaQuery.disableAnimationsOf(context) ? Duration.zero : const Duration(milliseconds: 200);
    return ValueListenableBuilder<bool>(
      valueListenable: visibility,
      builder: (context, visible, child) => AnimatedScale(
        scale: visible ? 1 : 0.6,
        duration: duration,
        curve: Curves.easeOutCubic,
        child: AnimatedOpacity(
          opacity: visible ? 1 : 0,
          duration: duration,
          curve: Curves.easeOut,
          // Out of taps and the screen reader while hidden.
          child: IgnorePointer(ignoring: !visible, child: ExcludeSemantics(excluding: !visible, child: child)),
        ),
      ),
      child: _button(),
    );
  }

  Widget _button() => FloatingActionButton(
        heroTag: 'seller-messages',
        onPressed: _open,
        tooltip: _unread > 0 ? 'Messages with sellers, $_unread unread' : 'Messages with sellers',
        backgroundColor: ZayloColors.ink,
        foregroundColor: ZayloColors.white,
        child: Badge(
          isLabelVisible: _unread > 0,
          label: Text(_unread > 99 ? '99+' : '$_unread'),
          backgroundColor: Colors.redAccent,
          child: const Icon(Icons.forum_outlined),
        ),
      );
}
