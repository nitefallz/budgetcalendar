<?php
// delete-override.php
require_once 'db.php';
header('Content-Type: application/json');

$date = $_POST['date'] ?? '';
$type = $_POST['type'] ?? '';

if (!$date || !$type) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    // Standardize date format
    $dateObj = new DateTime($date);
    $formattedDate = $dateObj->format('Y-m-d');

    if ($type === 'override') {
        $stmt = $pdo->prepare("DELETE FROM daily_overrides WHERE date = ?");
    } elseif ($type === 'deposit') {
        $stmt = $pdo->prepare("DELETE FROM daily_deposit_overrides WHERE date = ?");
    } elseif ($type === 'expense') {
        $stmt = $pdo->prepare("DELETE FROM daily_expense_overrides WHERE date = ?");
    } else {
        echo json_encode(['success' => false, 'error' => 'Unknown type']);
        exit;
    }

    $stmt->execute([$formattedDate]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'No override record found for that date']);
        exit;
    }

    // Clear month cache for affected month and next month
    $yearUpdated = $dateObj->format('Y');
    $monthUpdated = $dateObj->format('n');
    $nextDate = clone $dateObj;
    $nextDate->modify('first day of next month');
    $yearNext = $nextDate->format('Y');
    $monthNext = $nextDate->format('n');
    $clearStmt = $pdo->prepare(
        "DELETE FROM month_balances WHERE (year = ? AND month = ?) OR (year = ? AND month = ?)"
    );
    $clearStmt->execute([$yearUpdated, $monthUpdated, $yearNext, $monthNext]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
