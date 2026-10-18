Test-only RSA keys for `tests/Feature/MobileGoogleSignInTest.php`. They sign fake
Google ID tokens; the test serves their public halves as Google's key set. They
are not used anywhere else and grant no access.
