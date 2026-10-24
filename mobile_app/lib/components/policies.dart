import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_client.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/models/support_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

/// The website's policy pages (terms, privacy, returns, prohibited items).
class PoliciesScreen extends StatefulWidget {
  const PoliciesScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<PoliciesScreen> createState() => _PoliciesScreenState();
}

class _PoliciesScreenState extends State<PoliciesScreen> {
  late Future<List<PolicySummary>> _policies = widget.api.policies();

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Terms & policies')),
        body: FutureBuilder<List<PolicySummary>>(
          future: _policies,
          builder: (context, snapshot) {
            if (snapshot.hasError) {
              return LoadError(
                error: snapshot.error,
                title: 'Could not load the policies',
                onRetry: () => setState(() => _policies = widget.api.policies()),
              );
            }
            if (!snapshot.hasData) return const Loader();
            return ListView(padding: const EdgeInsets.all(ZayloSpace.page), children: [
              SectionCard(
                padding: EdgeInsets.zero,
                child: Column(children: [
                  for (final (index, policy) in snapshot.requireData.indexed) ...[
                    if (index > 0) Divider(height: 1, indent: 56, color: ZayloColors.line),
                    ListTile(
                      leading: Icon(Icons.description_outlined, color: ZayloColors.ink),
                      title: Text(policy.title,
                          style: TextStyle(fontWeight: FontWeight.w600, color: ZayloColors.ink, fontSize: 14)),
                      trailing: Icon(Icons.chevron_right, color: ZayloColors.muted),
                      onTap: () => Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => PolicyScreen(api: widget.api, slug: policy.slug, title: policy.title),
                      )),
                    ),
                  ],
                ]),
              ),
            ]);
          },
        ),
      );
}

class PolicyScreen extends StatefulWidget {
  const PolicyScreen({super.key, required this.api, required this.slug, required this.title});

  final ApiClient api;
  final String slug;
  final String title;

  @override
  State<PolicyScreen> createState() => _PolicyScreenState();
}

class _PolicyScreenState extends State<PolicyScreen> {
  late Future<Policy> _policy = widget.api.policy(widget.slug);

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(widget.title)),
        body: FutureBuilder<Policy>(
          future: _policy,
          builder: (context, snapshot) {
            if (snapshot.hasError) {
              return LoadError(
                error: snapshot.error,
                title: 'Policy unavailable',
                onRetry: () => setState(() => _policy = widget.api.policy(widget.slug)),
              );
            }
            if (!snapshot.hasData) return const Loader();
            final policy = snapshot.requireData;
            return SelectionArea(
              child: ListView(padding: const EdgeInsets.all(ZayloSpace.page), children: [
                Text(policy.title, style: Theme.of(context).textTheme.headlineSmall),
                if (policy.updatedAt != null) ...[
                  const SizedBox(height: 4),
                  Text('Last updated ${shortDate(policy.updatedAt)}',
                      style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
                ],
                const SizedBox(height: 8),
                ...PolicyText.blocks(context, policy.body),
              ]),
            );
          },
        ),
      );
}

/// Renders the admin's policy text the way the website does: "## " lines are
/// headings, "- " lines are bullets, and blank lines separate paragraphs.
abstract final class PolicyText {
  static List<Widget> blocks(BuildContext context, String text) {
    final widgets = <Widget>[];
    final paragraph = <String>[];
    void flush() {
      if (paragraph.isEmpty) return;
      widgets.add(Padding(
        padding: const EdgeInsets.only(top: 10),
        child: Text(paragraph.join('\n'), style: Theme.of(context).textTheme.bodyMedium),
      ));
      paragraph.clear();
    }

    for (final raw in text.split(RegExp(r'\r?\n'))) {
      final line = raw.trimRight();
      final bullet = RegExp(r'^\s*[-*] (.+)$').firstMatch(line);
      if (line.startsWith('## ')) {
        flush();
        widgets.add(Padding(
          padding: const EdgeInsets.only(top: 22, bottom: 2),
          child: Text(line.substring(3).trim(),
              style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
        ));
      } else if (bullet != null) {
        flush();
        widgets.add(Padding(
          padding: const EdgeInsets.only(top: 8, left: 4),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('•  ', style: TextStyle(color: ZayloColors.ink, fontWeight: FontWeight.w800)),
            Expanded(child: Text(bullet.group(1)!.trim(), style: Theme.of(context).textTheme.bodyMedium)),
          ]),
        ));
      } else if (line.trim().isEmpty) {
        flush();
      } else {
        paragraph.add(line.trim());
      }
    }
    flush();
    return widgets;
  }
}
