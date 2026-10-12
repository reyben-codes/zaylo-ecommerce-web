import 'package:flutter/material.dart';

import '../../core/theme/zaylo_theme.dart';
import '../../core/widgets/buttons.dart';
import '../content/rider_terms.dart';

/// Shows the Rider Terms in a pop-up. Resolves to `true` once the rider has
/// scrolled to the bottom (i.e. read them), `false` if they close it first.
Future<bool> showRiderTermsDialog(BuildContext context) async =>
    await showDialog<bool>(context: context, builder: (_) => const _RiderTermsDialog()) ?? false;

class _RiderTermsDialog extends StatefulWidget {
  const _RiderTermsDialog();

  @override
  State<_RiderTermsDialog> createState() => _RiderTermsDialogState();
}

class _RiderTermsDialogState extends State<_RiderTermsDialog> {
  /// Distance from the end that still counts as "reached the bottom".
  static const _bottomTolerance = 24.0;

  final _scroll = ScrollController();
  bool _reachedBottom = false;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_checkBottom);
    // On tall screens the whole text may fit without scrolling.
    WidgetsBinding.instance.addPostFrameCallback((_) => _checkBottom());
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  void _checkBottom() {
    if (_reachedBottom || !_scroll.hasClients) return;
    final position = _scroll.position;
    if (position.pixels >= position.maxScrollExtent - _bottomTolerance) {
      setState(() => _reachedBottom = true);
    }
  }

  @override
  Widget build(BuildContext context) => Dialog(
        backgroundColor: ZayloColors.white,
        insetPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 32),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 18, 8, 6),
            child: Row(children: [
              Expanded(child: Text(riderTermsTitle, style: Theme.of(context).textTheme.titleMedium)),
              IconButton(
                tooltip: 'Close',
                onPressed: () => Navigator.pop(context, _reachedBottom),
                icon: const Icon(Icons.close),
              ),
            ]),
          ),
          const Divider(height: 1),
          Flexible(
            child: Scrollbar(
              controller: _scroll,
              thumbVisibility: true,
              child: ListView(
                controller: _scroll,
                shrinkWrap: true,
                padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
                children: [
                  Text('Version $riderTermsVersion', style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
                  const SizedBox(height: 12),
                  for (final section in riderTermsSections) ...[
                    Text(section.heading, style: const TextStyle(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 4),
                    Text(section.body, style: const TextStyle(height: 1.4)),
                    const SizedBox(height: 14),
                  ],
                  Text('End of terms.', style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
                ],
              ),
            ),
          ),
          const Divider(height: 1),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
            child: Column(children: [
              Row(children: [
                Icon(
                  _reachedBottom ? Icons.check_circle : Icons.swipe_up_outlined,
                  size: 18,
                  color: _reachedBottom ? ZayloColors.success : ZayloColors.muted,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    _reachedBottom
                        ? 'You\'ve read the terms. They are now accepted.'
                        : 'Scroll to the bottom to read and accept the terms.',
                    style: const TextStyle(fontSize: 12),
                  ),
                ),
              ]),
              const SizedBox(height: 12),
              PrimaryButton(
                label: _reachedBottom ? 'DONE' : 'SCROLL TO READ ALL',
                onPressed: _reachedBottom ? () => Navigator.pop(context, true) : null,
              ),
            ]),
          ),
        ]),
      );
}
