import 'package:flutter/widgets.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/buyer_api.dart';
import 'package:zaylo_mobile/auth/google_auth.dart';
import 'package:zaylo_mobile/auth/token_store.dart';
import 'package:zaylo_mobile/models/auth_models.dart';
import 'package:zaylo_mobile/models/buyer/cart_models.dart';
import 'package:zaylo_mobile/models/buyer/catalog_models.dart';

/// Session, bag and saved-item state shared by every screen.
class AppState extends ChangeNotifier {
  AppState({required this.api, TokenStore? tokens}) : _tokens = tokens ?? SecureTokenStore() {
    api.onUnauthorized = _expireSession;
  }

  final BuyerApi api;
  final TokenStore _tokens;

  UserProfile? _user;
  CartSummary _cart = const CartSummary.empty();
  final Set<String> _savedSlugs = {};
  bool _restoring = true;

  UserProfile? get user => _user;
  bool get signedIn => _user != null;
  bool get restoring => _restoring;
  CartSummary get cart => _cart;
  int get cartCount => _cart.count;
  int get savedCount => _savedSlugs.length;

  bool isSaved(Product product) => _savedSlugs.contains(product.slug);

  /// Bottom navigation tab: 0 home, 1 saved, 2 bag, 3 account.
  final ValueNotifier<int> selectedTab = ValueNotifier(0);

  @override
  void dispose() {
    selectedTab.dispose();
    super.dispose();
  }

  /// Restores a previous session on launch. Network failures keep the token,
  /// so a buyer who opens the app offline is not signed out.
  Future<void> restore() async {
    final token = await _tokens.read();
    if (token != null) {
      api.token = token;
      try {
        _user = await api.me();
        await Future.wait([refreshCart(), _loadSaved()]);
      } on ApiException catch (error) {
        if (error.statusCode == 401) await _clearSession();
      }
    }
    _restoring = false;
    notifyListeners();
  }

  Future<void> completeSignIn(AuthResult result) async {
    final token = result.token;
    if (token == null || result.user == null) return;
    api.token = token;
    await _tokens.write(token);
    _user = result.user;
    notifyListeners();
    try {
      await Future.wait([refreshCart(), _loadSaved()]);
    } on ApiException {
      // The session is valid; the bag badge refreshes on next visit.
    }
  }

  void updateUser(UserProfile user) {
    _user = user;
    notifyListeners();
  }

  Future<void> signOut() async {
    try {
      await api.logout();
    } on ApiException {
      // The token is discarded locally either way.
    }
    // So the next "Continue with Google" lets the buyer choose an account again.
    await GoogleAuth.source.signOut();
    await _clearSession();
    notifyListeners();
  }

  Future<void> refreshCart() async {
    if (!signedIn) return;
    setCart(await api.cart());
  }

  void setCart(CartSummary cart) {
    _cart = cart;
    notifyListeners();
  }

  Future<CartSummary> addToBag(Product product, {int quantity = 1, int? variantId}) async {
    final cart = await api.addToCart(product.slug, quantity: quantity, variantId: variantId);
    setCart(cart);
    return cart;
  }

  /// Returns the new saved state.
  Future<bool> toggleSaved(Product product) async {
    final saved = await api.toggleWishlist(product.slug);
    saved ? _savedSlugs.add(product.slug) : _savedSlugs.remove(product.slug);
    notifyListeners();
    return saved;
  }

  /// Keeps saved hearts in sync after the Saved tab reloads the list.
  void syncSaved(Iterable<Product> products) {
    _savedSlugs
      ..clear()
      ..addAll(products.map((product) => product.slug));
    notifyListeners();
  }

  Future<void> _loadSaved() async => syncSaved(await api.wishlist());

  Future<void> _clearSession() async {
    api.token = null;
    _user = null;
    _cart = const CartSummary.empty();
    _savedSlugs.clear();
    await _tokens.clear();
  }

  void _expireSession() {
    if (!signedIn) return;
    _clearSession().then((_) => notifyListeners());
  }
}

class AppScope extends InheritedNotifier<AppState> {
  const AppScope({super.key, required AppState state, required super.child}) : super(notifier: state);

  /// Subscribes the caller to state changes.
  static AppState of(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<AppScope>()!.notifier!;

  /// Reads state without subscribing, for event handlers.
  static AppState read(BuildContext context) =>
      context.getInheritedWidgetOfExactType<AppScope>()!.notifier!;
}
