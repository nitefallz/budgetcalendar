<?php
// deposits.php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $frequency = $_POST['frequency'] ?? 'monthly';
    $startDate = $_POST['start_date'] ?? date('Y-m-d');

    $stmt = $pdo->prepare("INSERT INTO recurring_deposits (name, amount, frequency, start_date) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $amount, $frequency, $startDate]);

    header('Location: deposits.php');
    exit;
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM recurring_deposits WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: deposits.php');
    exit;
}

$stmt = $pdo->query("SELECT * FROM recurring_deposits ORDER BY start_date ASC");
$recurringDeposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recurring Deposits</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container my-4">
    <h1 class="mb-4">Manage Recurring Deposits</h1>

    <form method="POST" class="card p-3 mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Amount</label>
                <input type="number" step="0.01" name="amount" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Frequency</label>
                <select name="frequency" class="form-select" required>
                    <option value="weekly">Weekly</option>
                    <option value="biweekly">Biweekly</option>
                    <option value="monthly" selected>Monthly</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
        </div>
        <div class="mt-3 text-end">
            <button type="submit" class="btn btn-success">Add Deposit</button>
        </div>
    </form>

    <h4>Existing Recurring Deposits</h4>
    <table class="table table-bordered">
        <thead class="table-light">
        <tr>
            <th>Name</th>
            <th>Amount</th>
            <th>Frequency</th>
            <th>Start Date</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($recurringDeposits as $deposit): ?>
            <tr>
                <td><?php echo htmlspecialchars($deposit['name']); ?></td>
                <td>$<?php echo number_format($deposit['amount'], 2); ?></td>
                <td><?php echo ucfirst($deposit['frequency']); ?></td>
                <td><?php echo $deposit['start_date']; ?></td>
                <td>
                    <a href="?delete=<?php echo $deposit['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this deposit?');">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <a href="index.php" class="btn btn-secondary mt-3">Back to Calendar</a>
</div>
</body>
</html>
