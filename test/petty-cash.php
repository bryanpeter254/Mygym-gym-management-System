<?php
// petty-cash.php - Main petty cash management page
include_once 'includes/header.php';

// Initialize variables
$message = '';
$message_type = '';
$current_month = date('Y-m');
$current_year = date('Y');

// Get filter parameters
$transaction_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_month = isset($_GET['month']) ? $_GET['month'] : $current_month;
$filter_year = isset($_GET['year']) ? $_GET['year'] : $current_year;
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Check if month is closed
$is_month_closed = false;
$is_year_closed = false;
$check_month_sql = "SELECT * FROM petty_cash_closings WHERE closing_period = '$filter_month' AND closing_type = 'monthly'";
$month_result = mysqli_query($conn, $check_month_sql);
if ($month_result && mysqli_num_rows($month_result) > 0) {
    $is_month_closed = true;
    $month_closing_data = mysqli_fetch_assoc($month_result);
}

$check_year_sql = "SELECT * FROM petty_cash_closings WHERE closing_period = '$filter_year' AND closing_type = 'yearly'";
$year_result = mysqli_query($conn, $check_year_sql);
if ($year_result && mysqli_num_rows($year_result) > 0) {
    $is_year_closed = true;
    $year_closing_data = mysqli_fetch_assoc($year_result);
}

// Process transaction addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_transaction'])) {
    $transaction_date = mysqli_real_escape_string($conn, $_POST['transaction_date']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $amount = (float)$_POST['amount'];
    $transaction_type = mysqli_real_escape_string($conn, $_POST['transaction_type']);
    $category = isset($_POST['category']) ? (int)$_POST['category'] : null;
    $reference = mysqli_real_escape_string($conn, $_POST['reference'] ?? '');
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method'] ?? '');
    $recorded_by = mysqli_real_escape_string($conn, $_POST['recorded_by'] ?? 'Admin');
    
    // Ensure amount is positive in database, type determines if it's income or expense
    if ($amount <= 0) {
        $message = "Amount must be greater than zero.";
        $message_type = "danger";
    } else {
        // Check if the month is closed
        $trans_month = date('Y-m', strtotime($transaction_date));
        $trans_year = date('Y', strtotime($transaction_date));
        
        $check_trans_month_sql = "SELECT id FROM petty_cash_closings WHERE closing_period = '$trans_month' AND closing_type = 'monthly'";
        $check_trans_year_sql = "SELECT id FROM petty_cash_closings WHERE closing_period = '$trans_year' AND closing_type = 'yearly'";
        
        $trans_month_closed = mysqli_query($conn, $check_trans_month_sql) && mysqli_num_rows(mysqli_query($conn, $check_trans_month_sql)) > 0;
        $trans_year_closed = mysqli_query($conn, $check_trans_year_sql) && mysqli_num_rows(mysqli_query($conn, $check_trans_year_sql)) > 0;
        
        if ($trans_month_closed || $trans_year_closed) {
            $message = "Cannot add transaction. The selected period has been closed.";
            $message_type = "danger";
        } else {
            $insert_sql = "INSERT INTO petty_cash_transactions 
                          (transaction_date, description, amount, transaction_type, category, reference_number, payment_method, recorded_by) 
                          VALUES 
                          ('$transaction_date', '$description', $amount, '$transaction_type', " . 
                          ($category ? $category : "NULL") . ", '$reference', '$payment_method', '$recorded_by')";
            
            if (mysqli_query($conn, $insert_sql)) {
                $message = "Transaction recorded successfully!";
                $message_type = "success";
            } else {
                $message = "Error recording transaction: " . mysqli_error($conn);
                $message_type = "danger";
            }
        }
    }
}

// Process month closing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_month'])) {
    $close_month = mysqli_real_escape_string($conn, $_POST['closing_month']);
    $notes = mysqli_real_escape_string($conn, $_POST['closing_notes'] ?? '');
    $closed_by = mysqli_real_escape_string($conn, $_POST['closed_by'] ?? 'Admin');
    
    // Check if month is already closed
    $check_sql = "SELECT id FROM petty_cash_closings WHERE closing_period = '$close_month' AND closing_type = 'monthly'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $message = "This month is already closed.";
        $message_type = "warning";
    } else {
        // Calculate totals for the month
        // Start with previous month's closing balance or 0 if none
        $month_parts = explode('-', $close_month);
        $year = $month_parts[0];
        $month = $month_parts[1];
        
        // Get previous month
        $prev_month = date('Y-m', strtotime($close_month . '-01 -1 month'));
        
        // Get previous month's closing balance
        $prev_month_sql = "SELECT closing_balance FROM petty_cash_closings WHERE closing_period = '$prev_month' AND closing_type = 'monthly'";
        $prev_month_result = mysqli_query($conn, $prev_month_sql);
        $opening_balance = 0;
        
        if ($prev_month_result && mysqli_num_rows($prev_month_result) > 0) {
            $prev_month_data = mysqli_fetch_assoc($prev_month_result);
            $opening_balance = $prev_month_data['closing_balance'];
        }
        
        // Calculate income and expenses for the month
        $income_sql = "SELECT SUM(amount) as total FROM petty_cash_transactions 
                      WHERE transaction_type = 'income' 
                      AND DATE_FORMAT(transaction_date, '%Y-%m') = '$close_month'";
        $expense_sql = "SELECT SUM(amount) as total FROM petty_cash_transactions 
                       WHERE transaction_type = 'expense' 
                       AND DATE_FORMAT(transaction_date, '%Y-%m') = '$close_month'";
        
        $income_result = mysqli_query($conn, $income_sql);
        $expense_result = mysqli_query($conn, $expense_sql);
        
        $total_income = 0;
        $total_expense = 0;
        
        if ($income_result && $income_data = mysqli_fetch_assoc($income_result)) {
            $total_income = $income_data['total'] ?: 0;
        }
        
        if ($expense_result && $expense_data = mysqli_fetch_assoc($expense_result)) {
            $total_expense = $expense_data['total'] ?: 0;
        }
        
        $closing_balance = $opening_balance + $total_income - $total_expense;
        
        // Insert the closing record
        $close_sql = "INSERT INTO petty_cash_closings 
                     (closing_period, closing_type, opening_balance, total_income, total_expense, closing_balance, closed_by, notes) 
                     VALUES 
                     ('$close_month', 'monthly', $opening_balance, $total_income, $total_expense, $closing_balance, '$closed_by', '$notes')";
        
        if (mysqli_query($conn, $close_sql)) {
            $message = "Month successfully closed.";
            $message_type = "success";
        } else {
            $message = "Error closing month: " . mysqli_error($conn);
            $message_type = "danger";
        }
    }
}

// Process year closing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_year'])) {
    $close_year = mysqli_real_escape_string($conn, $_POST['closing_year']);
    $notes = mysqli_real_escape_string($conn, $_POST['closing_notes'] ?? '');
    $closed_by = mysqli_real_escape_string($conn, $_POST['closed_by'] ?? 'Admin');
    
    // Check if year is already closed
    $check_sql = "SELECT id FROM petty_cash_closings WHERE closing_period = '$close_year' AND closing_type = 'yearly'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $message = "This year is already closed.";
        $message_type = "warning";
    } else {
        // Calculate totals for the year
        // Start with previous year's closing balance or 0 if none
        $prev_year = $close_year - 1;
        
        // Get previous year's closing balance
        $prev_year_sql = "SELECT closing_balance FROM petty_cash_closings WHERE closing_period = '$prev_year' AND closing_type = 'yearly'";
        $prev_year_result = mysqli_query($conn, $prev_year_sql);
        $opening_balance = 0;
        
        if ($prev_year_result && mysqli_num_rows($prev_year_result) > 0) {
            $prev_year_data = mysqli_fetch_assoc($prev_year_result);
            $opening_balance = $prev_year_data['closing_balance'];
        }
        
        // Calculate income and expenses for the year
        $income_sql = "SELECT SUM(amount) as total FROM petty_cash_transactions 
                      WHERE transaction_type = 'income' 
                      AND YEAR(transaction_date) = '$close_year'";
        $expense_sql = "SELECT SUM(amount) as total FROM petty_cash_transactions 
                       WHERE transaction_type = 'expense' 
                       AND YEAR(transaction_date) = '$close_year'";
        
        $income_result = mysqli_query($conn, $income_sql);
        $expense_result = mysqli_query($conn, $expense_sql);
        
        $total_income = 0;
        $total_expense = 0;
        
        if ($income_result && $income_data = mysqli_fetch_assoc($income_result)) {
            $total_income = $income_data['total'] ?: 0;
        }
        
        if ($expense_result && $expense_data = mysqli_fetch_assoc($expense_result)) {
            $total_expense = $expense_data['total'] ?: 0;
        }
        
        $closing_balance = $opening_balance + $total_income - $total_expense;
        
        // Insert the closing record
        $close_sql = "INSERT INTO petty_cash_closings 
                     (closing_period, closing_type, opening_balance, total_income, total_expense, closing_balance, closed_by, notes) 
                     VALUES 
                     ('$close_year', 'yearly', $opening_balance, $total_income, $total_expense, $closing_balance, '$closed_by', '$notes')";
        
        if (mysqli_query($conn, $close_sql)) {
            $message = "Year successfully closed.";
            $message_type = "success";
        } else {
            $message = "Error closing year: " . mysqli_error($conn);
            $message_type = "danger";
        }
    }
}

// Build query for displaying transactions
$query = "SELECT t.*, c.name as category_name 
          FROM petty_cash_transactions t
          LEFT JOIN petty_cash_categories c ON t.category = c.id
          WHERE 1=1";

// Apply transaction type filter
if ($transaction_type !== 'all') {
    $query .= " AND t.transaction_type = '$transaction_type'";
}

// Apply month/year filter
if (!empty($filter_month)) {
    $query .= " AND DATE_FORMAT(t.transaction_date, '%Y-%m') = '$filter_month'";
} elseif (!empty($filter_year)) {
    $query .= " AND YEAR(t.transaction_date) = '$filter_year'";
}

// Apply category filter
if ($category_filter > 0) {
    $query .= " AND t.category = $category_filter";
}

// Order by date, latest first
$query .= " ORDER BY t.transaction_date DESC, t.id DESC";

// Execute query
$transactions_result = mysqli_query($conn, $query);

// Get categories for dropdown
$income_cats_sql = "SELECT * FROM petty_cash_categories WHERE type = 'income' ORDER BY name";
$expense_cats_sql = "SELECT * FROM petty_cash_categories WHERE type = 'expense' ORDER BY name";
$income_cats_result = mysqli_query($conn, $income_cats_sql);
$expense_cats_result = mysqli_query($conn, $expense_cats_sql);

// Calculate summary for current filter
$summary_query = "SELECT 
                  SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as total_income,
                  SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as total_expense
                  FROM petty_cash_transactions
                  WHERE 1=1";

// Apply month/year filter to summary
if (!empty($filter_month)) {
    $summary_query .= " AND DATE_FORMAT(transaction_date, '%Y-%m') = '$filter_month'";
} elseif (!empty($filter_year)) {
    $summary_query .= " AND YEAR(transaction_date) = '$filter_year'";
}

// Apply category filter to summary
if ($category_filter > 0) {
    $summary_query .= " AND category = $category_filter";
}

$summary_result = mysqli_query($conn, $summary_query);
$summary = mysqli_fetch_assoc($summary_result);

// Get available months and years for filter dropdown
$months_query = "SELECT DISTINCT DATE_FORMAT(transaction_date, '%Y-%m') as month_year FROM petty_cash_transactions ORDER BY month_year DESC";
$years_query = "SELECT DISTINCT YEAR(transaction_date) as year FROM petty_cash_transactions ORDER BY year DESC";
$months_result = mysqli_query($conn, $months_query);
$years_result = mysqli_query($conn, $years_query);

// Get all categories for filter dropdown
$all_cats_sql = "SELECT * FROM petty_cash_categories ORDER BY name";
$all_cats_result = mysqli_query($conn, $all_cats_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Petty Cash Management</title>
    <!-- Bootstrap CSS is included in header.php -->
    <style>
        .income-amount {
            color: #28a745;
            font-weight: bold;
        }
        .expense-amount {
            color: #dc3545;
            font-weight: bold;
        }
        .summary-card {
            background-color: #f8f9fa;
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .summary-box {
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 15px;
            color: white;
        }
        .income-box {
            background-color: #28a745;
        }
        .expense-box {
            background-color: #dc3545;
        }
        .balance-box {
            background-color: #007bff;
        }
        .category-icon {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-right: 10px;
        }
        .income-icon {
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        .expense-icon {
            background-color: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        .closed-period-badge {
            background-color: #6c757d;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-left: 5px;
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Petty Cash Management</h1>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                <i class="fas fa-plus-circle me-2"></i> Add Transaction
            </button>
            <div class="btn-group ms-2">
                <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cog me-2"></i> Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#closeMonthModal">Close Current Month</a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#closeYearModal">Close Current Year</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="petty-cash-categories.php">Manage Categories</a></li>
                    <li><a class="dropdown-item" href="petty-cash-reports.php">Generate Reports</a></li>
                </ul>
            </div>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="summary-box income-box">
                <h4>Total Income</h4>
                <h2>KES <?php echo number_format($summary['total_income'] ?? 0, 2); ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-box expense-box">
                <h4>Total Expenses</h4>
                <h2>KES <?php echo number_format($summary['total_expense'] ?? 0, 2); ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-box balance-box">
                <h4>Net Balance</h4>
                <h2>KES <?php echo number_format(($summary['total_income'] ?? 0) - ($summary['total_expense'] ?? 0), 2); ?></h2>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="type" class="form-label">Transaction Type</label>
                    <select class="form-select" id="type" name="type">
                        <option value="all" <?php echo $transaction_type === 'all' ? 'selected' : ''; ?>>All Transactions</option>
                        <option value="income" <?php echo $transaction_type === 'income' ? 'selected' : ''; ?>>Income Only</option>
                        <option value="expense" <?php echo $transaction_type === 'expense' ? 'selected' : ''; ?>>Expenses Only</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="month" class="form-label">Month</label>
                    <select class="form-select" id="month" name="month">
                        <option value="">All Months</option>
                        <?php if ($months_result && mysqli_num_rows($months_result) > 0): ?>
                            <?php while ($month_row = mysqli_fetch_assoc($months_result)): ?>
                                <?php 
                                    $month_display = date('F Y', strtotime($month_row['month_year'] . '-01'));
                                    $is_closed = false;
                                    $check_closed = mysqli_query($conn, "SELECT id FROM petty_cash_closings WHERE closing_period = '{$month_row['month_year']}' AND closing_type = 'monthly'");
                                    if ($check_closed && mysqli_num_rows($check_closed) > 0) {
                                        $is_closed = true;
                                    }
                                ?>
                                <option value="<?php echo $month_row['month_year']; ?>" 
                                        <?php echo $filter_month === $month_row['month_year'] ? 'selected' : ''; ?>>
                                    <?php echo $month_display; ?>
                                    <?php if ($is_closed): ?> (Closed)<?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="year" class="form-label">Year</label>
                    <select class="form-select" id="year" name="year">
                        <option value="">All Years</option>
                        <?php if ($years_result && mysqli_num_rows($years_result) > 0): ?>
                            <?php while ($year_row = mysqli_fetch_assoc($years_result)): ?>
                                <?php 
                                    $is_closed = false;
                                    $check_closed = mysqli_query($conn, "SELECT id FROM petty_cash_closings WHERE closing_period = '{$year_row['year']}' AND closing_type = 'yearly'");
                                    if ($check_closed && mysqli_num_rows($check_closed) > 0) {
                                        $is_closed = true;
                                    }
                                ?>
                                <option value="<?php echo $year_row['year']; ?>" 
                                        <?php echo $filter_year === $year_row['year'] ? 'selected' : ''; ?>>
                                    <?php echo $year_row['year']; ?>
                                    <?php if ($is_closed): ?> (Closed)<?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="0">All Categories</option>
                        <?php if ($all_cats_result && mysqli_num_rows($all_cats_result) > 0): ?>
                            <optgroup label="Income Categories">
                                <?php 
                                mysqli_data_seek($all_cats_result, 0);
                                while ($cat = mysqli_fetch_assoc($all_cats_result)):
                                    if ($cat['type'] === 'income'):
                                ?>
                                    <option value="<?php echo $cat['id']; ?>" 
                                            <?php echo $category_filter === (int)$cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php 
                                    endif;
                                endwhile; 
                                ?>
                            </optgroup>
                            <optgroup label="Expense Categories">
                                <?php 
                                mysqli_data_seek($all_cats_result, 0);
                                while ($cat = mysqli_fetch_assoc($all_cats_result)):
                                    if ($cat['type'] === 'expense'):
                                ?>
                                    <option value="<?php echo $cat['id']; ?>" 
                                            <?php echo $category_filter === (int)$cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php 
                                    endif;
                                endwhile; 
                                ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-2"></i> Apply Filters
                    </button>
                    <a href="petty-cash.php" class="btn btn-outline-secondary">
                        <i class="fas fa-undo me-2"></i> Reset
                    </a>
                    
                    <?php if ($is_month_closed || $is_year_closed): ?>
                        <div class="float-end">
                            <?php if ($is_month_closed): ?>
                                <span class="closed-period-badge">
                                    <i class="fas fa-lock me-1"></i> Month Closed
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($is_year_closed): ?>
                                <span class="closed-period-badge">
                                    <i class="fas fa-lock me-1"></i> Year Closed
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Transactions Table -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0">Transaction List</h5>
        </div>
        <div class="card-body">
            <?php if ($transactions_result && mysqli_num_rows($transactions_result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($transaction = mysqli_fetch_assoc($transactions_result)): ?>
                                <tr>
                                    <td><?php echo date('d-M-Y', strtotime($transaction['transaction_date'])); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="category-icon <?php echo $transaction['transaction_type'] === 'income' ? 'income-icon' : 'expense-icon'; ?>">
                                                <i class="fas <?php echo $transaction['transaction_type'] === 'income' ? 'fa-arrow-down' : 'fa-arrow-up'; ?>"></i>
                                            </div>
                                            <?php echo htmlspecialchars($transaction['description']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($transaction['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['reference_number']); ?></td>
                                    <td class="<?php echo $transaction['transaction_type'] === 'income' ? 'income-amount' : 'expense-amount'; ?>">
                                        <?php echo $transaction['transaction_type'] === 'income' ? '+' : '-'; ?> 
                                        KES <?php echo number_format($transaction['amount'], 2); ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-primary view-btn" 
                                                   data-id="<?php echo $transaction['id']; ?>"
                                                   data-bs-toggle="tooltip" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php 
                                            // Check if transaction can be edited (not in closed period)
                                            $trans_month = date('Y-m', strtotime($transaction['transaction_date']));
                                            $trans_year = date('Y', strtotime($transaction['transaction_date']));
                                            $is_trans_month_closed = false;
                                            $is_trans_year_closed = false;
                                            
                                            $check_trans_month = mysqli_query($conn, "SELECT id FROM petty_cash_closings WHERE closing_period = '$trans_month' AND closing_type = 'monthly'");
                                            $check_trans_year = mysqli_query($conn, "SELECT id FROM petty_cash_closings WHERE closing_period = '$trans_year' AND closing_type = 'yearly'");
                                            
                                            if ($check_trans_month && mysqli_num_rows($check_trans_month) > 0) {
                                                $is_trans_month_closed = true;
                                            }
                                            
                                            if ($check_trans_year && mysqli_num_rows($check_trans_year) > 0) {
                                                $is_trans_year_closed = true;
                                            }
                                            
                                            if (!$is_trans_month_closed && !$is_trans_year_closed):
                                            ?>
                                            <button type="button" class="btn btn-sm btn-warning edit-btn"
                                                   data-id="<?php echo $transaction['id']; ?>"
                                                   data-bs-toggle="tooltip" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <button type="button" class="btn btn-sm btn-danger delete-btn"
                                                   data-id="<?php echo $transaction['id']; ?>"
                                                   data-bs-toggle="tooltip" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-secondary" disabled
                                                   data-bs-toggle="tooltip" title="Period Closed">
                                                <i class="fas fa-lock"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No transactions found for the selected filters.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Transaction Modal -->
<div class="modal fade" id="addTransactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Transaction</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="transactionForm">
                    <input type="hidden" name="add_transaction" value="1">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="transaction_date" class="form-label">Transaction Date *</label>
                            <input type="date" class="form-control" id="transaction_date" name="transaction_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="transaction_type" class="form-label">Transaction Type *</label>
                            <select class="form-select" id="transaction_type" name="transaction_type" required>
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="amount" class="form-label">Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text">KES</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category_select" name="category">
                                <option value="">-- Select Category --</option>
                                <!-- Income categories will be loaded here -->
                                <optgroup label="Income Categories" id="income_categories">
                                    <?php if ($income_cats_result && mysqli_num_rows($income_cats_result) > 0): ?>
                                        <?php while ($cat = mysqli_fetch_assoc($income_cats_result)): ?>
                                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </optgroup>
                                
                                <!-- Expense categories will be loaded here -->
                                <optgroup label="Expense Categories" id="expense_categories" style="display:none;">
                                    <?php if ($expense_cats_result && mysqli_num_rows($expense_cats_result) > 0): ?>
                                        <?php while ($cat = mysqli_fetch_assoc($expense_cats_result)): ?>
                                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </optgroup>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description *</label>
                        <input type="text" class="form-control" id="description" name="description" required>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="reference" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="reference" name="reference" placeholder="Receipt #, Invoice #, etc.">
                        </div>
                        <div class="col-md-6">
                            <label for="payment_method" class="form-label">Payment Method</label>
                            <select class="form-select" id="payment_method" name="payment_method">
                                <option value="Cash">Cash</option>
                                <option value="M-Pesa">M-Pesa</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Check">Check</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="recorded_by" class="form-label">Recorded By</label>
                        <input type="text" class="form-control" id="recorded_by" name="recorded_by" value="Admin">
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save Transaction
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Close Month Modal -->
<div class="modal fade" id="closeMonthModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-calendar-check me-2"></i> Close Current Month</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="closeMonthForm">
                    <input type="hidden" name="close_month" value="1">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> 
                        <strong>Warning:</strong> Closing a month will prevent adding, editing, or deleting transactions for that period.
                        This action cannot be undone.
                    </div>
                    
                    <div class="mb-3">
                        <label for="closing_month" class="form-label">Month to Close</label>
                        <select class="form-select" id="closing_month" name="closing_month" required>
                            <?php
                            // Show last 6 months
                            for ($i = 0; $i < 6; $i++) {
                                $month = date('Y-m', strtotime("-$i month"));
                                $month_display = date('F Y', strtotime($month . '-01'));
                                
                                // Check if this month is already closed
                                $is_closed = false;
                                $check_closed = mysqli_query($conn, "SELECT id FROM petty_cash_closings WHERE closing_period = '$month' AND closing_type = 'monthly'");
                                if ($check_closed && mysqli_num_rows($check_closed) > 0) {
                                    $is_closed = true;
                                }
                                
                                if (!$is_closed) {
                                    echo "<option value='$month' " . ($i === 0 ? 'selected' : '') . ">$month_display</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="closing_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="closing_notes" name="closing_notes" rows="3" 
                                  placeholder="Any notes or comments about this month's closing"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="closed_by" class="form-label">Closed By</label>
                        <input type="text" class="form-control" id="closed_by" name="closed_by" value="Admin">
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-lock me-2"></i> Close Month
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Close Year Modal -->
<div class="modal fade" id="closeYearModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-calendar-check me-2"></i> Close Current Year</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="closeYearForm">
                    <input type="hidden" name="close_year" value="1">
                    
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i> 
                        <strong>Warning:</strong> Closing a year will prevent adding, editing, or deleting transactions for that entire year.
                        This action cannot be undone.
                    </div>
                    
                    <div class="mb-3">
                        <label for="closing_year" class="form-label">Year to Close</label>
                        <select class="form-select" id="closing_year" name="closing_year" required>
                            <?php
                            // Show last 3 years
                            $current_year = date('Y');
                            for ($i = 0; $i < 3; $i++) {
                                $year = $current_year - $i;
                                
                                // Check if this year is already closed
                                $is_closed = false;
                                $check_closed = mysqli_query($conn, "SELECT id FROM petty_cash_closings WHERE closing_period = '$year' AND closing_type = 'yearly'");
                                if ($check_closed && mysqli_num_rows($check_closed) > 0) {
                                    $is_closed = true;
                                }
                                
                                if (!$is_closed) {
                                    echo "<option value='$year' " . ($i === 0 ? 'selected' : '') . ">$year</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="year_closing_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="year_closing_notes" name="closing_notes" rows="3" 
                                  placeholder="Any notes or comments about this year's closing"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="year_closed_by" class="form-label">Closed By</label>
                        <input type="text" class="form-control" id="year_closed_by" name="closed_by" value="Admin">
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-lock me-2"></i> Close Year
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Transaction Modal (will be populated with AJAX) -->
<div class="modal fade" id="viewTransactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i> Transaction Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="transactionDetails">
                    <!-- Transaction details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle transaction type change to show appropriate categories
    const transactionType = document.getElementById('transaction_type');
    const incomeCategories = document.getElementById('income_categories');
    const expenseCategories = document.getElementById('expense_categories');
    
    transactionType.addEventListener('change', function() {
        if (this.value === 'income') {
            incomeCategories.style.display = 'block';
            expenseCategories.style.display = 'none';
        } else {
            incomeCategories.style.display = 'none';
            expenseCategories.style.display = 'block';
        }
    });
    
    // Handle month/year filter sync
    const monthFilter = document.getElementById('month');
    const yearFilter = document.getElementById('year');
    
    monthFilter.addEventListener('change', function() {
        if (this.value) {
            yearFilter.value = '';
        }
    });
    
    yearFilter.addEventListener('change', function() {
        if (this.value) {
            monthFilter.value = '';
        }
    });
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // View transaction details
    const viewButtons = document.querySelectorAll('.view-btn');
    const viewModal = new bootstrap.Modal(document.getElementById('viewTransactionModal'));
    const transactionDetails = document.getElementById('transactionDetails');
    
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const transactionId = this.dataset.id;
            
            // You would typically use AJAX to fetch the transaction details
            // For simplicity, we'll use a placeholder
            transactionDetails.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Loading transaction details...</p></div>';
            
            // Simulate AJAX call
            setTimeout(() => {
                // Find the transaction data from the table
                const row = this.closest('tr');
                const date = row.cells[0].textContent;
                const description = row.cells[1].textContent.trim();
                const category = row.cells[2].textContent;
                const reference = row.cells[3].textContent;
                const amount = row.cells[4].textContent;
                
                transactionDetails.innerHTML = `
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">${description}</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Date:</span>
                                    <strong>${date}</strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Amount:</span>
                                    <strong>${amount}</strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Category:</span>
                                    <span>${category}</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Reference:</span>
                                    <span>${reference}</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Transaction ID:</span>
                                    <span>${transactionId}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                `;
            }, 500);
            
            viewModal.show();
        });
    });
    
    // In a real application, you would implement edit and delete functionality using AJAX calls
});
</script>

<?php
include_once 'includes/footer.php';
?>