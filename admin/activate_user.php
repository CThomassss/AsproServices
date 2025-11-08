<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['user']['role'] ?? '';
if (!in_array($role, ['superadmin', 'admin'])) {
    http_response_code(403);
    echo 'Accès refusé';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

    $role = $_SESSION['user']['role'] ?? '';
    // only superadmin can validate/activate accounts
    if ($role !== 'superadmin') {
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

$stmt = $pdo->prepare('UPDATE users SET isActive = 1 WHERE id = ? LIMIT 1');
$ok = $stmt->execute([$id]);
if ($ok) {
    $_SESSION['flash'] = 'Utilisateur activé.';
} else {
    $_SESSION['flash'] = 'Échec de l\'activation.';
}
header('Location: index.php');
