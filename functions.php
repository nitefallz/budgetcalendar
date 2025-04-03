<?php
// functions.php

if (!function_exists('array_key_last')) {
    function array_key_last(array $arr) {
        if (!empty($arr)) {
            $keys = array_keys($arr);
            return $keys[count($arr) - 1];
        }
        return null;
    }
}

function getRecurringBillsTotals($pdo) {
    $stmt = $pdo->query("SELECT day, SUM(ABS(amount)) AS total FROM bills GROUP BY day");
    $totals = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $totals[intval($row['day'])] = floatval($row['total']);
    }
    return $totals;
}

function getTransactionsForDate($pdo, DateTime $date) {
    $dateStr = $date->format('Y-m-d');
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE date = ?");
    $stmt->execute([$dateStr]);
    $all = $stmt->fetchAll();
    $deposits = [];
    $expenses = [];
    foreach ($all as $tran) {
        if ($tran['type'] === 'deposit') {
            $deposits[] = $tran;
        } elseif ($tran['type'] === 'expense') {
            $expenses[] = $tran;
        }
    }
    return ['deposits' => $deposits, 'expenses' => $expenses];
}

function getTransactionsForDateSafe($pdo, DateTime $date) {
    $dateStr = $date->format('Y-m-d');
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE date = ?");
    $stmt->execute([$dateStr]);
    return $stmt->fetchAll();
}

function getDailyOverride($pdo, DateTime $date) {
    $dateStr = $date->format('Y-m-d');
    $stmt = $pdo->prepare("SELECT override_amount FROM daily_overrides WHERE date = ?");
    $stmt->execute([$dateStr]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? floatval($row['override_amount']) : null;
}

function getDailyDepositOverride($pdo, DateTime $date) {
    $dateStr = $date->format('Y-m-d');
    $stmt = $pdo->prepare("SELECT override_amount FROM daily_deposit_overrides WHERE date = ?");
    $stmt->execute([$dateStr]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? floatval($row['override_amount']) : null;
}

function getDailyExpenseOverride($pdo, DateTime $date) {
    $dateStr = $date->format('Y-m-d');
    $stmt = $pdo->prepare("SELECT override_amount FROM daily_expense_overrides WHERE date = ?");
    $stmt->execute([$dateStr]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? floatval($row['override_amount']) : null;
}

function getRecurringDepositsByDate($pdo, DateTime $start, DateTime $end) {
    $stmt = $pdo->query("SELECT * FROM recurring_deposits");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results = [];
    $exclusions = getRecurringDepositExclusions($pdo);
    $overrides = getRecurringDepositOverrides($pdo);
    foreach ($rows as $row) {
        $startDate = new DateTime($row['start_date']);
        $current = clone $startDate;
        $defaultAmount = floatval($row['amount']);
        $frequency = $row['frequency'];
        $depositId = $row['id'];
        while ($current <= $end) {
            if ($current >= $start) {
                $dateStr = $current->format('Y-m-d');
                $excludeKey = $depositId . ':' . $dateStr;
                if (!isset($exclusions[$excludeKey])) {
                    $overrideKey = $depositId . ':' . $dateStr;
                    $amount = isset($overrides[$overrideKey]) ? $overrides[$overrideKey] : $defaultAmount;
                    if (!isset($results[$dateStr])) {
                        $results[$dateStr] = 0;
                    }
                    $results[$dateStr] += $amount;
                }
            }
            if ($frequency === 'weekly') {
                $current->modify('+1 week');
            } elseif ($frequency === 'biweekly') {
                $current->modify('+2 weeks');
            } elseif ($frequency === 'monthly') {
                $current->modify('+1 month');
            } else {
                break;
            }
        }
    }
    return $results;
}

function getRecurringDepositExclusions($pdo) {
    $stmt = $pdo->query("SELECT deposit_id, date FROM recurring_deposit_exclusions");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($rows as $row) {
        $map[$row['deposit_id'] . ':' . $row['date']] = true;
    }
    return $map;
}

function getRecurringDepositOverrides($pdo) {
    $stmt = $pdo->query("SELECT deposit_id, date, override_amount FROM recurring_deposit_overrides");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($rows as $row) {
        $map[$row['deposit_id'] . ':' . $row['date']] = floatval($row['override_amount']);
    }
    return $map;
}

function getPreviousMonthEndingBalance($pdo, DateTime $firstDay) {
    $initialBalance = 1000.00;
    $prevFirst = (clone $firstDay)->modify('first day of last month');
    $prevLast = (clone $firstDay)->modify('last day of last month');
    $year = intval($prevFirst->format('Y'));
    $month = intval($prevFirst->format('n'));
    $stmt = $pdo->prepare("SELECT ending_balance FROM month_balances WHERE year = ? AND month = ?");
    $stmt->execute([$year, $month]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return floatval($row['ending_balance']);
    }
    $priorBalance = ($prevFirst->format('Y-m') > '2000-01')
        ? getPreviousMonthEndingBalance($pdo, $prevFirst)
        : $initialBalance;
    $results = calculateBalances($pdo, $prevFirst, $prevLast, $priorBalance);
    $finalBalance = !empty($results)
        ? $results[array_key_last($results)]['ending']
        : $priorBalance;
    $stmt = $pdo->prepare("INSERT INTO month_balances (year, month, ending_balance) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE ending_balance = VALUES(ending_balance)");
    $stmt->execute([$year, $month, $finalBalance]);
    return $finalBalance;
}

// Updated calculateBalances function in functions.php
function calculateBalances($pdo, DateTime $start, DateTime $end, $initialBalance) {
    $recurringBills = getRecurringBillsTotals($pdo);
    $recurringDeposits = getRecurringDepositsByDate($pdo, $start, $end);
    $weeklyDeductions = getWeeklyDeductionsMap($pdo, $start, $end);
    $balance = $initialBalance;
    $results = [];
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, (clone $end)->modify('+1 day'));
    foreach ($period as $date) {
        $dateStr = $date->format('Y-m-d');
        $override = getDailyOverride($pdo, $date);
        if ($override !== null) {
            // If a daily balance override exists, use it as the final balance for the day
            $results[$dateStr] = [
                'starting' => $override,
                'deposits' => 0,
                'recurring_deposits' => 0,
                'expenses' => 0,
                'recurring' => 0,
                'total_expenses' => 0,
                'ending' => $override,
                'override' => $override,
                'deposit_override' => null,
                'expense_override' => null,
                'transactions' => []
            ];
            $balance = $override;
            continue;
        }
        // Otherwise, perform normal calculations.
        $transactions = getTransactionsForDateSafe($pdo, $date);
        $deposits = 0;
        $expenses = 0;
        foreach ($transactions as $tran) {
            if ($tran['type'] === 'deposit') {
                $deposits += floatval($tran['amount']);
            } elseif ($tran['type'] === 'expense') {
                $expenses += floatval($tran['amount']);
            }
        }
        $depositOverride = getDailyDepositOverride($pdo, $date);
        if ($depositOverride !== null) {
            $deposits = $depositOverride;
        }
        $expenseOverride = getDailyExpenseOverride($pdo, $date);
        if ($expenseOverride !== null) {
            $expenses = $expenseOverride;
        }
        $dayNum = intval($date->format('j'));
        $recurring = $recurringBills[$dayNum] ?? 0;
        $recurringDep = $recurringDeposits[$dateStr] ?? 0;
        $starting = $balance;
        $ending = $starting + $deposits + $recurringDep - $expenses - $recurring;
        if (isset($weeklyDeductions[$dateStr])) {
            $ending -= $weeklyDeductions[$dateStr];
        }
        $results[$dateStr] = [
            'starting' => $starting,
            'deposits' => $deposits,
            'recurring_deposits' => $recurringDep,
            'expenses' => $expenses,
            'recurring' => $recurring,
            'total_expenses' => $expenses + $recurring,
            'ending' => $ending,
            'override' => $override,
            'deposit_override' => $depositOverride,
            'expense_override' => $expenseOverride,
            'transactions' => $transactions
        ];
        $balance = $ending;
    }
    return $results;
}

function clearMonthBalanceCache($pdo, $year = null, $month = null) {
    if ($year && $month) {
        $stmt = $pdo->prepare("DELETE FROM month_balances WHERE year = ? AND month = ?");
        $stmt->execute([$year, $month]);
    } else {
        $pdo->exec("DELETE FROM month_balances");
    }
}

function getDayNote($pdo, $dateStr) {
    $stmt = $pdo->prepare("SELECT note FROM day_notes WHERE date = ?");
    $stmt->execute([$dateStr]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['note'] : '';
}

function setDayNote($pdo, $dateStr, $note) {
    $stmt = $pdo->prepare("REPLACE INTO day_notes (date, note) VALUES (?, ?)");
    $stmt->execute([$dateStr, $note]);
}

function getBillNamesByDay($pdo) {
    $stmt = $pdo->query("SELECT day, name FROM bills");
    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $day = intval($row['day']);
        if (!isset($map[$day])) {
            $map[$day] = [];
        }
        $map[$day][] = $row['name'];
    }
    return $map;
}

function getWeeklyExpenses(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM weekly_expenses ORDER BY label ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getWeeklyOverrides(PDO $pdo, string $sundayDate): array {
    $stmt = $pdo->prepare("SELECT * FROM weekly_expense_overrides WHERE week_start = ?");
    $stmt->execute([$sundayDate]);
    $overrides = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($overrides as $row) {
        $map[$row['label']] = $row['amount'];
    }
    return $map;
}

function getWeeklyExpenseTotal($pdo) {
    $stmt = $pdo->query("SELECT SUM(ABS(amount)) AS total FROM weekly_expenses");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? floatval($row['total']) : 0.0;
}

function calculateWeeklyDeductions(PDO $pdo, DateTime $date): float {
    if ($date->format('w') !== '0') return 0.0;
    $sundayStr = $date->format('Y-m-d');
    $defaults = getWeeklyExpenses($pdo);
    $overrides = getWeeklyOverrides($pdo, $sundayStr);
    $total = 0.0;
    foreach ($defaults as $item) {
        $label = $item['label'];
        $base = floatval($item['amount']);
        $adjusted = isset($overrides[$label]) ? floatval($overrides[$label]) : $base;
        $total += abs($adjusted);
    }
    return $total;
}

function applyWeeklyDeductionsToBalance(float $currentBalance, float $weeklyDeduction): float {
    return $currentBalance - $weeklyDeduction;
}

function getWeeklyDeductionsMap(PDO $pdo, DateTime $start, DateTime $end): array {
    $map = [];
    $period = new DatePeriod($start, new DateInterval('P1D'), (clone $end)->modify('+1 day'));
    foreach ($period as $date) {
        if ($date->format('w') === '0') {
            $map[$date->format('Y-m-d')] = calculateWeeklyDeductions($pdo, $date);
        }
    }
    return $map;
}
