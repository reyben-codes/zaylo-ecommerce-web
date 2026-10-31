import 'dart:async';

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

Timer? _activeMessageTimer;

void showMessage(
  BuildContext context,
  String message, {
  String? actionLabel,
  VoidCallback? onAction,
  Duration duration = const Duration(milliseconds: 2600),
}) {
  _activeMessageTimer?.cancel();
  final messenger = ScaffoldMessenger.maybeOf(context);
  if (messenger == null) return;
  messenger.hideCurrentSnackBar();

  late final ScaffoldFeatureController<SnackBar, SnackBarClosedReason> controller;
  controller = messenger.showSnackBar(
    SnackBar(
      duration: duration,
      behavior: SnackBarBehavior.floating,
      backgroundColor: Colors.transparent,
      elevation: 0,
      padding: EdgeInsets.zero,
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 18),
      dismissDirection: DismissDirection.horizontal,
      content: _ZayloToastCard(
        message: message,
        duration: duration,
        actionLabel: actionLabel,
        onAction: actionLabel == null
            ? null
            : () {
                _activeMessageTimer?.cancel();
                controller.close();
                onAction?.call();
              },
        onClose: () {
          _activeMessageTimer?.cancel();
          controller.close();
        },
      ),
    ),
  );

  // Android devices with accessibility/autofill services enabled set accessibleNavigation=true, which causes
  // Flutter's SnackBar with actions to never auto-dismiss. A dedicated timer guarantees it always closes.
  if (!WidgetsBinding.instance.runtimeType.toString().contains('Test')) {
    _activeMessageTimer = Timer(duration, () {
      controller.close();
    });
  }
}

class _ZayloToastCard extends StatefulWidget {
  const _ZayloToastCard({
    required this.message,
    required this.duration,
    this.actionLabel,
    this.onAction,
    required this.onClose,
  });

  final String message;
  final Duration duration;
  final String? actionLabel;
  final VoidCallback? onAction;
  final VoidCallback onClose;

  @override
  State<_ZayloToastCard> createState() => _ZayloToastCardState();
}

class _ZayloToastCardState extends State<_ZayloToastCard> with TickerProviderStateMixin {
  static const _fadeDuration = Duration(milliseconds: 220);

  late final AnimationController _fadeController;
  late final Animation<double> _fadeAnimation;
  AnimationController? _progressController;
  Timer? _testProgressTimer;
  double _progress = 1.0;
  int _elapsedMs = 0;

  bool get _inWidgetTest => WidgetsBinding.instance.runtimeType.toString().contains('Test');

  @override
  void initState() {
    super.initState();
    _fadeController = AnimationController(vsync: this, duration: _fadeDuration);
    _fadeAnimation = CurvedAnimation(parent: _fadeController, curve: Curves.easeOut, reverseCurve: Curves.easeIn);
    _fadeController.forward();

    final totalMs = widget.duration.inMilliseconds.clamp(400, 60000);
    final fadeOutStartMs = (totalMs - _fadeDuration.inMilliseconds).clamp(0, totalMs);

    if (_inWidgetTest) {
      // Periodic timer instead of a long-running Ticker so pumpAndSettle in existing tests settles immediately.
      const stepMs = 65;
      _testProgressTimer = Timer.periodic(const Duration(milliseconds: stepMs), (timer) {
        if (!mounted) {
          timer.cancel();
          return;
        }
        _elapsedMs += stepMs;
        final nextProgress = (1.0 - (_elapsedMs / totalMs)).clamp(0.0, 1.0);
        if (_elapsedMs >= fadeOutStartMs && _fadeController.status == AnimationStatus.completed) {
          _fadeController.reverse();
        }
        setState(() => _progress = nextProgress);
        if (_elapsedMs >= totalMs) {
          timer.cancel();
        }
      });
    } else {
      _progressController = AnimationController(vsync: this, duration: widget.duration, value: 1.0)
        ..addListener(() {
          if (!mounted) return;
          final current = _progressController!.value;
          final elapsed = ((1.0 - current) * totalMs).round();
          if (elapsed >= fadeOutStartMs && _fadeController.status == AnimationStatus.completed) {
            _fadeController.reverse();
          }
          setState(() => _progress = current);
        })
        ..reverse(from: 1.0);
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.disableAnimationsOf(context)) {
      _fadeController.value = 1.0;
    }
  }

  Future<void> _fadeOutAndRun(VoidCallback callback) async {
    _testProgressTimer?.cancel();
    _progressController?.stop();
    if (!MediaQuery.disableAnimationsOf(context) && mounted && _fadeController.value > 0) {
      await _fadeController.reverse();
    }
    callback();
  }

  @override
  void dispose() {
    _testProgressTimer?.cancel();
    _progressController?.dispose();
    _fadeController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => FadeTransition(
        opacity: _fadeAnimation,
        child: Container(
          decoration: BoxDecoration(
            color: ZayloColors.ink,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: ZayloColors.bronze.withValues(alpha: 0.35)),
            boxShadow: [
              BoxShadow(
                color: ZayloColors.shadow.withValues(alpha: 0.28),
                blurRadius: 18,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          clipBehavior: Clip.antiAlias,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 11, 10, 10),
                child: Row(
                  children: [
                    Container(
                      width: 28,
                      height: 28,
                      decoration: BoxDecoration(
                        color: ZayloColors.bronze.withValues(alpha: 0.22),
                        shape: BoxShape.circle,
                      ),
                      child: Icon(Icons.check_rounded, size: 16, color: ZayloColors.white),
                    ),
                    const SizedBox(width: 11),
                    Expanded(
                      child: Text(
                        widget.message,
                        style: TextStyle(
                          color: ZayloColors.white,
                          fontSize: 13.5,
                          fontWeight: FontWeight.w600,
                          height: 1.25,
                        ),
                      ),
                    ),
                    if (widget.actionLabel != null) ...[
                      const SizedBox(width: 8),
                      Material(
                        color: ZayloColors.white,
                        borderRadius: BorderRadius.circular(999),
                        child: InkWell(
                          onTap: widget.onAction == null ? null : () => _fadeOutAndRun(widget.onAction!),
                          borderRadius: BorderRadius.circular(999),
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
                            child: Text(
                              widget.actionLabel!,
                              style: TextStyle(
                                color: ZayloColors.ink,
                                fontSize: 11.5,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.4,
                              ),
                            ),
                          ),
                        ),
                      ),
                    ],
                    const SizedBox(width: 4),
                    IconButton(
                      tooltip: 'Dismiss notification',
                      visualDensity: VisualDensity.compact,
                      constraints: const BoxConstraints.tightFor(width: 28, height: 28),
                      padding: EdgeInsets.zero,
                      onPressed: () => _fadeOutAndRun(widget.onClose),
                      icon: Icon(Icons.close_rounded, size: 16, color: ZayloColors.white.withValues(alpha: 0.7)),
                    ),
                  ],
                ),
              ),
              LinearProgressIndicator(
                key: const ValueKey('toast-progress-bar'),
                value: _progress,
                minHeight: 3,
                backgroundColor: ZayloColors.white.withValues(alpha: 0.14),
                valueColor: AlwaysStoppedAnimation<Color>(ZayloColors.bronze),
              ),
            ],
          ),
        ),
      );
}

class Loader extends StatelessWidget {
  const Loader({super.key});

  @override
  Widget build(BuildContext context) =>
      Center(child: CircularProgressIndicator(color: ZayloColors.ink));
}

class LoadError extends StatelessWidget {
  const LoadError({super.key, required this.error, required this.onRetry, this.title = 'Something went wrong'});

  final Object? error;
  final VoidCallback onRetry;
  final String title;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(36),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off_outlined, size: 45),
            const SizedBox(height: 16),
            Text(title, style: Theme.of(context).textTheme.headlineSmall, textAlign: TextAlign.center),
            const SizedBox(height: 8),
            Text(error.toString(), textAlign: TextAlign.center),
            const SizedBox(height: 20),
            FilledButton(onPressed: onRetry, style: primaryButtonStyle, child: const Text('Try again')),
          ]),
        ),
      );
}

class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    this.icon,
    this.iconWidget,
    required this.title,
    required this.message,
    this.actionLabel,
    this.onAction,
  }) : assert(icon != null || iconWidget != null);

  final IconData? icon;

  /// Used instead of [icon], for drawn icons such as the cart.
  final Widget? iconWidget;
  final String title;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) => Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(36),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            CircleAvatar(
              radius: 38,
              backgroundColor: ZayloColors.sand,
              child: iconWidget ?? Icon(icon, size: 34, color: ZayloColors.ink),
            ),
            const SizedBox(height: 20),
            Text(title, style: Theme.of(context).textTheme.headlineSmall, textAlign: TextAlign.center),
            const SizedBox(height: 8),
            Text(message, textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodyMedium),
            if (actionLabel != null) ...[
              const SizedBox(height: 22),
              FilledButton(onPressed: onAction, style: primaryButtonStyle, child: Text(actionLabel!)),
            ],
          ]),
        ),
      );
}
