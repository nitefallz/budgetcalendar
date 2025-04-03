<?php
require_once 'db.php';
require_once 'functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    clearMonthBalanceCache($pdo); // Or scope to a specific range
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
http_response_code(400);
