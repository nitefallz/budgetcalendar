<?php
require_once 'db.php';

header('Content-Type: application/json');

// Handle GET request to load weekly overrides
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $weekStart = $_GET['week_start'] ?? null;
    if (!$weekStart) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing week_start']);
        exit;
    }

    // Fetch overrides for the given week
    $stmt = $pdo->prepare("SELECT id, label, amount FROM weekly_expense_overrides WHERE week_start = ?");
    $stmt->execute([$weekStart]);
    $overrides = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no overrides are found, fall back to default weekly expenses
    if (empty($overrides)) {
        $defaults = $pdo->query("SELECT label, amount FROM weekly_expenses ORDER BY label ASC")
            ->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($defaults);
    } else {
        echo json_encode($overrides);
    }
    exit;
}

// Handle POST request to save overrides
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input']);
        exit;
    }

    $weekStart = $input['week_start'] ?? null;
    $entries = $input['overrides'] ?? [];

    if (!$weekStart) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing week_start']);
        exit;
    }

    try {
        // Begin transaction for atomicity
        $pdo->beginTransaction();

        // Delete old overrides for the specified week
        $deleteStmt = $pdo->prepare("DELETE FROM weekly_expense_overrides WHERE week_start = ?");
        $deleteStmt->execute([$weekStart]);

        // Insert new overrides if provided
        $insertStmt = $pdo->prepare("INSERT INTO weekly_expense_overrides (week_start, label, amount) VALUES (?, ?, ?)");
        foreach ($entries as $entry) {
            $label  = trim($entry['label'] ?? '');
            $amount = floatval($entry['amount'] ?? 0);
            if ($label !== '') {
                $insertStmt->execute([$weekStart, $label, $amount]);
            }
        }

        // Commit transaction
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode([
            'error' => 'Database error',
            'details' => $e->getMessage()
        ]);
    }
    exit;
}

// For any other request method, return 405 Method Not Allowed
http_response_code(405);
echo json_encode(['error' => 'Invalid request method']);
?>
