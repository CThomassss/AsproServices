<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Enforce session inactivity timeout
enforceSessionTimeout();

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Only superadmin can activate accounts
$role = $_SESSION['user']['role'] ?? '';
if ($role !== 'superadmin') {
    http_response_code(403);
    $_SESSION['flash'] = 'Accès refusé : seuls les superadmins peuvent activer des comptes.';
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Get and validate id from POST
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    $_SESSION['flash'] = 'Identifiant invalide.';
    header('Location: index.php');
    exit;
}

$pdo = getPDO();
if (!$pdo) {
    $_SESSION['flash'] = 'Impossible de se connecter à la base de données.';
    header('Location: index.php');
    exit;
}

try {
    // Prevent self-activation
    if ($id == ($_SESSION['user']['id'] ?? 0)) {
        $_SESSION['flash'] = 'Vous ne pouvez pas activer votre propre compte.';
        header('Location: index.php');
        exit;
    }

    // Load target user to make safe decisions
    $s = $pdo->prepare('SELECT id, role, isActive FROM users WHERE id = ? LIMIT 1');
    $s->execute([$id]);
    $target = $s->fetch();
    if (!$target) {
        $_SESSION['flash'] = 'Utilisateur introuvable.';
        header('Location: index.php');
        exit;
    }

    if (!empty($target['isActive'])) {
        $_SESSION['flash'] = 'Cet utilisateur est déjà activé.';
        header('Location: index.php');
        exit;
    }

    // If the target is already superadmin, do not change role; just activate
    if (isset($target['role']) && $target['role'] === 'superadmin') {
        $upd = $pdo->prepare('UPDATE users SET isActive = 1 WHERE id = ? LIMIT 1');
        $upd->execute([$id]);
    } else {
        // Set isActive and upgrade role to 'admin'
        $upd = $pdo->prepare("UPDATE users SET isActive = 1, role = 'admin' WHERE id = ? LIMIT 1");
        $upd->execute([$id]);
    }

    if ($upd->rowCount() > 0) {
        $_SESSION['flash'] = 'Utilisateur activé.';
    } else {
        $_SESSION['flash'] = 'Échec de l\'activation (aucune modification appliquée).';
    }

} catch (Exception $e) {
    error_log('activate_user error: ' . $e->getMessage());
    $_SESSION['flash'] = 'Erreur lors de l\'activation.';
}

header('Location: index.php');
