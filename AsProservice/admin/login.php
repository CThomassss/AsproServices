<?php
// Modern, minimal admin login page
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// If already logged in, redirect to dashboard
if (!empty($_SESSION['user'])) {
        header('Location: index.php');
        exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $user = verifyUserCredentials($username, $password);
        if ($user) {
                // minimal session payload
                $_SESSION['user'] = ['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role'] ?? 'admin'];
                header('Location: index.php');
                exit;
        }
        $error = 'Identifiants invalides.';
}

// Find exported CSS file from frontend/_next (prefer explicit CSS_FILE)
$cssFile = null;
if (!empty(CSS_FILE) && strpos(CSS_FILE, '/_next/static/css/') !== false) {
        $cssFile = basename(CSS_FILE);
}
if (!$cssFile) {
        foreach (glob(__DIR__ . '/../frontend/_next/static/css/*.css') as $f) {
                $cssFile = basename($f);
                break;
        }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Admin — Connexion</title>
    <?php if ($cssFile): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(SITE_URL . '/frontend/_next/static/css/' . $cssFile) ?>" crossorigin="" />
        <link rel="stylesheet" href="<?= htmlspecialchars(SITE_URL . '/admin/static/tailwind-like.css') ?>" />
    <?php else: ?>
        <style>body{font-family:Arial,Helvetica,sans-serif;background:#f3f4f6;margin:0;padding:32px} .card{max-width:420px;margin:48px auto;padding:24px;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.06)}</style>
        <link rel="stylesheet" href="<?= htmlspecialchars(SITE_URL . '/admin/static/tailwind-like.css') ?>" />
    <?php endif; ?>
    <style>.login-brand{display:block;text-align:center;margin-bottom:18px}.login-brand img{height:44px}</style>
</head>
<body>
    <main class="min-h-screen flex items-center justify-center" style="padding:24px;display:flex;align-items:center;justify-content:center;min-height:100vh;">
        <section class="card" style="max-width:420px;width:100%;margin:0 auto;">
            <h1 class="text-2xl font-semibold text-slate-900 text-center">Connexion administrateur</h1>
            <?php if ($error): ?>
                <p class="mt-3 text-sm text-red-600 text-center"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <form method="post" class="mt-6 space-y-4">
                <label class="block text-sm">Identifiant
                    <input name="username" required class="mt-1 block w-full rounded-md border bg-white px-3 py-2" />
                </label>
                <label class="block text-sm">Mot de passe
                    <input name="password" type="password" required class="mt-1 block w-full rounded-md border bg-white px-3 py-2" />
                </label>
                <div class="pt-2">
                    <button type="submit" class="cta-button w-full">Se connecter</button>
                </div>
            </form>
            <p class="mt-4 text-xs text-slate-500 text-center">Connexion sécurisée — utilisez vos identifiants administrateur</p>
        </section>
    </main>
</body>
</html>
