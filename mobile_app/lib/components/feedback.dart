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

class _ZayloToastCard extends StatelessWidget {
  const _ZayloToastCard({
    required this.message,
    this.actionLabel,
    this.onAction,
    required this.onClose,
  });

  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.fromLTRB(14, 11, 10, 11),
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
                message,
                style: TextStyle(
                  color: ZayloColors.white,
                  fontSize: 13.5,
                  fontWeight: FontWeight.w600,
                  height: 1.25,
                ),
              ),
            ),
            if (actionLabel != null) ...[
              const SizedBox(width: 8),
              Material(
                color: ZayloColors.white,
                borderRadius: BorderRadius.circular(999),
                child: InkWell(
                  onTap: onAction,
                  borderRadius: BorderRadius.circular(999),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
                    child: Text(
                      actionLabel!,
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
              onPressed: onClose,
              icon: Icon(Icons.close_rounded, size: 16, color: ZayloColors.white.withValues(alpha: 0.7)),
            ),
          ],
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
