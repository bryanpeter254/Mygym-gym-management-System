<?php
// petty-cash-reports.php - Generate reports for petty cash
include_once 'includes/header.php';

// Initialize variables
$message = '';
$message_type = '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'monthly';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$transaction_type = isset($_GET['transaction_type']) ? $_GET['transaction_type'] : 'all';

// Set date range based on report type
if ($report_type === 'monthly') {
    $start_date = date('Y-m-01', strtotime($month));
    $end_date = date('Y-m-t', strtotime($month));
} elseif ($report_type === 'yearly') {
    $start_date = $year . '-01-01';
    $end_date = $year . '-12-31';
}

// Build the query based on filters
$query = "SELECT t.*, c.name as category_name 
          FROM petty_cash_transactions t
          LEFT JOIN petty_cash_categories c ON t.category = c.id
          WHERE t.transaction_date BETWEEN '$start_date' AND '$end_date'";

// Apply transaction type filter
if ($transaction_type !== 'all') {
    $query .= " AND t.transaction_type = '$transaction_type'";
}

// Apply category filter
if ($category_filter > 0) {
    $query .= " AND t.category = $category_filter";
}

// Order by date
$query .= " ORDER BY t.transaction_date, t.id";

// Execute query
$transactions_result = mysqli_query($conn, $query);

// Get summary by category
$category_summary_query = "SELECT 
                          c.name as category_name,
                          t.transaction_type,
                          SUM(t.amount) as total_amount,
                          COUNT(t.id) as transaction_count
                          FROM petty_cash_transactions t
                          LEFT JOIN petty_cash_categories c ON t.category = c.id
                          WHERE t.transaction_date BETWEEN '$start_date' AND '$end_date'";

// Apply transaction type filter to summary
if ($transaction_type !== 'all') {
    $category_summary_query .= " AND t.transaction_type = '$transaction_type'";
}

// Apply category filter to summary
if ($category_filter > 0) {
    $category_summary_query .= " AND t.category = $category_filter";
}

$category_summary_query .= " GROUP BY t.transaction_type, t.category
                           ORDER BY t.transaction_type, total_amount DESC";

$category_summary_result = mysqli_query($conn, $category_summary_query);

// Calculate totals
$totals_query = "SELECT 
                SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as total_expense
                FROM petty_cash_transactions
                WHERE transaction_date BETWEEN '$start_date' AND '$end_date'";

// Apply category filter to totals
if ($category_filter > 0) {
    $totals_query .= " AND category = $category_filter";
}

// Apply transaction type filter to totals
if ($transaction_type !== 'all') {
    $totals_query .= " AND transaction_type = '$transaction_type'";
}

$totals_result = mysqli_query($conn, $totals_query);
$totals = mysqli_fetch_assoc($totals_result);

// Get all categories for filter dropdown
$all_cats_sql = "SELECT * FROM petty_cash_categories ORDER BY type, name";
$all_cats_result = mysqli_query($conn, $all_cats_sql);

// Get available months for dropdown
$months_query = "SELECT DISTINCT DATE_FORMAT(transaction_date, '%Y-%m') as month_year 
                FROM petty_cash_transactions 
                ORDER BY month_year DESC";
$months_result = mysqli_query($conn, $months_query);

// Get available years for dropdown
$years_query = "SELECT DISTINCT YEAR(transaction_date) as year 
               FROM petty_cash_transactions 
               ORDER BY year DESC";
$years_result = mysqli_query($conn, $years_query);

// Handle export request
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="petty-cash-report-' . date('Y-m-d') . '.csv"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV header row
    fputcsv($output, ['Date', 'Description', 'Category', 'Type', 'Reference', 'Amount']);
    
    // Add data rows
    mysqli_data_seek($transactions_result, 0);
    while ($row = mysqli_fetch_assoc($transactions_result)) {
        fputcsv($output, [
            $row['transaction_date'],
            $row['description'],
            $row['category_name'] ?? 'Uncategorized',
            ucfirst($row['transaction_type']),
            $row['reference_number'],
            $row['amount']
        ]);
    }
    
    // Close the output stream
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Petty Cash Reports</title>
    <style>
        .report-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
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
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Petty Cash Reports</h1>
        <a href="petty-cash.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Petty Cash
        </a>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Report Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="report_type" class="form-label">Report Type</label>
                    <select class="form-select" id="report_type" name="report_type">
                        <option value="monthly" <?php echo $report_type === 'monthly' ? 'selected' : ''; ?>>Monthly Report</option>
                        <option value="yearly" <?php echo $report_type === 'yearly' ? 'selected' : ''; ?>>Yearly Report</option>
                        <option value="custom" <?php echo $report_type === 'custom' ? 'selected' : ''; ?>>Custom Date Range</option>
                    </select>
                </div>
                
                <!-- Monthly filter fields -->
                <div class="col-md-3 monthly-fields" <?php echo $report_type !== 'monthly' ? 'style="display:none;"' : ''; ?>>
                    <label for="month" class="form-label">Select Month</label>
                    <select class="form-select" id="month" name="month">
                        <?php if ($months_result && mysqli_num_rows($months_result) > 0): ?>
                            <?php while ($month_row = mysqli_fetch_assoc($months_result)): ?>
                                <?php $month_display = date('F Y', strtotime($month_row['month_year'] . '-01')); ?>
                                <option value="<?php echo $month_row['month_year']; ?>" 
                                        <?php echo $month === $month_row['month_year'] ? 'selected' : ''; ?>>
                                    <?php echo $month_display; ?>
                                </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="<?php echo date('Y-m'); ?>"><?php echo date('F Y'); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <!-- Yearly filter fields -->
                <div class="col-md-3 yearly-fields" <?php echo $report_type !== 'yearly' ? 'style="display:none;"' : ''; ?>>
                    <label for="year" class="form-label">Select Year</label>
                    <select class="form-select" id="year" name="year">
                        <?php if ($years_result && mysqli_num_rows($years_result) > 0): ?>
                            <?php while ($year_row = mysqli_fetch_assoc($years_result)): ?>
                                <option value="<?php echo $year_row['year']; ?>" 
                                        <?php echo $year === $year_row['year'] ? 'selected' : ''; ?>>
                                    <?php echo $year_row['year']; ?>
                                </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="<?php echo date('Y'); ?>"><?php echo date('Y'); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <!-- Custom date range fields -->
                <div class="col-md-3 custom-date-fields" <?php echo $report_type !== 'custom' ? 'style="display:none;"' : ''; ?>>
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                
                <div class="col-md-3 custom-date-fields" <?php echo $report_type !== 'custom' ? 'style="display:none;"' : ''; ?>>
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="transaction_type" class="form-label">Transaction Type</label>
                    <select class="form-select" id="transaction_type" name="transaction_type">
                        <option value="all" <?php echo $transaction_type === 'all' ? 'selected' : ''; ?>>All Transactions</option>
                        <option value="income" <?php echo $transaction_type === 'income' ? 'selected' : ''; ?>>Income Only</option>
                        <option value="expense" <?php echo $transaction_type === 'expense' ? 'selected' : ''; ?>>Expenses Only</option>
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
                        <i class="fas fa-search me-2"></i> Generate Report
                    </button>
                    <a href="petty-cash-reports.php" class="btn btn-outline-secondary">
                        <i class="fas fa-undo me-2"></i> Reset
                    </a>
                    
                    <!-- Export buttons -->
                    <div class="float-end">
                        <a href="<?php echo $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') ? '&' : '?') . 'export=csv'; ?>" class="btn btn-success">
                            <i class="fas fa-file-csv me-2"></i> Export to CSV
                        </a>
                        <a href="#" class="btn btn-danger" id="printReportBtn">
                            <i class="fas fa-print me-2"></i> Print Report
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Report Header -->
    <div class="report-header">
        <div class="row">
            <div class="col-md-8">
                <h2>
                    <?php
                    if ($report_type === 'monthly') {
                        echo "Monthly Report: " . date('F Y', strtotime($month . '-01'));
                    } elseif ($report_type === 'yearly') {
                        echo "Yearly Report: " . $year;
                    } else {
                        echo "Custom Report: " . date('d M Y', strtotime($start_date)) . " to " . date('d M Y', strtotime($end_date));
                    }
                    ?>
                </h2>
                
                <p class="text-muted">
                    Report period: <?php echo date('d F Y', strtotime($start_date)); ?> to <?php echo date('d F Y', strtotime($end_date)); ?>
                </p>
                
                <?php if ($category_filter > 0): ?>
                    <?php
                    $cat_name = '';
                    mysqli_data_seek($all_cats_result, 0);
                    while ($cat = mysqli_fetch_assoc($all_cats_result)) {
                        if ($cat['id'] == $category_filter) {
                            $cat_name = $cat['name'];
                            break;
                        }
                    }
                    ?>
                    <p class="mb-0">
                        <span class="badge bg-info">
                            Category filter: <?php echo htmlspecialchars($cat_name); ?>
                        </span>
                    </p>
                <?php endif; ?>
                
                <?php if ($transaction_type !== 'all'): ?>
                    <p class="mb-0">
                        <span class="badge bg-info">
                            Type filter: <?php echo ucfirst($transaction_type); ?> only
                        </span>
                    </p>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-md-6 border-end">
                                <h6 class="text-muted">Total Income</h6>
                                <h4 class="income-amount">
                                    KES <?php echo number_format($totals['total_income'] ?? 0, 2); ?>
                                </h4>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">Total Expenses</h6>
                                <h4 class="expense-amount">
                                    KES <?php echo number_format($totals['total_expense'] ?? 0, 2); ?>
                                </h4>
                            </div>
                        </div>
                        <hr>
                        <div class="text-center">
                            <h6 class="text-muted">Net Balance</h6>
                            <h3 class="<?php echo ($totals['total_income'] - $totals['total_expense']) >= 0 ? 'income-amount' : 'expense-amount'; ?>">
                                KES <?php echo number_format(($totals['total_income'] ?? 0) - ($totals['total_expense'] ?? 0), 2); ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Category Breakdown -->
        <div class="col-md-5 mb-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Category Breakdown</h5>
                </div>
                <div class="card-body">
                    <?php if ($category_summary_result && mysqli_num_rows($category_summary_result) > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($category = mysqli_fetch_assoc($category_summary_result)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($category['category_name'] ?? 'Uncategorized'); ?></td>
                                            <td>
                                                <?php if ($category['transaction_type'] === 'income'): ?>
                                                    <span class="badge bg-success">Income</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Expense</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="<?php echo $category['transaction_type'] === 'income' ? 'income-amount' : 'expense-amount'; ?>">
                                                KES <?php echo number_format($category['total_amount'], 2); ?>
                                            </td>
                                            <td><?php echo $category['transaction_count']; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No data available for the selected period.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="col-md-7 mb-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Visual Summary</h5>
                </div>
                <div class="card-body">
                    <!-- We'll use Chart.js for visualization -->
                    <div class="chart-container">
                        <canvas id="incomeExpenseChart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Transactions List -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0">Transaction Details</h5>
        </div>
        <div class="card-body">
            <?php if ($transactions_result && mysqli_num_rows($transactions_result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Reference</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php mysqli_data_seek($transactions_result, 0); ?>
                            <?php while ($transaction = mysqli_fetch_assoc($transactions_result)): ?>
                                <tr>
                                    <td><?php echo date('d-M-Y', strtotime($transaction['transaction_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['reference_number']); ?></td>
                                    <td class="<?php echo $transaction['transaction_type'] === 'income' ? 'income-amount' : 'expense-amount'; ?>">
                                        <?php echo $transaction['transaction_type'] === 'income' ? '+' : '-'; ?> 
                                        KES <?php echo number_format($transaction['amount'], 2); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No transactions found for the selected period.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Include Chart.js for visualizations -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle filter fields based on report type
    const reportType = document.getElementById('report_type');
    const monthlyFields = document.querySelectorAll('.monthly-fields');
    const yearlyFields = document.querySelectorAll('.yearly-fields');
    const customDateFields = document.querySelectorAll('.custom-date-fields');
    
    reportType.addEventListener('change', function() {
        if (this.value === 'monthly') {
            monthlyFields.forEach(field => field.style.display = 'block');
            yearlyFields.forEach(field => field.style.display = 'none');
            customDateFields.forEach(field => field.style.display = 'none');
        } else if (this.value === 'yearly') {
            monthlyFields.forEach(field => field.style.display = 'none');
            yearlyFields.forEach(field => field.style.display = 'block');
            customDateFields.forEach(field => field.style.display = 'none');
        } else {
            monthlyFields.forEach(field => field.style.display = 'none');
            yearlyFields.forEach(field => field.style.display = 'none');
            customDateFields.forEach(field => field.style.display = 'block');
        }
    });
    
    // Print report functionality
    document.getElementById('printReportBtn').addEventListener('click', function(e) {
        e.preventDefault();
        window.print();
    });
    
    // Prepare data for charts
    <?php
    // Prepare data for pie chart
    $income_expense_data = [
        'labels' => ['Income', 'Expenses'],
        'data' => [
            $totals['total_income'] ?? 0,
            $totals['total_expense'] ?? 0
        ],
        'backgroundColor' => [
            'rgba(40, 167, 69, 0.7)',
            'rgba(220, 53, 69, 0.7)'
        ]
    ];
    
    // Prepare data for category chart
    $categories = [];
    $category_amounts = [];
    $category_colors = [];
    
    if ($category_summary_result && mysqli_num_rows($category_summary_result) > 0) {
        mysqli_data_seek($category_summary_result, 0);
        while ($category = mysqli_fetch_assoc($category_summary_result)) {
            $categories[] = $category['category_name'] ?? 'Uncategorized';
            $category_amounts[] = $category['total_amount'];
            
            // Set color based on transaction type
            if ($category['transaction_type'] === 'income') {
                $category_colors[] = 'rgba(40, 167, 69, 0.7)';
            } else {
                $category_colors[] = 'rgba(220, 53, 69, 0.7)';
            }
        }
    }
    ?>
    
    // Create income/expense pie chart
    const incomeExpenseCtx = document.getElementById('incomeExpenseChart').getContext('2d');
    const incomeExpenseChart = new Chart(incomeExpenseCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($income_expense_data['labels']); ?>,
            datasets: [{
                data: <?php echo json_encode($income_expense_data['data']); ?>,
                backgroundColor: <?php echo json_encode($income_expense_data['backgroundColor']); ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Income vs Expenses'
                }
            }
        }
    });
    
    // Create category bar chart
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    const categoryChart = new Chart(categoryCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($categories); ?>,
            datasets: [{
                label: 'Amount',
                data: <?php echo json_encode($category_amounts); ?>,
                backgroundColor: <?php echo json_encode($category_colors); ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: 'Amount by Category'
                }
            }
        }
    });
});
</script>

<?php
include_once 'includes/footer.php';
?>