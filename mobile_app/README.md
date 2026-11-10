# ZAYLO Mobile

The Flutter companion for the Laravel ZAYLO marketplace. It includes buyer shopping and account journeys as well as the delivery-rider workflow.

## Start it

From this directory, fetch packages and run the app on a connected device or running emulator:

```powershell
flutter pub get
flutter run
```

The app uses Laravel's `/api/mobile` endpoints; it never connects directly to
MySQL. By default it uses the live site,
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
of the machine running Laravel. Visual tokens are in `lib/theme/zaylo_theme.dart`,
and API clients are in `lib/api/`.

Buyer and rider sessions use separate Sanctum bearer tokens kept in secure
storage.
