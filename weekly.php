<?php
// weekly.php
require_once 'db.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'add':
            $label  = trim($_POST['label'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            $stmt   = $pdo->prepare("INSERT INTO weekly_expenses (label, amount) VALUES (?, ?)");
            $stmt->execute([$label, $amount]);
            break;

        case 'update':
            $id     = intval($_POST['id'] ?? 0);
            $label  = trim($_POST['label'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            $stmt   = $pdo->prepare("UPDATE weekly_expenses SET label = ?, amount = ? WHERE id = ?");
            $stmt->execute([$label, $amount, $id]);
            break;

        case 'delete':
            $id   = intval($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM weekly_expenses WHERE id = ?");
            $stmt->execute([$id]);
            break;

        default:
            // Unrecognized action; do nothing.
            break;
    }
    header("Location: weekly.php");
    exit;
}

// Fetch current weekly expenses
$stmt     = $pdo->query("SELECT * FROM weekly_expenses ORDER BY id ASC");
$expenses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Weekly Expenses</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Weekly Expenses</h1>
        <a href="index.php" class="btn btn-secondary">&larr; Back to Calendar</a>
    </div>

    <!-- Add New Weekly Entry -->
    <div class="card mb-4">
        <div class="card-header">Add New Weekly Entry</div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="action" value="add">
                <div class="col-md-6">
                    <label class="form-label">Label</label>
                    <input type="text" name="label" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Amount</label>
                    <input type="number" step="0.01" name="amount" class="form-control" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Add</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Current Weekly Entries Table -->
    <h5>Current Weekly Entries</h5>
    <table class="table table-bordered">
        <thead class="table-dark">
        <tr>
            <th>Label</th>
            <th>Amount</th>
            <th style="width: 180px">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($expenses as $exp): ?>
            <tr>
                <!-- Update Form -->
                <form method="post" class="row g-1">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo $exp['id']; ?>">
                    <td>
                        <input type="text" name="label" value="<?php echo htmlspecialchars($exp['label']); ?>" class="form-control">
                    </td>
                    <td>
                        <input type="number" step="0.01" name="amount" value="<?php echo $exp['amount']; ?>" class="form-control">
                    </td>
                    <td class="d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-success">Update</button>
                </form>
                <!-- Delete Form -->
                <form method="post">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $exp['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this entry?');">Delete</button>
                </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
