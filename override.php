<?php
// override.php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action == 'add_override') {
        $date = $_POST['date'] ?? '';
        $input = trim($_POST['override_amount'] ?? '');

        if (!preg_match('/^[0-9\+\-\*\/\.\s\(\)]+$/', $input)) {
            http_response_code(400);
            echo "Invalid characters in expression.";
            exit;
        }

        try {
            // Evaluate the math expression safely
            eval('$result = ' . $input . ';');
            if (!is_numeric($result)) {
                throw new Exception("Result is not numeric");
            }
            $override_amount = floatval($result);
        } catch (Throwable $e) {
            http_response_code(400);
            echo "Invalid expression.";
            exit;
        }

        $stmt = $pdo->prepare("REPLACE INTO daily_overrides (date, override_amount) VALUES (?, ?)");
        if ($stmt->execute([$date, $override_amount])) {
            echo "Success";
        } else {
            http_response_code(500);
            echo "Error saving override.";
        }
        exit;
    } elseif ($action == 'delete_override') {
        $date = $_POST['date'] ?? '';
        $stmt = $pdo->prepare("DELETE FROM daily_overrides WHERE date = ?");
        if ($stmt->execute([$date])) {
            echo "Deleted";
        } else {
            http_response_code(500);
            echo "Error deleting override.";
        }
        exit;
    }
}
