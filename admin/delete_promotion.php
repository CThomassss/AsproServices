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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = $_POST['id'] ?? null;
if ($id === null) {
    header('Location: index.php');
    exit;
}

deletePromotionById($id);
$_SESSION['flash'] = 'Promotion supprimée.';
header('Location: index.php');
exit;
