import 'package:flutter/material.dart';

import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

void showMessage(BuildContext context, String message, {String? actionLabel, VoidCallback? onAction}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(
      content: Text(message),
      behavior: SnackBarBehavior.floating,
      action: actionLabel == null ? null : SnackBarAction(label: actionLabel, onPressed: onAction ?? () {}),
    ));
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
