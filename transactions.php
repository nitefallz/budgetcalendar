<?php
// transactions.php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $date = $_GET['date'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE date = ?");
    $stmt->execute([$date]);
    $transactions = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($transactions);
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Expect JSON data in POST body (or fallback to form data)
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }
    $date = $data['date'] ?? '';
    $transactions = $data['transactions'] ?? [];
    // Delete existing transactions for that date
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE date = ?");
    $stmt->execute([$date]);
    // Insert new transactions
    $stmt = $pdo->prepare("INSERT INTO transactions (date, type, amount, notes) VALUES (?, ?, ?, ?)");
    foreach ($transactions as $tran) {
        $type = $tran['type'] ?? '';
        $amount = $tran['amount'] ?? 0;
        $notes = $tran['notes'] ?? '';
        $stmt->execute([$date, $type, $amount, $notes]);
    }
    echo "Success";
    exit;
}
