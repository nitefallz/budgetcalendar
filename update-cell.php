<?php
// update-cell.php
require_once 'db.php';
header('Content-Type: application/json');

// Retrieve POST data
$date = $_POST['date'] ?? '';
$type = $_POST['type'] ?? '';
$value = $_POST['value'] ?? '';

if (!$date || !$type || !is_numeric($value)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input data.']);
    exit;
}

try {
    if ($type === 'override') {
        // Daily balance override
        $stmt = $pdo->prepare("REPLACE INTO daily_overrides (date, override_amount) VALUES (?, ?)");
        $stmt->execute([$date, $value]);
    } elseif ($type === 'deposit') {
        // Daily deposit override
        $stmt = $pdo->prepare("REPLACE INTO daily_deposit_overrides (date, override_amount) VALUES (?, ?)");
        $stmt->execute([$date, $value]);
    } elseif ($type === 'expense') {
        // Daily expense override
        $stmt = $pdo->prepare("REPLACE INTO daily_expense_overrides (date, override_amount) VALUES (?, ?)");
        $stmt->execute([$date, $value]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Unknown type specified.']);
        exit;
    }

    // Clear month cache for the month of the updated date and the next month
    $dateObj = new DateTime($date);
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
