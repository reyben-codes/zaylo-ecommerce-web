import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_client.dart';
import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/file_picking.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/models/support_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

/// Complaint cases the user is part of: the buyer's own complaints, or for a
/// rider, complaints about deliveries they handled. Shared by both apps.
class ComplaintsScreen extends StatefulWidget {
  const ComplaintsScreen({super.key, required this.api, this.emptyMessage});

  final ApiClient api;
  final String? emptyMessage;

  @override
  State<ComplaintsScreen> createState() => _ComplaintsScreenState();
}

class _ComplaintsScreenState extends State<ComplaintsScreen> {
  late Future<List<Complaint>> _complaints = widget.api.complaints();

  Future<void> _reload() async {
    final complaints = widget.api.complaints();
    setState(() => _complaints = complaints);
    try {
      await complaints;
    } on ApiException {
      // Shown by the FutureBuilder's error state.
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Complaints')),
        body: FutureBuilder<List<Complaint>>(
          future: _complaints,
          builder: (context, snapshot) {
            if (snapshot.hasError) {
              return LoadError(error: snapshot.error, onRetry: _reload, title: 'Could not load complaints');
            }
            if (!snapshot.hasData) return const Loader();
            final complaints = snapshot.requireData;
            if (complaints.isEmpty) {
              return EmptyState(
                icon: Icons.report_gmailerrorred_outlined,
                title: 'No complaints',
                message: widget.emptyMessage ?? 'Cases about your orders will appear here.',
              );
            }
            return RefreshIndicator(
              color: ZayloColors.ink,
              onRefresh: _reload,
              child: ListView.separated(
                padding: const EdgeInsets.all(ZayloSpace.page),
                itemCount: complaints.length,
                separatorBuilder: (_, __) => const SizedBox(height: 10),
                itemBuilder: (context, index) {
                  final complaint = complaints[index];
                  return SectionCard(
                    padding: EdgeInsets.zero,
                    child: ListTile(
                      contentPadding: const EdgeInsets.fromLTRB(16, 8, 12, 8),
                      title: Text(complaint.categoryLabel,
                          style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
                      subtitle: Text(
                        [
                          if (complaint.orderNumber != null) 'Order ${complaint.orderNumber}',
                          'Updated ${relativeTime(complaint.updatedAt)}',
                        ].join(' · '),
                        style: const TextStyle(fontSize: 12),
                      ),
                      trailing: ComplaintStatusPill(status: complaint.status, label: complaint.statusLabel),
                      onTap: () async {
                        await Navigator.of(context).push(MaterialPageRoute(
                          builder: (_) => ComplaintDetailScreen(api: widget.api, complaintId: complaint.id),
                        ));
                        if (mounted) _reload();
                      },
                    ),
                  );
                },
              ),
            );
          },
        ),
      );
}

class ComplaintStatusPill extends StatelessWidget {
  const ComplaintStatusPill({super.key, required this.status, required this.label});

  final String status;
  final String label;

  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'resolved' => ZayloColors.success,
      'rejected' => ZayloColors.danger,
      'in_review' => ZayloColors.info,
      _ => ZayloColors.warning,
    };
    return DecoratedBox(
      decoration: BoxDecoration(
        color: color.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        child: Text(label, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w800)),
      ),
    );
  }
}

/// One case: what went wrong, the evidence, and the thread with the ZAYLO team.
class ComplaintDetailScreen extends StatefulWidget {
  const ComplaintDetailScreen({super.key, required this.api, required this.complaintId});

  final ApiClient api;
  final int complaintId;

  @override
  State<ComplaintDetailScreen> createState() => _ComplaintDetailScreenState();
}

class _ComplaintDetailScreenState extends State<ComplaintDetailScreen> {
  static const _pollEvery = Duration(seconds: 30);

  final _reply = TextEditingController();
  final _scroll = ScrollController();
  Complaint? _complaint;
  Object? _error;
  File? _attachment;
  String? _replyError;
  bool _sending = false;
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    _load();
    _poll = Timer.periodic(_pollEvery, (_) => _load(quiet: true));
  }

  @override
  void dispose() {
    _poll?.cancel();
    _reply.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _load({bool quiet = false}) async {
    try {
      final complaint = await widget.api.complaint(widget.complaintId);
      if (!mounted) return;
      setState(() {
        _complaint = complaint;
        _error = null;
      });
    } on ApiException catch (error) {
      if (mounted && !quiet) setState(() => _error = error);
    }
  }

  Future<void> _attach() async {
    final file = await pickPhotoOrFile(context, fileExtensions: uploadExtensions, fileHint: 'JPG, PNG, WebP or PDF up to 5.0 MB');
    if (file == null || !mounted) return;
    final problem = uploadFileError(file);
    if (problem != null) {
      showMessage(context, problem);
      return;
    }
    setState(() => _attachment = file);
  }

  Future<void> _send() async {
    final body = _reply.text.trim();
    if (body.isEmpty) {
      setState(() => _replyError = 'Write a reply first.');
      return;
    }
    setState(() {
      _sending = true;
      _replyError = null;
    });
    try {
      final updated = await widget.api.replyToComplaint(widget.complaintId, body, attachment: _attachment);
      if (!mounted) return;
      _reply.clear();
      setState(() {
        _complaint = updated;
        _attachment = null;
      });
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_scroll.hasClients) {
          _scroll.animateTo(_scroll.position.maxScrollExtent,
              duration: const Duration(milliseconds: 250), curve: Curves.easeOut);
        }
      });
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _replyError = error.fieldErrors['body'] ?? error.fieldErrors['attachment'] ?? error.message);
      _load(quiet: true);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final complaint = _complaint;
    return Scaffold(
      appBar: AppBar(title: Text(complaint?.orderNumber == null ? 'Complaint' : 'Order ${complaint!.orderNumber}')),
      body: complaint == null
          ? (_error != null ? LoadError(error: _error, onRetry: _load, title: 'Could not open the complaint') : const Loader())
          : Column(children: [
              Expanded(
                child: RefreshIndicator(
                  color: ZayloColors.ink,
                  onRefresh: _load,
                  child: ListView(
                    controller: _scroll,
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(ZayloSpace.page),
                    children: [
                      _CaseCard(complaint: complaint, headers: widget.api.authHeaders),
                      if (complaint.resolution != null && complaint.resolution!.trim().isNotEmpty) ...[
                        const SizedBox(height: 12),
                        SectionCard(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            const Eyebrow('Decision from the ZAYLO team'),
                            const SizedBox(height: 6),
                            Text(complaint.resolution!, style: TextStyle(color: ZayloColors.ink)),
                          ]),
                        ),
                      ],
                      const SizedBox(height: 18),
                      const Eyebrow('Conversation'),
                      const SizedBox(height: 8),
                      if (complaint.messages.isEmpty)
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 8),
                          child: Text('No replies yet. The ZAYLO team will reply here, and you can add details anytime.',
                              style: TextStyle(fontSize: 13, color: ZayloColors.muted)),
                        ),
                      for (final message in complaint.messages)
                        _MessageBubble(message: message, headers: widget.api.authHeaders),
                    ],
                  ),
                ),
              ),
              if (complaint.canReply) _composer() else _closedNotice(),
            ]),
    );
  }

  Widget _composer() => SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(8, 8, 8, 8),
          decoration: BoxDecoration(color: ZayloColors.paper, border: Border(top: BorderSide(color: ZayloColors.line))),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            if (_attachment != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(8, 0, 8, 6),
                child: InputChip(
                  avatar: Icon(_attachment!.path.toLowerCase().endsWith('.pdf')
                      ? Icons.picture_as_pdf_outlined
                      : Icons.image_outlined),
                  label: Text(_attachment!.uri.pathSegments.last, overflow: TextOverflow.ellipsis),
                  deleteButtonTooltipMessage: 'Remove attachment',
                  onDeleted: _sending ? null : () => setState(() => _attachment = null),
                ),
              ),
            Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
              IconButton(
                tooltip: 'Attach a photo or PDF',
                onPressed: _sending ? null : _attach,
                icon: const Icon(Icons.attach_file),
              ),
              Expanded(
                child: TextField(
                  controller: _reply,
                  minLines: 1,
                  maxLines: 4,
                  maxLength: 2000,
                  enabled: !_sending,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: InputDecoration(
                    hintText: 'Reply to the ZAYLO team',
                    errorText: _replyError,
                    counterText: '',
                    filled: true,
                    fillColor: ZayloColors.white,
                    border: const OutlineInputBorder(borderRadius: BorderRadius.all(Radius.circular(22))),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  ),
                ),
              ),
              const SizedBox(width: 6),
              IconButton.filled(
                tooltip: 'Send reply',
                onPressed: _sending ? null : _send,
                style: IconButton.styleFrom(backgroundColor: ZayloColors.ink, foregroundColor: ZayloColors.white),
                icon: _sending
                    ? SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: ZayloColors.white))
                    : const Icon(Icons.send),
              ),
            ]),
          ]),
        ),
      );

  Widget _closedNotice() => SafeArea(
        top: false,
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.fromLTRB(20, 14, 20, 14),
          decoration: BoxDecoration(color: ZayloColors.paper, border: Border(top: BorderSide(color: ZayloColors.line))),
          child: Row(children: [
            Icon(Icons.lock_outline, size: 18, color: ZayloColors.muted),
            const SizedBox(width: 10),
            const Expanded(
              child: Text('This case is closed. Message ZAYLO support if you need more help.',
                  style: TextStyle(fontSize: 13)),
            ),
          ]),
        ),
      );
}

class _CaseCard extends StatelessWidget {
  const _CaseCard({required this.complaint, required this.headers});

  final Complaint complaint;
  final Map<String, String> headers;

  @override
  Widget build(BuildContext context) => SectionCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Expanded(
              child: Text(complaint.categoryLabel,
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: ZayloColors.ink)),
            ),
            ComplaintStatusPill(status: complaint.status, label: complaint.statusLabel),
          ]),
          const SizedBox(height: 4),
          Text('Filed ${dateTimeLabel(complaint.openedAt)}', style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
          if (complaint.details != null) ...[
            const SizedBox(height: 12),
            Text(complaint.details!, style: TextStyle(color: ZayloColors.ink)),
          ],
          if (complaint.evidence.isNotEmpty) ...[
            const SizedBox(height: 14),
            const Eyebrow('Evidence'),
            const SizedBox(height: 8),
            Wrap(spacing: 8, runSpacing: 8, children: [
              for (final file in complaint.evidence) _FileTile(file: file, headers: headers, size: 76),
            ]),
          ],
        ]),
      );
}

class _MessageBubble extends StatelessWidget {
  const _MessageBubble({required this.message, required this.headers});

  final ComplaintMessage message;
  final Map<String, String> headers;

  @override
  Widget build(BuildContext context) {
    final mine = message.mine;
    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * .8),
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 8),
        decoration: BoxDecoration(
          color: mine ? ZayloColors.ink : ZayloColors.sand,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          if (!mine)
            Padding(
              padding: const EdgeInsets.only(bottom: 3),
              child: Text(message.sender,
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: ZayloColors.ink)),
            ),
          Text(message.body, style: TextStyle(color: mine ? ZayloColors.white : ZayloColors.ink)),
          if (message.attachment != null) ...[
            const SizedBox(height: 8),
            _FileTile(file: message.attachment!, headers: headers, size: 120),
          ],
          const SizedBox(height: 4),
          Text(dateTimeLabel(message.sentAt),
              style: TextStyle(fontSize: 11, color: mine ? ZayloColors.onInkMuted : ZayloColors.muted)),
        ]),
      ),
    );
  }
}

/// A photo thumbnail that opens full screen, or a PDF label.
class _FileTile extends StatelessWidget {
  const _FileTile({required this.file, required this.headers, required this.size});

  final ComplaintFile file;
  final Map<String, String> headers;
  final double size;

  @override
  Widget build(BuildContext context) {
    if (!file.isImage) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        decoration: BoxDecoration(
          color: ZayloColors.white,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: ZayloColors.line),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.picture_as_pdf_outlined, size: 20, color: Colors.redAccent),
          const SizedBox(width: 6),
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 160),
            child: Text(file.name, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12, color: ZayloColors.ink)),
          ),
        ]),
      );
    }
    return Semantics(
      button: true,
      label: 'Open photo ${file.name}',
      child: GestureDetector(
        onTap: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => Scaffold(
            backgroundColor: Colors.black,
            appBar: AppBar(backgroundColor: Colors.black, foregroundColor: Colors.white, title: Text(file.name)),
            body: InteractiveViewer(
              child: Center(child: NetworkImageBox(url: file.url, headers: headers, fit: BoxFit.contain)),
            ),
          ),
        )),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: SizedBox(width: size, height: size, child: NetworkImageBox(url: file.url, headers: headers)),
        ),
      ),
    );
  }
}
