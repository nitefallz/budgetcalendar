<?php
// expense.php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $date   = $_POST['date'] ?? '';
    $notes  = trim($_POST['notes'] ?? '');

    // Validate date format (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        echo "Invalid date format.";
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO expenses (name, date, amount, notes) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$name, $date, $amount, $notes])) {
        echo "Expense Added";
    } else {
        http_response_code(500);
        echo "Error adding expense.";
    }
    exit;
}
