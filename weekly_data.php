<?php
// weekly_data.php
require_once 'db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM weekly_expenses ORDER BY id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents("php://input");
    $entries = json_decode($raw, true);

    if (!is_array($entries)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid input"]);
        exit;
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Clear existing entries
        $pdo->exec("DELETE FROM weekly_expenses");

        // Prepare multi-insert
        $stmt = $pdo->prepare("INSERT INTO weekly_expenses (label, amount) VALUES (?, ?)");
        foreach ($entries as $e) {
            $label  = trim($e['label'] ?? '');
            $amount = floatval($e['amount'] ?? 0);
            if ($label !== '' && $amount !== 0) {
                $stmt->execute([$label, $amount]);
            }
        }
        $pdo->commit();
        echo json_encode(["status" => "success"]);
    } catch (Exception $ex) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(["error" => "Database error", "details" => $ex->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Method not allowed"]);
?>
