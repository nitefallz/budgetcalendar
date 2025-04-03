<?php
// index.php
require_once 'db.php';
require_once 'functions.php';
date_default_timezone_set('America/New_York');
session_start();

$year  = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');

$firstDay = new DateTime("$year-$month-01");
$lastDay  = clone $firstDay;
$lastDay->modify('last day of this month');

$recurringTotals   = getRecurringBillsTotals($pdo);
$recurringDeposits = getRecurringDepositsByDate($pdo, $firstDay, $lastDay);
$initialBalance = getPreviousMonthEndingBalance($pdo, $firstDay);
$dailyResults   = calculateBalances($pdo, $firstDay, $lastDay, $initialBalance);

$daysInMonth  = (int)$lastDay->format('d');
$firstWeekday = (int)$firstDay->format('w');
$currentYear = date('Y'); // For "Jump to Month" sidebar
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Budget Calendar - <?php echo date('F Y', strtotime("$year-$month-01")); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Custom inline styles */
        body.dark-mode {
            background-color: #121212;
            color: #e0e0e0;
        }
        body.dark-mode .table {
            color: #e0e0e0;
        }
        .calendar-day.today {
            border: 2px solid #007bff;
            background-color: #e9f7fe;
        }
        .calendar-day .line[contenteditable="true"] {
            outline: 1px dashed #ccc;
            padding: 4px;
            cursor: text;
        }
        body.dark-mode .calendar-day .line[contenteditable="true"] {
            outline-color: #888;
        }
        /* Highlight cell while editing */
        .line.editing {
            background-color: #ffffe0;
        }
        /* Delete override button styling */
        .delete-override {
            margin-left: 5px;
            color: red;
            cursor: pointer;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-light">

<div class="container my-4">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3">
        <div class="container-fluid">
            <button id="darkModeToggle" class="btn btn-sm btn-outline-dark" style="color:#fff;">Dark Mode</button>
        </div>
    </nav>
    <!-- Header & Month Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Budget Calendar - <?php echo date('F Y', strtotime("$year-$month-01")); ?></h1>
        <div class="btn-group">
            <a href="bill.php" class="btn btn-primary">Manage Bills</a>
            <a href="deposits.php" class="btn btn-success">Manage Deposits</a>
            <a href="weekly.php" class="btn btn-primary">Weekly Setup</a>
            <button id="recalcCacheBtn" class="btn btn-warning">🔄 Recalculate</button>
        </div>
    </div>
    <div class="d-flex justify-content-between mb-3">
        <?php
        $prevMonth = $month - 1;
        $prevYear  = $year;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
        $nextMonth = $month + 1;
        $nextYear  = $year;
        if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
        ?>
        <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="btn btn-secondary">&larr; Previous Month</a>
        <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>" class="btn btn-info">📅 Current Month</a>
        <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="btn btn-secondary">Next Month &rarr;</a>
    </div>
    <!-- Jump to Month Sidebar -->
    <div class="mb-3">
        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#monthSidebar">
            📅 Jump to Month
        </button>
        <div class="collapse mt-2" id="monthSidebar">
            <div class="card card-body">
                <div class="row row-cols-3 g-2">
                    <?php
                    for ($y = $currentYear - 1; $y <= $currentYear + 2; $y++) {
                        for ($m = 1; $m <= 12; $m++) {
                            $label = date('M Y', strtotime("$y-$m-01"));
                            echo "<div class='col'><a href='?month=$m&year=$y' class='btn btn-sm btn-outline-primary w-100'>$label</a></div>";
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <!-- Calendar Table -->
    <table class="table table-bordered calendar-table">
        <thead class="table-dark">
        <tr>
            <th class="text-center">Sun</th>
            <th class="text-center">Mon</th>
            <th class="text-center">Tue</th>
            <th class="text-center">Wed</th>
            <th class="text-center">Thu</th>
            <th class="text-center">Fri</th>
            <th class="text-center">Sat</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <?php
            // Pad the first row
            for ($i = 0; $i < $firstWeekday; $i++) {
                echo '<td class="calendar-day" data-date=""></td>';
            }

            $billNamesByDay = getBillNamesByDay($pdo);
            $today = date('Y-m-d');
            $currentDate = new DateTime("$year-$month-01");

            while ($currentDate->format('n') == $month) {
                $dateStr = $currentDate->format('Y-m-d');
                $dayNum = (int)$currentDate->format('j');
                $data = $dailyResults[$dateStr] ?? [
                    'ending' => 0,
                    'deposits' => 0,
                    'recurring_deposits' => 0,
                    'total_expenses' => 0
                ];
                $cellClass = ($data['ending'] < 100) ? "bg-warning text-danger" : (($data['ending'] < 500) ? "bg-danger text-white" : "");
                $isToday = ($dateStr === $today) ? " today" : "";

                echo '<td class="calendar-day ' . $cellClass . '" data-date="' . $dateStr . '">';
                echo '<div class="day-number' . $isToday . '">' . $dayNum . '</div>';
                if (!empty($billNamesByDay[$dayNum])) {
                    $notes = [];
                    foreach ($billNamesByDay[$dayNum] as $billName) {
                        $notes[] = '💡 ' . htmlspecialchars($billName);
                    }
                    echo '<div class="day-note small text-muted" style="color:white !important;" title="' . implode(', ', $notes) . '">';
                    echo implode('<br>', $notes);
                    echo '</div>';
                }

                // Inline-editable Balance cell with delete button if override exists
                echo '<div class="line current-balance" data-action="override" contenteditable="true">';
                echo 'Balance: $' . number_format($data['ending'], 2);
                if ($data['override'] !== null) {
                    echo ' <span class="delete-override" data-date="' . $dateStr . '" data-type="override">&times;</span>';
                }
                echo '</div>';

                // Inline-editable Deposits cell with delete button if deposit override exists
                $manualDeposits = $data['deposits'] ?? 0;
                echo '<div class="line deposits" data-action="deposit" contenteditable="true">';
                echo 'Deposits: $' . number_format($manualDeposits, 2);
                if (isset($data['deposit_override']) && $data['deposit_override'] !== null) {
                    echo ' <span class="delete-override" data-date="' . $dateStr . '" data-type="deposit">&times;</span>';
                }
                echo '</div>';

                // Display recurring deposits (non-editable)
                $recurringDepositsVal = $data['recurring_deposits'] ?? 0;
                if ($recurringDepositsVal > 0) {
                    echo '<div class="line recurring-deposits">';
                    echo 'Recurring: $' . number_format($recurringDepositsVal, 2);
                    echo '</div>';
                }

                // Inline-editable Expenses cell with delete button if expense override exists
                $totalExpenses = $data['total_expenses'] ?? 0;
                echo '<div class="line expenses" data-action="expense" contenteditable="true">';
                echo 'Expenses: $' . number_format($totalExpenses, 2);
                if (isset($data['expense_override']) && $data['expense_override'] !== null) {
                    echo ' <span class="delete-override" data-date="' . $dateStr . '" data-type="expense">&times;</span>';
                }
                echo '</div>';

                echo '</td>';

                // If it's Saturday, add weekly summary cell for the upcoming week (if Sunday is in the same month)
                if ($currentDate->format('w') == 6) {
                    $sunday = (clone $currentDate)->modify('+1 day');
                    if ($sunday->format('n') == $month) {
                        $sundayStr = $sunday->format('Y-m-d');
                        $weeklyItems = getWeeklyExpenses($pdo);
                        $weeklyOverrides = getWeeklyOverrides($pdo, $sundayStr);
                        $weeklyTotal = 0;
                        echo '<td class="calendar-day weekly-summary override-cell" data-date="' . $sundayStr . '">';
                        echo '<div class="day-number text-muted small text-center" style="color:#fff !important;">Weekly</div>';
                        foreach ($weeklyItems as $item) {
                            $label = $item['label'];
                            $baseAmount = abs(floatval($item['amount']));
                            $overrideAmount = isset($weeklyOverrides[$label]) ? abs(floatval($weeklyOverrides[$label])) : $baseAmount;
                            $weeklyTotal += $overrideAmount;
                            echo '<div class="line text-danger small fw-semibold">';
                            echo htmlspecialchars($label) . ': -$' . number_format($overrideAmount, 2);
                            echo '</div>';
                        }
                        echo '<div class="line fw-bold text-white bg-dark text-center">-$' . number_format($weeklyTotal, 2) . '</div>';
                        echo '</td>';
                    }
                    echo '</tr><tr>';
                }
                $currentDate->modify('+1 day');
            }

            // Pad the last row if needed
            $dayOfWeek = $currentDate->format('w');
            if ($dayOfWeek != 0) {
                for ($i = $dayOfWeek; $i < 7; $i++) {
                    echo '<td class="calendar-day" data-date=""></td>';
                }
                echo '</tr>';
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Modal Markup -->
<div id="modalOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;"></div>

<!-- Override Modal -->
<div id="overrideModal" class="modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; padding:20px; z-index:1100;">
    <h3>Set Override for <span id="overrideDate"></span></h3>
    <form id="overrideForm">
        <label>Override Value:
            <input type="text" name="override_amount" required placeholder="e.g. 3400 + 1700">
        </label>
        <input type="hidden" name="date" id="overrideInputDate">
        <br><br>
        <button type="submit" class="btn btn-primary">Save</button>
        <button type="button" id="deleteOverrideBtn" class="btn btn-danger">Delete Override</button>
        <button type="button" id="closeOverrideModal" class="btn btn-secondary">Cancel</button>
    </form>
</div>

<!-- Transactions Modal -->
<div id="transactionsModal" class="modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; padding:20px; z-index:1100;">
    <h3>Edit Transactions for <span id="transDate"></span></h3>
    <form id="transactionsForm">
        <div id="depositsSection">
            <h5>Deposits</h5>
            <div id="depositsList"></div>
            <button type="button" id="addDeposit" class="btn btn-sm btn-outline-primary">Add Deposit</button>
        </div>
        <div id="expensesSection">
            <h5>Expenses</h5>
            <div id="expensesList"></div>
            <button type="button" id="addExpense" class="btn btn-sm btn-outline-danger">Add Expense</button>
        </div>
        <br>
        <button type="submit" class="btn btn-primary">Save Transactions</button>
        <button type="button" id="closeTransModal" class="btn btn-secondary">Cancel</button>
        <input type="hidden" name="date" id="transDateInput">
    </form>
</div>

<!-- Weekly Override Modal -->
<div id="weeklyOverrideModal" class="modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; padding:20px; z-index:1100;">
    <h3>Edit Weekly Overrides for <span id="weeklyOverrideDate"></span></h3>
    <form id="weeklyOverrideForm">
        <div id="weeklyOverrideList"></div>
        <button type="button" id="addWeeklyOverride" class="btn btn-sm btn-outline-primary">Add Weekly Item</button>
        <br><br>
        <button type="submit" class="btn btn-primary">Save Overrides</button>
        <button type="button" id="closeWeeklyOverrideModal" class="btn btn-secondary">Cancel</button>
    </form>
</div>

<!-- Include JS Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
