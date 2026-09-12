<?php

use App\Models\Address;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ViewErrorBag;

// CLI-only browser fixture. It renders an unsaved user/address and sends no email.
if (PHP_SAPI !== 'cli') {
    exit;
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
URL::forceRootUrl('http://127.0.0.1:8765');
Auth::setUser(new User([
    'name' => 'Preview Buyer', 'email' => 'preview@example.com', 'role' => 'buyer', 'status' => 'active',
]));
echo view('addresses.form', [
    'address' => new Address(['recipient_name' => 'Preview Buyer', 'label' => 'Home']),
    'returnTo' => 'checkout',
    'errors' => new ViewErrorBag,
])->render();
