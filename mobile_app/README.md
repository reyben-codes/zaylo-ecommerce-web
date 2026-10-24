# ZAYLO Mobile

The buyer-facing Flutter companion for the Laravel ZAYLO marketplace. It mirrors the web store's warm cream, black, and taupe visual language and includes a browsable catalog, product details, saved items, a quantity-aware cart, checkout confirmation, and an account view.

## Start it

From this directory, generate native platform folders once (they are intentionally not committed here), then run the app:

```powershell
flutter create . --platforms=android,ios
flutter pub get
flutter run
```

The app reads the same Laravel marketplace database through the read-only API at
`/api/mobile`. It does **not** connect directly to MySQL, which keeps database
credentials out of the app. By default it uses the live site,
`https://zaylo-zyl.shop/api`, so with a phone connected this is enough:

```powershell
flutter run
```

To use a local Laravel server instead (`php artisan serve`), pass its address,
for example from the Android emulator:

```powershell
flutter run --dart-define=ZAYLO_API_URL=http://10.0.2.2:8000/api
```

For a physical phone on a local server, replace `10.0.2.2` with the LAN address
of the machine running Laravel. The visual tokens are in
`lib/theme/zaylo_theme.dart`, API work is isolated in
`lib/services/storefront_api.dart`, and the storefront widgets are in
`lib/screens/storefront_screen.dart`.

The existing cart, wishlist, checkout, and account routes are browser-session
routes. The mobile UI currently keeps bag/saved interactions in its session;
add Laravel Sanctum-backed authenticated endpoints before making those writes
persist to a buyer account.
