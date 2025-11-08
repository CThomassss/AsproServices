<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['user']['role'] ?? '';
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo 'Accès refusé';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

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

// prevent deleting the last superadmin or self-delete
try {
    // get user
    $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) {
        $_SESSION['flash'] = 'Utilisateur introuvable.';
        header('Location: index.php');
        exit;
    }

    // prevent self-delete
    if ($user['id'] == ($_SESSION['user']['id'] ?? 0)) {
        $_SESSION['flash'] = 'Vous ne pouvez pas supprimer votre propre compte.';
        header('Location: index.php');
        exit;
    }

    // if deleting a superadmin, ensure there is another superadmin left
    if ($user['role'] === 'superadmin') {
        $stmt2 = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE role = 'superadmin'");
        $c = $stmt2->fetch();
        if ($c && $c['c'] <= 1) {
            $_SESSION['flash'] = 'Impossible de supprimer le dernier superadmin.';
            header('Location: index.php');
            exit;
        }
    }

    $del = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $ok = $del->execute([$id]);
    if ($ok) {
        $_SESSION['flash'] = 'Utilisateur supprimé.';
    } else {
        $_SESSION['flash'] = 'Échec de la suppression.';
    }
} catch (Exception $e) {
    error_log('delete_user error: '. $e->getMessage());
    $_SESSION['flash'] = 'Erreur lors de la suppression.';
}

header('Location: index.php');
