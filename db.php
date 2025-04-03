<?php
// --- Database Connection Settings ---
$host    = 'localhost:3360';
$db      = 'budget2';    // Replace with your database name
$user    = 'realms';         // Replace with your DB username
$pass    = 'Realms!99X';             // Replace with your DB password (if any)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}