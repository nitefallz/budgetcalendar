<?php
// bill.php
require_once 'db.php';
require_once 'functions.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'add_bill':
            $name    = trim($_POST['name'] ?? '');
            $amount  = floatval($_POST['amount'] ?? 0);
            $dueDate = $_POST['due_date'] ?? '';
            // Use the day from due_date if provided; otherwise, default to 1
            $day = $dueDate ? intval(date('d', strtotime($dueDate))) : 1;

            $stmt = $pdo->prepare("INSERT INTO bills (name, amount, day) VALUES (?, ?, ?)");
            $stmt->execute([$name, $amount, $day]);
            header("Location: bill.php");
            exit;

        case 'delete_bill':
            $id = intval($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM bills WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: bill.php");
            exit;

        case 'update_bill':
            $id     = intval($_POST['id'] ?? 0);
            $name   = trim($_POST['name'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            $day    = intval($_POST['day'] ?? 1);

            $stmt = $pdo->prepare("UPDATE bills SET name = ?, amount = ?, day = ? WHERE id = ?");
            $stmt->execute([$name, $amount, $day, $id]);
            header("Location: bill.php");
            exit;

        default:
            // Unrecognized action; do nothing.
            break;
    }
}

// Fetch bills from the database
$stmt  = $pdo->query("SELECT * FROM bills ORDER BY day ASC, name ASC");
$bills = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill Manager</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Bill Manager</h1>
        <a href="index.php" class="btn btn-secondary">&larr; Back to Calendar</a>
    </div>

    <!-- Add New Recurring Bill Form -->
    <div class="card mb-4">
        <div class="card-header"><strong>Add New Recurring Bill</strong></div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="action" value="add_bill">
                <div class="col-md-5">
                    <label for="name" class="form-label">Bill Name</label>
                    <input type="text" name="name" id="name" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label for="amount" class="form-label">Amount ($)</label>
                    <input type="number" step="0.01" name="amount" id="amount" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label for="due_date" class="form-label">Due Date</label>
                    <input type="date" name="due_date" id="due_date" class="form-control" required>
                    <small class="text-muted">Only the day is used for recurrence.</small>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Add Bill</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Existing Bills Table -->
    <h5>Existing Recurring Bills</h5>
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
            <tr>
                <th>Bill Name</th>
                <th>Amount ($)</th>
                <th>Due Day</th>
                <th style="width: 180px;">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($bills as $bill): ?>
                <tr>
                    <!-- Update Bill Form -->
                    <form method="post">
                        <input type="hidden" name="action" value="update_bill">
                        <input type="hidden" name="id" value="<?php echo $bill['id']; ?>">
                        <td>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($bill['name']); ?>" class="form-control">
                        </td>
                        <td>
                            <input type="number" step="0.01" name="amount" value="<?php echo $bill['amount']; ?>" class="form-control">
                        </td>
                        <td>
                            <input type="number" name="day" value="<?php echo $bill['day']; ?>" class="form-control" min="1" max="31">
                        </td>
                        <td class="d-flex gap-1">
                            <button type="submit" class="btn btn-sm btn-success">Update</button>
                    </form>
                    <!-- Delete Bill Form -->
                    <form method="post" onsubmit="return confirm('Are you sure?');">
                        <input type="hidden" name="action" value="delete_bill">
                        <input type="hidden" name="id" value="<?php echo $bill['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
