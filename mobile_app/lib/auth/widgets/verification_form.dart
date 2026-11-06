import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:zaylo_mobile/api/api_client.dart';
import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/auth/widgets/otp_code_field.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/form_fields.dart';
import 'package:zaylo_mobile/models/auth_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// The six-digit email code form shared by buyer and rider sign-up.
/// The code checks itself as soon as the sixth digit is in (typed, pasted or
/// picked from the keyboard's suggestion); the boxes then show checking,
/// verified or wrong-code states. [onVerified] decides what happens next:
/// buyers are signed in, riders are told their application is under review.
class VerificationForm extends StatefulWidget {
  const VerificationForm({super.key, required this.api, required this.email, required this.onVerified});

  final ApiClient api;
  final String email;
  final Future<void> Function(AuthResult result) onVerified;

  @override
  State<VerificationForm> createState() => _VerificationFormState();
}

class _VerificationFormState extends State<VerificationForm> {
  static const _length = 6;

  final _code = TextEditingController();
  final _focus = FocusNode();
  OtpStatus _status = OtpStatus.idle;
  String? _message;
  bool _resending = false;
  int _attempt = 0;

  bool get _verifying => _status == OtpStatus.verifying;

  @override
  void dispose() {
    _code.dispose();
    _focus.dispose();
    super.dispose();
  }

  void _onChanged(String code) {
    if (_status == OtpStatus.error) setState(() => _status = OtpStatus.idle);
    if (_message != null && _status == OtpStatus.idle) setState(() => _message = null);
    if (code.isNotEmpty) HapticFeedback.selectionClick();
    if (code.length == _length) _verify();
  }

  Future<void> _verify() async {
    if (_verifying || _status == OtpStatus.success) return;
    if (_code.text.length != _length) {
      _fail('Enter all six digits from your email.', keepCode: true);
      return;
    }
    final attempt = ++_attempt;
    setState(() {
      _status = OtpStatus.verifying;
      _message = 'Checking your code…';
    });
    try {
      final result = await widget.api.verifyEmail(email: widget.email, code: _code.text);
      if (!mounted || attempt != _attempt) return;
      HapticFeedback.mediumImpact();
      setState(() {
        _status = OtpStatus.success;
        _message = 'Email verified!';
      });
      _focus.unfocus();
      // Let the verified state show before moving on.
      await Future<void>.delayed(Duration(milliseconds: MediaQuery.disableAnimationsOf(context) ? 300 : 900));
      if (mounted) await widget.onVerified(result);
    } on ApiException catch (error) {
      if (!mounted || attempt != _attempt) return;
      // No status code means the request never reached ZAYLO, so the code may still be right.
      _fail(error.message, keepCode: error.statusCode == null);
    }
  }

  void _fail(String message, {bool keepCode = false}) {
    HapticFeedback.heavyImpact();
    setState(() {
      _status = OtpStatus.error;
      _message = message;
    });
    if (keepCode) {
      _focus.requestFocus();
      return;
    }
    // Let the shake play, then empty the boxes so the next try starts clean.
    final attempt = _attempt;
    Future<void>.delayed(const Duration(milliseconds: 650), () {
      if (!mounted || attempt != _attempt || _status != OtpStatus.error) return;
      _code.clear();
      setState(() {});
      _focus.requestFocus();
    });
  }

  /// Fills the boxes from a code the person copied out of the email.
  Future<void> _paste() async {
    final data = await Clipboard.getData(Clipboard.kTextPlain);
    if (!mounted) return;
    final code = OtpCodeField.extractCode(data?.text, length: _length);
    if (code.length != _length) {
      setState(() {
        _status = OtpStatus.idle;
        _message = 'No 6-digit code on your clipboard. Copy the code from the email first.';
      });
      return;
    }
    _code.value = TextEditingValue(text: code, selection: TextSelection.collapsed(offset: code.length));
    _attempt++;
    setState(() {
      _status = OtpStatus.idle;
      _message = null;
    });
    _onChanged(code);
  }

  Future<void> _resend() async {
    setState(() => _resending = true);
    try {
      final response = await widget.api.resendVerification(widget.email);
      if (!mounted) return;
      _attempt++;
      _code.clear();
      setState(() {
        _status = OtpStatus.idle;
        _message = null;
      });
      _focus.requestFocus();
      showMessage(context, response['message'] as String? ?? 'A new code has been sent.');
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _resending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final locked = _verifying || _status == OtpStatus.success;
    final tone = switch (_status) {
      OtpStatus.success => ZayloColors.success,
      OtpStatus.error => ZayloColors.dangerText,
      _ => ZayloColors.muted,
    };
    final reduceMotion = MediaQuery.disableAnimationsOf(context);

    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      const FieldLabel('Email verification code'),
      OtpCodeField(controller: _code, focusNode: _focus, status: _status, onChanged: _onChanged, length: _length),
      const SizedBox(height: 10),
      AnimatedSize(
        duration: reduceMotion ? Duration.zero : const Duration(milliseconds: 220),
        curve: Curves.easeOut,
        alignment: Alignment.topLeft,
        child: AnimatedSwitcher(
          duration: reduceMotion ? Duration.zero : const Duration(milliseconds: 200),
          child: _message == null
              ? const SizedBox(key: ValueKey('no-message'), width: double.infinity)
              : Semantics(
                  key: ValueKey('${_status.name}-$_message'),
                  liveRegion: true,
                  child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Padding(
                      padding: const EdgeInsets.only(top: 1, right: 6),
                      child: switch (_status) {
                        OtpStatus.verifying => SizedBox(
                            width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: ZayloColors.accent)),
                        OtpStatus.success => Icon(Icons.check_circle_rounded, size: 16, color: tone),
                        OtpStatus.error => Icon(Icons.error_outline_rounded, size: 16, color: tone),
                        OtpStatus.idle => Icon(Icons.info_outline_rounded, size: 16, color: tone),
                      },
                    ),
                    Expanded(
                      child: Text(_message!,
                          style: TextStyle(
                              fontSize: 13,
                              color: tone,
                              fontWeight: _status == OtpStatus.success ? FontWeight.w600 : FontWeight.w400)),
                    ),
                  ]),
                ),
        ),
      ),
      Align(
        alignment: Alignment.centerLeft,
        child: TextButton.icon(
          onPressed: locked ? null : _paste,
          icon: const Icon(Icons.content_paste_rounded, size: 18),
          label: const Text('Paste code'),
        ),
      ),
      Text(
        'Your code expires after 5 minutes. It checks itself once all six digits are in. You can type it, paste it, or tap the code your keyboard suggests.',
        style: TextStyle(fontSize: 12, color: ZayloColors.muted),
      ),
      const SizedBox(height: 25),
      PrimaryButton(
        label: _status == OtpStatus.success ? 'VERIFIED' : 'VERIFY EMAIL',
        icon: _status == OtpStatus.success ? Icons.check_rounded : null,
        busy: _verifying,
        onPressed: _resending || _status == OtpStatus.success ? null : _verify,
      ),
      TextButton(
        onPressed: locked || _resending ? null : _resend,
        child: Text(_resending ? 'Sending…' : 'Resend code'),
      ),
    ]);
  }
}
