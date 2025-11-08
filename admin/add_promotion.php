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

$title = trim($_POST['title'] ?? '');
$discount = trim($_POST['discount'] ?? '');
$description = trim($_POST['description'] ?? '');
$validUntil = trim($_POST['validUntil'] ?? '');

if ($title === '' || $discount === '' || $description === '' || $validUntil === '') {
    $_SESSION['flash'] = 'Tous les champs obligatoires doivent être remplis.';
    header('Location: index.php');
    exit;
}

// Normalize and validate discount: accept values like "20", "-20", "20%", "-20%" and
// ensure the numeric part is between 0 and 100. We store discounts as "-NN%" (or "0%" for zero).
$normalizedDiscount = null;
// Try to extract an integer from the provided value
if (preg_match('/-?\d+/', $discount, $m)) {
    $num = intval($m[0]);
    // Use absolute value for range check; business rule: magnitude 0..100
    $num = abs($num);
    if ($num < 0 || $num > 100) {
        $_SESSION['flash'] = 'La remise doit être un nombre entre 0 et 100.';
        header('Location: index.php');
        exit;
    }
    if ($num === 0) {
        $normalizedDiscount = '0%';
    } else {
        // store as negative percentage to indicate a reduction
        $normalizedDiscount = '-' . $num . '%';
    }
} else {
    // No valid number found
    $_SESSION['flash'] = 'Format de remise invalide. Saisissez un nombre entre 0 et 100.';
    header('Location: index.php');
    exit;
}

$imageUrl = null;
$pdfUrl = null;

// Handle image upload
if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $tmp = $_FILES['image']['tmp_name'];
    $name = basename($_FILES['image']['name']);
    $target = rtrim(UPLOADS_DIR, '/\\') . DIRECTORY_SEPARATOR . $name;
    if (move_uploaded_file($tmp, $target)) {
        $imageUrl = 'public/promotions/' . $name;
    }
}

// Handle PDF upload
if (!empty($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
    $tmp = $_FILES['pdf']['tmp_name'];
    $name = basename($_FILES['pdf']['name']);
    $target = rtrim(UPLOADS_DIR, '/\\') . DIRECTORY_SEPARATOR . $name;
    if (move_uploaded_file($tmp, $target)) {
        $pdfUrl = 'public/promotions/' . $name;
    }
}

// If the admin provided a URL for the PDF and did not upload a file, use it.
// The form shows a master checkbox to reveal these controls; respect that but
// also tolerate direct POSTs. If both are provided, the uploaded file takes precedence.
if (empty($pdfUrl) && !empty($_POST['urlpdf'])) {
    $maybe = trim($_POST['urlpdf']);
    if (filter_var($maybe, FILTER_VALIDATE_URL)) {
        $pdfUrl = $maybe;
    }
}

$id = addPromotion([
    'title' => $title,
    'discount' => $normalizedDiscount,
    'description' => $description,
    'validUntil' => $validUntil,
    'imageUrl' => $imageUrl,
    'pdfUrl' => $pdfUrl,
]);

$_SESSION['flash'] = 'Promotion ajoutée.';
header('Location: index.php');
exit;
