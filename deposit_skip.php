<?php
require_once 'db.php';

// Get all recurring deposit IDs
$stmt = $pdo->query("SELECT id FROM recurring_deposits");
$depositIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'exclude') {
    $date = $_POST['date'] ?? '';
    if ($date && !empty($depositIds)) {
        $values = [];
        $placeholders = [];
        foreach ($depositIds as $id) {
            $placeholders[] = "(?, ?)";
            $values[] = $id;
            $values[] = $date;
        }
        // Build and execute one multi-row INSERT query
        $sql = "INSERT IGNORE INTO recurring_deposit_exclusions (deposit_id, date) VALUES " . implode(", ", $placeholders);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
    }
    echo "Excluded";
    exit;
}
?>
