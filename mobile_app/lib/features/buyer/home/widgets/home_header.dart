import 'dart:async';

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/components/theme_toggle.dart';
import 'package:zaylo_mobile/features/buyer/account/notification_bell_button.dart';
import 'package:zaylo_mobile/features/buyer/messages/seller_inbox_screen.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

class HomeTopBar extends StatelessWidget {
  const HomeTopBar({super.key});

  /// The bar floats over the page, which reserves this much space for it.
  static const height = 70.0;

  /// Narrower than this, the centred logo leaves too little room for three icons on the right, so the theme
  /// toggle stays in Account > Settings only.
  static const _roomyWidth = 400.0;

  @override
  Widget build(BuildContext context) => Container(
        height: height,
        color: ZayloColors.cream,
        padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 10, ZayloSpace.page, 12),
        // Equal-width sides keep the logo centred.
        child: Row(children: [
          Expanded(
            child: Align(
              alignment: Alignment.centerLeft,
              child: IconButton(
                tooltip: 'Menu',
                onPressed: () => Scaffold.of(context).openDrawer(),
                icon: const Icon(Icons.menu_rounded),
              ),
            ),
          ),
          Image.asset(ZayloAssets.logo,
              height: 38, fit: BoxFit.contain, color: ZayloAssets.logoTint, colorBlendMode: BlendMode.srcIn),
          Expanded(
            child: Row(mainAxisAlignment: MainAxisAlignment.end, children: [
              if (MediaQuery.sizeOf(context).width >= _roomyWidth) const ThemeToggleButton(),
              // Messages with sellers (the floating chat head opens them too); the bag is in the bottom nav bar.
              const _MessagesButton(),
              const NotificationBellButton(),
            ]),
          ),
        ]),
      );
}

/// The home hero, following the web storefront's (resources/views/index.blade.php)
/// copy, overlay and dots. Images can be swiped in either direction and wrap
/// around; they also advance every 3.5 seconds. Touching the hero or a dot
/// restarts the countdown. Rotation pauses while the app is in the background
/// and stays off when the device asks for reduced motion.
class HomeHeroCarousel extends StatefulWidget {
  const HomeHeroCarousel({super.key, required this.images, required this.onShop, required this.onNew});

  final List<String> images;
  final VoidCallback onShop;
  final VoidCallback onNew;

  static const interval = Duration(milliseconds: 3500);

  @override
  State<HomeHeroCarousel> createState() => _HomeHeroCarouselState();
}

class _HomeHeroCarouselState extends State<HomeHeroCarousel> {
  /// A landscape banner: 16:10 of the screen width.
  static const _aspectRatio = 16 / 10;
  static const _slide = Duration(milliseconds: 600);

  /// Pages start far from zero so the first swipe back can wrap around.
  static const _loops = 1000;

  late final AppLifecycleListener _lifecycle;
  late PageController _pages;
  Timer? _timer;
  int _current = 0;
  bool _foreground = true;
  bool _dragging = false;
  bool _reducedMotion = false;

  int get _count => widget.images.length;

  @override
  void initState() {
    super.initState();
    _pages = PageController(initialPage: _count * _loops);
    _lifecycle = AppLifecycleListener(
      onShow: () {
        _foreground = true;
        _startRotation();
      },
      onHide: () {
        _foreground = false;
        _stopRotation();
      },
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _reducedMotion = MediaQuery.disableAnimationsOf(context);
    _reducedMotion ? _stopRotation() : _startRotation();
  }

  @override
  void didUpdateWidget(HomeHeroCarousel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (_count != oldWidget.images.length) {
      _current = _count == 0 ? 0 : _current % _count;
      _pages.dispose();
      _pages = PageController(initialPage: _count * _loops + _current);
      _stopRotation();
      _startRotation();
    }
  }

  @override
  void dispose() {
    _stopRotation();
    _lifecycle.dispose();
    _pages.dispose();
    super.dispose();
  }

  void _startRotation() {
    if (_timer != null || _reducedMotion || !_foreground || _dragging || _count < 2) return;
    _timer = Timer.periodic(HomeHeroCarousel.interval, (_) {
      if (_pages.hasClients) _pages.nextPage(duration: _slide, curve: Curves.easeInOut);
    });
  }

  void _stopRotation() {
    _timer?.cancel();
    _timer = null;
  }

  void _showImage(int index) {
    if (!_pages.hasClients) return;
    final page = _pages.page!.round() - _current + index;
    _reducedMotion ? _pages.jumpToPage(page) : _pages.animateToPage(page, duration: _slide, curve: Curves.easeInOut);
    _stopRotation();
    _startRotation();
  }

  /// Holds rotation while a finger is on the hero, then restarts the countdown.
  bool _onScroll(ScrollNotification notification) {
    if (notification is ScrollStartNotification && notification.dragDetails != null) {
      _dragging = true;
      _stopRotation();
    } else if (notification is ScrollEndNotification && _dragging) {
      _dragging = false;
      _startRotation();
    }
    return false;
  }

  @override
  Widget build(BuildContext context) => LayoutBuilder(builder: (context, constraints) {
        final width = constraints.maxWidth;
        final compact = width < 360;
        // The landscape height is a floor, so narrow phones and large
        // accessibility text grow the hero instead of clipping the copy.
        return ConstrainedBox(
          constraints: BoxConstraints(minWidth: width, minHeight: width / _aspectRatio),
          child: ColoredBox(
            color: ZayloColors.sand,
            child: Stack(alignment: Alignment.centerLeft, children: [
              if (_count > 0)
                Positioned.fill(
                  child: ExcludeSemantics(
                    child: NotificationListener<ScrollNotification>(
                      onNotification: _onScroll,
                      child: PageView.builder(
                        controller: _pages,
                        physics: _count < 2 ? const NeverScrollableScrollPhysics() : null,
                        onPageChanged: (page) => setState(() => _current = page % _count),
                        itemBuilder: (_, page) => NetworkImageBox(url: widget.images[page % _count]),
                      ),
                    ),
                  ),
                ),
              Positioned.fill(
                child: IgnorePointer(
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.centerLeft,
                        end: Alignment.centerRight,
                        stops: const [0, .65, 1],
                        colors: [
                          ZayloColors.cream.withValues(alpha: .94),
                          ZayloColors.cream.withValues(alpha: .69),
                          ZayloColors.cream.withValues(alpha: .25),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
              // The bottom inset keeps the copy clear of the dots.
              Padding(
                padding: EdgeInsets.fromLTRB(width * .05, 20, width * .05, 36),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 360),
                  child: _HeroCopy(compact: compact, wide: width > 480, onShop: widget.onShop, onNew: widget.onNew),
                ),
              ),
              if (_count > 1)
                Positioned(
                  right: width * .05,
                  bottom: 12,
                  child: _HeroDots(count: _count, current: _current, onSelect: _showImage),
                ),
            ]),
          ),
        );
      });
}

class _HeroCopy extends StatelessWidget {
  const _HeroCopy({required this.compact, required this.wide, required this.onShop, required this.onNew});

  final bool compact;

  /// The web hides the description on screens 480px and narrower.
  final bool wide;
  final VoidCallback onShop;
  final VoidCallback onNew;

  @override
  Widget build(BuildContext context) {
    final headline = TextStyle(
      fontFamily: 'Playfair Display',
      // Sized so the headline stays on two lines in the landscape banner.
      fontSize: compact ? 24 : 27,
      fontWeight: FontWeight.w600,
      height: 1.1,
      letterSpacing: -.7,
      color: ZayloColors.ink,
    );
    return Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
      // Only the buttons take touches, so swipes over the text reach the
      // images, as pointer-events: none does on the web.
      IgnorePointer(
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Opacity(
            opacity: .8,
            child: Text('ONE MARKETPLACE. ENDLESS FINDS.',
                style: TextStyle(fontSize: compact ? 10 : 12, letterSpacing: compact ? 1.8 : 2.4, color: ZayloColors.muted)),
          ),
          const SizedBox(height: 10),
          Text.rich(
            TextSpan(style: headline, children: [
              const TextSpan(text: 'Driven by passion.\n'),
              TextSpan(
                text: 'Defined by your origin',
                style: TextStyle(color: ZayloColors.bronze, fontStyle: FontStyle.italic),
              ),
            ]),
          ),
          const SizedBox(height: 16),
          if (wide) ...[
            SizedBox(
              width: 260,
              child: Opacity(
                opacity: .85,
                child: Text(
                  'Discover the latest tech, home essentials, fashion, beauty, groceries, and more from sellers across the marketplace.',
                  style: TextStyle(fontSize: 12.8, height: 1.6, color: ZayloColors.ink),
                ),
              ),
            ),
            const SizedBox(height: 32),
          ],
        ]),
      ),
      Wrap(
        spacing: compact ? 16 : 24,
        runSpacing: 10,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          FilledButton(
            onPressed: onShop,
            style: FilledButton.styleFrom(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 10),
              minimumSize: const Size(0, 38),
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              shape: const RoundedRectangleBorder(),
              textStyle: const TextStyle(fontSize: 10.4, fontWeight: FontWeight.w600, letterSpacing: 1.25),
            ),
            child: const Text('SHOP THE MARKETPLACE'),
          ),
          TextButton(
            onPressed: onNew,
            style: TextButton.styleFrom(
              padding: EdgeInsets.zero,
              minimumSize: const Size(0, 32),
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              textStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500, letterSpacing: .5),
            ),
            child: const Text("See what's new →"),
          ),
        ],
      ),
    ]);
  }
}

/// One dot per image, styled like the web's: the active one stretches into a
/// pill. Each dot has a 24px touch target.
class _HeroDots extends StatelessWidget {
  const _HeroDots({required this.count, required this.current, required this.onSelect});

  final int count;
  final int current;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) => Row(mainAxisSize: MainAxisSize.min, children: [
        for (var index = 0; index < count; index++) ...[
          if (index > 0) const SizedBox(width: 7),
          Semantics(
            button: true,
            selected: index == current,
            label: 'Show image ${index + 1} of $count',
            child: GestureDetector(
              behavior: HitTestBehavior.opaque,
              onTap: () => onSelect(index),
              child: SizedBox(
                width: 24,
                height: 24,
                child: Center(
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 200),
                    width: index == current ? 20 : 8,
                    height: 8,
                    decoration: BoxDecoration(
                      color: index == current ? ZayloColors.ink : ZayloColors.ink.withValues(alpha: .4),
                      borderRadius: BorderRadius.circular(999),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ]);
}

/// The chat icon in the Home top bar, with a badge for unread seller messages. Signed-in buyers only.
class _MessagesButton extends StatelessWidget {
  const _MessagesButton();

  @override
  Widget build(BuildContext context) {
    final state = AppScope.of(context);
    if (!state.signedIn) return const SizedBox.shrink();
    final unread = state.chatUnread;
    return IconButton(
      tooltip: unread > 0 ? 'Messages, $unread unread' : 'Messages',
      onPressed: () => Navigator.of(context)
          .push(MaterialPageRoute(builder: (_) => const SellerInboxScreen()))
          .then((_) => state.refreshChatUnread()),
      icon: Badge(
        isLabelVisible: unread > 0,
        label: Text(unread > 99 ? '99+' : '$unread'),
        backgroundColor: ZayloColors.ink,
        child: const Icon(Icons.chat_bubble_outline),
      ),
    );
  }
}
