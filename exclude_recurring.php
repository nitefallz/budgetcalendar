<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dateInput = $_POST['date'] ?? null;
    if (!$dateInput) {
        http_response_code(400);
        echo 'Missing date';
        exit;
    }

    try {
        $target = new DateTime($dateInput);
        // Normalize target date to Y-m-d
        $targetStr = $target->format('Y-m-d');
    } catch (Exception $e) {
        http_response_code(400);
        echo 'Invalid date';
        exit;
    }

    // Prepare the insert statement once
    $insertStmt = $pdo->prepare("INSERT IGNORE INTO recurring_deposit_exclusions (deposit_id, date) VALUES (?, ?)");

    // Get all recurring deposits
    $stmt = $pdo->query("SELECT id, start_date, frequency FROM recurring_deposits");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = $row['id'];
        $start = new DateTime($row['start_date']);
        $frequency = $row['frequency'];

        // Only process if target is on or after start date
        if ($target < $start) {
            continue;
        }

        $match = false;
        if ($frequency === 'weekly') {
            $diffDays = $target->diff($start)->days;
            if ($diffDays % 7 === 0) {
                $match = true;
            }
        } elseif ($frequency === 'biweekly') {
            $diffDays = $target->diff($start)->days;
            if ($diffDays % 14 === 0) {
                $match = true;
            }
        } elseif ($frequency === 'monthly') {
            // Calculate total months difference
            $interval = $start->diff($target);
            $monthsDiff = $interval->y * 12 + $interval->m;
            $computed = (clone $start)->modify("+{$monthsDiff} months");
            if ($computed->format('Y-m-d') === $targetStr) {
                $match = true;
            }
        }

        if ($match) {
            $insertStmt->execute([$id, $targetStr]);
        }
    }

    echo 'Excluded';
    exit;
}
?>
