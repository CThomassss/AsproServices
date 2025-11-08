<?php
require_once __DIR__ . '/config.php';

$mysqli = @new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE, MYSQL_PORT);
if ($mysqli->connect_errno) {
    echo "DB connect error: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    exit;
}
$mysqli->set_charset('utf8mb4');

$res = $mysqli->query("SELECT COUNT(*) AS cnt FROM promotions");
if ($res) {
    $row = $res->fetch_assoc();
    echo "promotions table rows: " . intval($row['cnt']) . "<br>\n";
    // show first 3 rows
    $r2 = $mysqli->query("SELECT id,title,discount,validUntil,imageUrl FROM promotions ORDER BY id DESC LIMIT 3");
    if ($r2) {
        echo "<pre>";
        while ($rr = $r2->fetch_assoc()) {
            print_r($rr);
        }
        echo "</pre>";
    }
} else {
    echo "Query failed: " . $mysqli->error;
}

$mysqli->close();
