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
    // login attempt
    if (isset($_POST['login'])) {
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

    // registration attempt
    if (isset($_POST['register'])) {
        $r_username = trim($_POST['reg_username'] ?? '');
        $r_password = $_POST['reg_password'] ?? '';
        $r_password2 = $_POST['reg_password2'] ?? '';
        if ($r_username === '' || $r_password === '') {
            $regError = 'Veuillez renseigner un identifiant et un mot de passe.';
        } elseif ($r_password !== $r_password2) {
            $regError = 'Les mots de passe ne correspondent pas.';
        } elseif (strlen($r_password) < 6) {
            $regError = 'Le mot de passe doit contenir au moins 6 caractères.';
        } else {
            // create user as invite (guest) and inactive by default
            $newId = createUser($r_username, $r_password, 'invite', 0);
            if ($newId) {
                $regSuccess = 'Compte créé avec succès. Il est inactif et devra être activé par un administrateur.';
            } else {
                $regError = 'Impossible de créer le compte. Le nom d\'utilisateur existe peut-être déjà.';
            }
        }
    }
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
    <section class="card text-center" style="position:relative;max-width:420px;width:100%;margin:0 auto;text-align:center;">
        <!-- Small return link top-right of the card -->
        <a href="<?= htmlspecialchars(SITE_URL . '/index.html') ?>" class="back-to-site" style="position:absolute;top:12px;right:12px;font-size:0.85rem;padding:6px 10px;background:#0b61a4;color:#fff;border-radius:999px;text-decoration:none;">Retour au site</a>
            <div id="login-area">
                <h1 id="auth-title" class="text-2xl font-semibold text-slate-900 text-center">Connexion administrateur</h1>
                <?php if ($error): ?>
                    <p class="mt-3 text-sm text-red-600 text-center"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
                <form method="post" class="mt-6" style="max-width:340px;margin:0 auto;text-align:center;">
                <div style="margin-bottom:12px;text-align:center;">
                    <label class="block text-sm" style="display:block;margin-bottom:6px">Identifiant</label>
                    <input name="username" required class="mt-1 rounded-md border bg-white px-3 py-2" style="display:block;margin:0 auto;width:85%;text-align:center;" />
                </div>
                <div style="margin-bottom:12px;text-align:center;">
                    <label class="block text-sm" style="display:block;margin-bottom:6px">Mot de passe</label>
                    <input name="password" type="password" required class="mt-1 rounded-md border bg-white px-3 py-2" style="display:block;margin:0 auto;width:85%;text-align:center;" />
                </div>
                    <div class="pt-2">
                    <button type="submit" name="login" class="cta-button w-full">Se connecter</button>
                </div>
                </form>
                            <p style="margin-top:18px;text-align:center;"><a href="#" id="toggle-register" style="color:#0b61a4;text-decoration:underline;">Créer un compte</a></p>

            </div>

            <div id="register-container" style="display:none;max-width:340px;margin:12px auto 0;text-align:center;">
                <?php if (!empty($regError)): ?>
                    <p class="mt-3 text-sm text-red-600 text-center"><?= htmlspecialchars($regError) ?></p>
                <?php elseif (!empty($regSuccess)): ?>
                    <p class="mt-3 text-sm text-green-600 text-center"><?= htmlspecialchars($regSuccess) ?></p>
                <?php endif; ?>

                <!-- horizontal rule removed to keep the card clean -->
                <h2 class="text-lg font-medium mt-2">Créer un nouveau compte</h2>
                <form method="post" class="mt-4" style="text-align:center;">
                    <div style="margin-bottom:12px;text-align:center;">
                        <label class="block text-sm" style="display:block;margin-bottom:6px">Identifiant</label>
                        <input name="reg_username" required class="mt-1 rounded-md border bg-white px-3 py-2" style="display:block;margin:0 auto;width:85%;text-align:center;" />
                    </div>
                    <div style="margin-bottom:12px;text-align:center;">
                        <label class="block text-sm" style="display:block;margin-bottom:6px">Mot de passe</label>
                        <input name="reg_password" type="password" required class="mt-1 rounded-md border bg-white px-3 py-2" style="display:block;margin:0 auto;width:85%;text-align:center;" />
                    </div>
                    <div style="margin-bottom:12px;text-align:center;">
                        <label class="block text-sm" style="display:block;margin-bottom:6px">Confirmez le mot de passe</label>
                        <input name="reg_password2" type="password" required class="mt-1 rounded-md border bg-white px-3 py-2" style="display:block;margin:0 auto;width:85%;text-align:center;" />
                    </div>
                    <div class="pt-2">
                        <button type="submit" name="register" class="cta-button w-full">Créer le compte</button>
                    </div>
                </form>
            <p style="margin-top:18px;text-align:center;"><a href="#" id="back-to-login" class="back-to-login" style="color:#0b61a4;text-decoration:underline;">connexion</a></p>

            
            </div>

            

            <script>
                (function(){
                    var toggle = document.getElementById('toggle-register');
                    var container = document.getElementById('register-container');
                    var back = document.getElementById('back-to-login');
                    toggle.addEventListener('click', function(e){
                        e.preventDefault();
                        var loginArea = document.getElementById('login-area');
                        if (container.style.display === 'none' || container.style.display === '') {
                            // show registration, hide login
                            container.style.display = 'block';
                            loginArea.style.display = 'none';
                            // show small status text under submit (does not replace link text)
                            var status = document.getElementById('register-status');
                            if (!status) {
                                status = document.createElement('div');
                                status.id = 'register-status';
                                status.style.marginTop = '8px';
                                status.style.fontSize = '0.9rem';
                                status.style.color = '#0b61a4';
                                // append after the create button
                                var submit = container.querySelector('button[type="submit"]');
                                if (submit && submit.parentNode) submit.parentNode.appendChild(status);
                            } else {
                                status.style.display = 'block';
                            }
                        } else {
                            // hide registration, show login
                            container.style.display = 'none';
                            loginArea.style.display = 'block';
                            var status = document.getElementById('register-status');
                            if (status) status.style.display = 'none';
                        }
                    });
                    if (back) {
                        back.addEventListener('click', function(e){
                            e.preventDefault();
                            // hide registration, show login
                            container.style.display = 'none';
                            var loginArea = document.getElementById('login-area');
                            if (loginArea) loginArea.style.display = 'block';
                            var status = document.getElementById('register-status');
                            if (status) status.style.display = 'none';
                        });
                    }
                })();
            </script>
            <!-- Toast notifications container -->
            <style>
                .toast-container{position:fixed;top:18px;right:18px;z-index:99999;display:flex;flex-direction:column;gap:10px}
                .toast{min-width:220px;max-width:360px;padding:10px 14px;border-radius:8px;color:#fff;box-shadow:0 8px 24px rgba(2,6,23,0.2);font-size:0.95rem}
                .toast-success{background:linear-gradient(90deg,#059669,#10b981)}
                .toast-error{background:linear-gradient(90deg,#ef4444,#f97316)}
                .toast-close{margin-left:8px;opacity:0.9;cursor:pointer}
            </style>
            <div id="toast-container" class="toast-container" aria-live="polite" aria-atomic="true"></div>
            <script>
                function showToast(message, type){
                    var container = document.getElementById('toast-container');
                    if (!container) return; 
                    var t = document.createElement('div');
                    t.className = 'toast ' + (type === 'success' ? 'toast-success' : 'toast-error');
                    t.textContent = message;
                    // close button
                    var close = document.createElement('span');
                    close.textContent = '✕';
                    close.className = 'toast-close';
                    close.style.float = 'right';
                    close.onclick = function(){ container.removeChild(t); };
                    t.appendChild(close);
                    container.appendChild(t);
                    setTimeout(function(){ if (t.parentNode) t.parentNode.removeChild(t); }, 6000);
                }
                document.addEventListener('DOMContentLoaded', function(){
                    <?php if (!empty($error)): ?>
                        showToast(<?= json_encode($error) ?>, 'error');
                    <?php endif; ?>
                    <?php if (!empty($regError)): ?>
                        // show registration form and display error toast
                        var rc = document.getElementById('register-container');
                        var la = document.getElementById('login-area');
                        if (rc && la) { rc.style.display='block'; la.style.display='none'; }
                        showToast(<?= json_encode($regError) ?>, 'error');
                    <?php endif; ?>
                    <?php if (!empty($regSuccess)): ?>
                        // hide registration and show success toast
                        var rc2 = document.getElementById('register-container');
                        var la2 = document.getElementById('login-area');
                        if (rc2 && la2) { rc2.style.display='none'; la2.style.display='block'; }
                        showToast(<?= json_encode($regSuccess) ?>, 'success');
                    <?php endif; ?>
                });
            </script>
            <p class="mt-4 text-xs text-slate-500 text-center">Connexion sécurisée — utilisez vos identifiants administrateur</p>
        </section>
    </main>
</body>
</html>
