<?php
require_once __DIR__ . '/config.php';

function getPDO()
{
    if (empty(MYSQL_HOST) || empty(MYSQL_USER) || empty(MYSQL_DATABASE)) {
        return null;
    }

    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', MYSQL_HOST, MYSQL_PORT, MYSQL_DATABASE);
    try {
        $pdo = new PDO($dsn, MYSQL_USER, MYSQL_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (Exception $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        return null;
    }
}

function isUsingMysql()
{
    return getPDO() !== null;
}

function getPromotions()
{
    if (isUsingMysql()) {
        $pdo = getPDO();
        $stmt = $pdo->query('SELECT id, title, discount, description, validUntil, imageUrl, pdfUrl FROM promotions ORDER BY id DESC');
        return $stmt->fetchAll();
    }

    $file = PROMOTIONS_JSON;
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function savePromotionsFile(array $promotions)
{
    $file = PROMOTIONS_JSON;
    file_put_contents($file, json_encode(array_values($promotions), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function addPromotion(array $p)
{
    if (isUsingMysql()) {
        $pdo = getPDO();
        $stmt = $pdo->prepare('INSERT INTO promotions (title, discount, description, validUntil, imageUrl, pdfUrl) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$p['title'], $p['discount'], $p['description'], $p['validUntil'], $p['imageUrl'] ?? null, $p['pdfUrl'] ?? null]);
        return $pdo->lastInsertId();
    }

    $promotions = getPromotions();
    $id = 1;
    if (!empty($promotions)) {
        $ids = array_column($promotions, 'id');
        $id = max($ids) + 1;
    }
    $p['id'] = $id;
    $promotions[] = $p;
    savePromotionsFile($promotions);
    return $id;
}

function deletePromotionById($id)
{
    if (isUsingMysql()) {
        $pdo = getPDO();
        $stmt = $pdo->prepare('DELETE FROM promotions WHERE id = ?');
        return $stmt->execute([$id]);
    }

    $promotions = getPromotions();
    $promotions = array_filter($promotions, function ($p) use ($id) {
        return (string)$p['id'] !== (string)$id;
    });
    savePromotionsFile(array_values($promotions));
    return true;
}

function getUserByUsername($username)
{
    if (isUsingMysql()) {
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT id, username, passwordHash, isActive, role FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }
    return null;
}

function verifyUserCredentials($username, $password)
{
    $user = getUserByUsername($username);
    if ($user) {
        if (password_verify($password, $user['passwordHash'])) return $user;
        return null;
    }

    // Fallback to ADMIN_PASSWORD if no user found / no DB
    if (!empty(ADMIN_PASSWORD) && hash_equals(ADMIN_PASSWORD, $password)) {
        return ['id' => 0, 'username' => 'admin', 'role' => 'admin'];
    }
    return null;
}

?>
