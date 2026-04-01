<?php
$host='127.0.0.1';
$db='e_library';
$user='root';
$pass='';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->query("SELECT id, email, firstname, lastname, is_active, status, created_at FROM users ORDER BY id DESC LIMIT 10");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
