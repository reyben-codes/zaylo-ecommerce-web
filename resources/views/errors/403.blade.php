<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ZAYLO · Access Denied</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #faf7f2; color: #1a1714; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 24px; }
        .error-container { text-align: center; max-width: 480px; }
        .error-icon { font-size: 4rem; color: #b28b6f; margin-bottom: 24px; }
        .error-code { font-family: 'Playfair Display', serif; font-size: 6rem; font-weight: 600; color: #1a1714; line-height: 1; margin-bottom: 8px; letter-spacing: -0.02em; }
        .error-title { font-family: 'Playfair Display', serif; font-size: 1.6rem; font-weight: 600; margin-bottom: 12px; }
        .error-message { font-size: 0.9rem; color: #6b5f54; line-height: 1.6; margin-bottom: 36px; }
        .error-actions { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
        .btn-primary { background: #1a1714; color: white; padding: 14px 32px; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; text-decoration: none; display: inline-block; transition: 0.15s; }
        .btn-primary:hover { background: #b28b6f; }
        .btn-secondary { background: transparent; color: #1a1714; border: 1px solid #1a1714; padding: 14px 32px; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; text-decoration: none; display: inline-block; transition: 0.15s; }
        .btn-secondary:hover { background: #1a1714; color: white; }
        .divider { width: 48px; height: 2px; background: #ece4db; margin: 24px auto; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon"><i class="fas fa-shield-alt"></i></div>
        <div class="error-code">403</div>
        <h1 class="error-title">Access Denied</h1>
        <div class="divider"></div>
        <p class="error-message">You don't have permission to access this area. Please make sure you're signed in with the correct account.</p>
        <div class="error-actions">
            <a href="{{ url('/') }}" class="btn-primary">Go Home</a>
            @auth
                <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn-secondary">Switch Account</button>
                </form>
            @endauth
        </div>
    </div>
</body>
</html>
