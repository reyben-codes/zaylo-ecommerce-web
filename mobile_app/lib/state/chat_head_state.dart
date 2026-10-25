import 'package:flutter/widgets.dart';

import 'package:zaylo_mobile/state/app_state.dart';

/// The floating Messenger-style chat bubble's state (see ChatHeadOverlay).
class ChatHeadState extends ChangeNotifier {
  bool _dismissed = false;
  int _hiders = 0;
  int? _conversationId;
  String? _partnerName;

  /// Dragged onto the dismiss target; opening Messages brings it back, as on the website.
  bool get dismissed => _dismissed;

  /// Screens where the bubble would be in the way (Messages, a chat, the rider app).
  bool get hidden => _hiders > 0;

  /// The conversation last opened: the bubble shows that person and reopens it.
  int? get conversationId => _conversationId;
  String? get partnerName => _partnerName;

  void dismiss() {
    _dismissed = true;
    notifyListeners();
  }

  void restore() {
    if (!_dismissed) return;
    _dismissed = false;
    notifyListeners();
  }

  void openedConversation(int id, String name) {
    _conversationId = id;
    _partnerName = name;
    notifyListeners();
  }

  void reset() {
    _dismissed = false;
    _conversationId = null;
    _partnerName = null;
    notifyListeners();
  }

  bool _disposed = false;

  @override
  void dispose() {
    _disposed = true;
    super.dispose();
  }

  void _hide() {
    _hiders++;
    _notifyAfterFrame();
  }

  void _show() {
    _hiders--;
    _notifyAfterFrame();
  }

  /// Screens register while building or unmounting; the bubble updates once that frame is done.
  void _notifyAfterFrame() => WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!_disposed) notifyListeners();
      });
}

/// Hides the chat bubble while [child] is on screen (and any route above it).
/// Does nothing outside the buyer app's [AppScope].
class HideChatHead extends StatefulWidget {
  const HideChatHead({super.key, required this.child});

  final Widget child;

  @override
  State<HideChatHead> createState() => _HideChatHeadState();
}

class _HideChatHeadState extends State<HideChatHead> {
  ChatHeadState? _state;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_state != null) return;
    _state = context.getInheritedWidgetOfExactType<AppScope>()?.notifier?.chatHead;
    _state?._hide();
  }

  @override
  void dispose() {
    _state?._show();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
