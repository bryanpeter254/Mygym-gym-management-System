<?php
// Include header
include_once 'includes/header.php';

// Set default filters
$current_month = date('m');
$current_year = date('Y');

// Process filter parameters
$filter_month = isset($_GET['month']) ? $_GET['month'] : $current_month;
$filter_year = isset($_GET['year']) ? $_GET['year'] : $current_year;
$filter_member = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;

// Build the query
$sql = "SELECT c.*, m.first_name, m.last_name, m.email, m.phone, mt.name as membership_type 
        FROM check_ins c
        JOIN members m ON c.member_id = m.id
        LEFT JOIN membership_types mt ON m.membership_type_id = mt.id
        WHERE 1=1";

$params = [];

// Apply filters
if ($filter_month != 'all') {
    $sql .= " AND MONTH(c.check_in_time) = ?";
    $params[] = $filter_month;
}

if ($filter_year != 'all') {
    $sql .= " AND YEAR(c.check_in_time) = ?";
    $params[] = $filter_year;
}

if ($filter_member > 0) {
    $sql .= " AND c.member_id = ?";
    $params[] = $filter_member;
}

// Add ordering
$sql .= " ORDER BY c.check_in_time DESC";

// Prepare and execute the query
$stmt = mysqli_prepare($conn, $sql);

if ($params) {
    $types = str_repeat('s', count($params));
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Check-ins</title>
    <!-- Bootstrap CSS is included in header.php -->
    <style>
        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .check-in-card:hover {
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .badge-qr {
            background-color: #28a745;
        }
        .badge-manual {
            background-color: #6c757d;
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1><i class="fas fa-calendar-check text-primary me-2"></i> Member Check-ins</h1>
            <p class="lead">View and filter all member check-ins at the gym</p>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-section mb-4">
        <form method="get" action="" class="row g-3">
            <div class="col-md-3">
                <label for="month" class="form-label">Month</label>
                <select class="form-select" id="month" name="month">
                    <option value="all" <?php echo $filter_month == 'all' ? 'selected' : ''; ?>>All Months</option>
                    <?php
                    $months = [
                        '01' => 'January',
                        '02' => 'February',
                        '03' => 'March',
                        '04' => 'April',
                        '05' => 'May',
                        '06' => 'June',
                        '07' => 'July',
                        '08' => 'August',
                        '09' => 'September',
                        '10' => 'October',
                        '11' => 'November',
                        '12' => 'December'
                    ];
                    
                    foreach ($months as $num => $name) {
                        echo '<option value="' . $num . '" ' . ($filter_month == $num ? 'selected' : '') . '>' . $name . '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="year" class="form-label">Year</label>
                <select class="form-select" id="year" name="year">
                    <option value="all" <?php echo $filter_year == 'all' ? 'selected' : ''; ?>>All Years</option>
                    <?php
                    // Generate years from 2020 to current year
                    $current_year = date('Y');
                    for ($y = $current_year; $y >= 2020; $y--) {
                        echo '<option value="' . $y . '" ' . ($filter_year == $y ? 'selected' : '') . '>' . $y . '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div class="col-md-4">
                <label for="member_id" class="form-label">Member</label>
                <select class="form-select" id="member_id" name="member_id">
                    <option value="0" <?php echo $filter_member == 0 ? 'selected' : ''; ?>>All Members</option>
                    <?php
                    // Get all members
                    $members_sql = "SELECT id, first_name, last_name FROM members ORDER BY first_name, last_name";
                    $members_result = mysqli_query($conn, $members_sql);
                    
                    if ($members_result && mysqli_num_rows($members_result) > 0) {
                        while ($member = mysqli_fetch_assoc($members_result)) {
                            echo '<option value="' . $member['id'] . '" ' . 
                                 ($filter_member == $member['id'] ? 'selected' : '') . '>' . 
                                 htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>
    
    <!-- Check-in Table -->
    <div class="card">
        <div class="card-header bg-light">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i> Check-in Records</h5>
                </div>
                <div class="col-auto">
                    <?php if (mysqli_num_rows($result) > 0): ?>
                    <a href="export-check-ins.php?month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>&member_id=<?php echo $filter_member; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-file-export me-1"></i> Export to CSV
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (mysqli_num_rows($result) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Member</th>
                            <th>Check-in Time</th>
                            <th>Membership Type</th>
                            <th>Method</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($check_in = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td>
                                    <a href="member-profile.php?id=<?php echo $check_in['member_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($check_in['first_name'] . ' ' . $check_in['last_name']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php 
                                    $check_in_date = new DateTime($check_in['check_in_time']);
                                    echo $check_in_date->format('M j, Y g:i A'); 
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($check_in['membership_type']); ?></td>
                                <td>
                                    <?php if (strtolower($check_in['verification_method']) == 'qrcode' || strtolower($check_in['verification_method']) == 'qr'): ?>
                                        <span class="badge bg-success"><i class="fas fa-qrcode me-1"></i> QR Code</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="fas fa-keyboard me-1"></i> Manual</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="member-profile.php?id=<?php echo $check_in['member_id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-user me-1"></i> View Member
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card-footer bg-light">
                <div class="text-muted">
                    Total check-ins: <?php echo mysqli_num_rows($result); ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card-body text-center py-5">
                <div class="text-muted mb-3">
                    <i class="fas fa-calendar-times fa-3x"></i>
                </div>
                <h5>No check-ins found</h5>
                <p>Try changing your filters or check back later.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Statistics Card -->
    <?php if (mysqli_num_rows($result) > 0): ?>
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i> Check-in Statistics</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get count of check-ins by day of week
                    $day_stats_sql = "SELECT 
                                        DAYNAME(check_in_time) as day_name,
                                        COUNT(*) as count
                                      FROM check_ins
                                      WHERE 1=1";
                    
                    if ($filter_month != 'all') {
                        $day_stats_sql .= " AND MONTH(check_in_time) = " . (int)$filter_month;
                    }
                    
                    if ($filter_year != 'all') {
                        $day_stats_sql .= " AND YEAR(check_in_time) = " . (int)$filter_year;
                    }
                    
                    if ($filter_member > 0) {
                        $day_stats_sql .= " AND member_id = " . (int)$filter_member;
                    }
                    
                    $day_stats_sql .= " GROUP BY DAYNAME(check_in_time)
                                      ORDER BY DAYOFWEEK(check_in_time)";
                                      
                    $day_stats_result = mysqli_query($conn, $day_stats_sql);
                    
                    if ($day_stats_result && mysqli_num_rows($day_stats_result) > 0):
                    ?>
                        <h6>Check-ins by Day of Week</h6>
                        <div class="progress-stacked mb-4">
                            <?php
                            $days = [];
                            $total_checkins = 0;
                            
                            while ($day_stat = mysqli_fetch_assoc($day_stats_result)) {
                                $days[$day_stat['day_name']] = $day_stat['count'];
                                $total_checkins += $day_stat['count'];
                            }
                            
                            $colors = [
                                'Monday' => 'bg-primary',
                                'Tuesday' => 'bg-success',
                                'Wednesday' => 'bg-info',
                                'Thursday' => 'bg-warning',
                                'Friday' => 'bg-danger',
                                'Saturday' => 'bg-secondary',
                                'Sunday' => 'bg-dark'
                            ];
                            
                            foreach ($days as $day_name => $count) {
                                $percentage = ($count / $total_checkins) * 100;
                                $color = isset($colors[$day_name]) ? $colors[$day_name] : 'bg-primary';
                                echo '<div class="progress-bar ' . $color . '" role="progressbar" style="width: ' . $percentage . '%" 
                                     aria-valuenow="' . $percentage . '" aria-valuemin="0" aria-valuemax="100" 
                                     title="' . $day_name . ': ' . $count . ' check-ins"></div>';
                            }
                            ?>
                        </div>
                        
                        <div class="row">
                            <?php foreach ($days as $day_name => $count): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="d-flex align-items-center">
                                        <div class="me-2" style="width: 20px; height: 20px; border-radius: 3px;" class="<?php echo isset($colors[$day_name]) ? $colors[$day_name] : 'bg-primary'; ?>"></div>
                                        <div class="small">
                                            <strong><?php echo $day_name; ?>:</strong> 
                                            <?php echo $count; ?> check-ins 
                                            (<?php echo round(($count / $total_checkins) * 100, 1); ?>%)
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php
                    // Get count by verification method
                    $method_stats_sql = "SELECT 
                                         verification_method,
                                         COUNT(*) as count
                                       FROM check_ins
                                       WHERE 1=1";
                    
                    if ($filter_month != 'all') {
                        $method_stats_sql .= " AND MONTH(check_in_time) = " . (int)$filter_month;
                    }
                    
                    if ($filter_year != 'all') {
                        $method_stats_sql .= " AND YEAR(check_in_time) = " . (int)$filter_year;
                    }
                    
                    if ($filter_member > 0) {
                        $method_stats_sql .= " AND member_id = " . (int)$filter_member;
                    }
                    
                    $method_stats_sql .= " GROUP BY verification_method";
                                      
                    $method_stats_result = mysqli_query($conn, $method_stats_sql);
                    
                    if ($method_stats_result && mysqli_num_rows($method_stats_result) > 0):
                        $methods = [];
                        $method_total = 0;
                        
                        while ($method_stat = mysqli_fetch_assoc($method_stats_result)) {
                            $method_name = strtolower($method_stat['verification_method']);
                            if ($method_name == 'qrcode' || $method_name == 'qr') {
                                $methods['QR Code'] = $method_stat['count'];
                            } else {
                                $methods['Manual'] = $method_stat['count'];
                            }
                            $method_total += $method_stat['count'];
                        }
                    ?>
                        <h6 class="mt-4">Check-ins by Method</h6>
                        <div class="row text-center">
                            <?php if (isset($methods['QR Code'])): ?>
                            <div class="col-6">
                                <div class="card bg-light">
                                    <div class="card-body py-3">
                                        <h3 class="mb-0"><?php echo $methods['QR Code']; ?></h3>
                                        <div class="small text-muted">QR Code Check-ins</div>
                                        <div class="mt-2">
                                            <span class="badge bg-success">
                                                <?php echo round(($methods['QR Code'] / $method_total) * 100, 1); ?>%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($methods['Manual'])): ?>
                            <div class="col-6">
                                <div class="card bg-light">
                                    <div class="card-body py-3">
                                        <h3 class="mb-0"><?php echo $methods['Manual']; ?></h3>
                                        <div class="small text-muted">Manual Check-ins</div>
                                        <div class="mt-2">
                                            <span class="badge bg-secondary">
                                                <?php echo round(($methods['Manual'] / $method_total) * 100, 1); ?>%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i> Check-in Time Analysis</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get count of check-ins by hour of day
                    $hour_stats_sql = "SELECT 
                                         HOUR(check_in_time) as hour,
                                         COUNT(*) as count
                                       FROM check_ins
                                       WHERE 1=1";
                    
                    if ($filter_month != 'all') {
                        $hour_stats_sql .= " AND MONTH(check_in_time) = " . (int)$filter_month;
                    }
                    
                    if ($filter_year != 'all') {
                        $hour_stats_sql .= " AND YEAR(check_in_time) = " . (int)$filter_year;
                    }
                    
                    if ($filter_member > 0) {
                        $hour_stats_sql .= " AND member_id = " . (int)$filter_member;
                    }
                    
                    $hour_stats_sql .= " GROUP BY HOUR(check_in_time)
                                       ORDER BY HOUR(check_in_time)";
                                      
                    $hour_stats_result = mysqli_query($conn, $hour_stats_sql);
                    
                    if ($hour_stats_result && mysqli_num_rows($hour_stats_result) > 0):
                        $hours = [];
                        $max_count = 0;
                        
                        while ($hour_stat = mysqli_fetch_assoc($hour_stats_result)) {
                            $hour = (int)$hour_stat['hour'];
                            $count = (int)$hour_stat['count'];
                            $hours[$hour] = $count;
                            
                            if ($count > $max_count) {
                                $max_count = $count;
                            }
                        }
                    ?>
                        <h6>Popular Check-in Times</h6>
                        <div class="time-chart mt-3">
                            <?php
                            // Divide the day into 4-hour segments
                            $segments = [
                                ['start' => 0, 'end' => 5, 'label' => 'Early Morning (12am-6am)'],
                                ['start' => 6, 'end' => 11, 'label' => 'Morning (6am-12pm)'],
                                ['start' => 12, 'end' => 17, 'label' => 'Afternoon (12pm-6pm)'],
                                ['start' => 18, 'end' => 23, 'label' => 'Evening (6pm-12am)']
                            ];
                            
                            foreach ($segments as $segment):
                                $segment_total = 0;
                                for ($h = $segment['start']; $h <= $segment['end']; $h++) {
                                    $segment_total += isset($hours[$h]) ? $hours[$h] : 0;
                                }
                            ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span><?php echo $segment['label']; ?></span>
                                        <span><?php echo $segment_total; ?> check-ins</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-info" role="progressbar" 
                                             style="width: <?php echo ($max_count > 0) ? (($segment_total / $max_count) * 100) : 0; ?>%" 
                                             aria-valuenow="<?php echo $segment_total; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="<?php echo $max_count; ?>"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <h6 class="mt-4">Peak Hours</h6>
                        <div class="row">
                            <?php
                            // Find the top 3 hours
                            arsort($hours);
                            $top_hours = array_slice($hours, 0, 3, true);
                            
                            foreach ($top_hours as $hour => $count):
                                // Format hour for display
                                $formatted_hour = date('g:i A', strtotime($hour . ':00'));
                            ?>
                                <div class="col-md-4 mb-2">
                                    <div class="card text-center">
                                        <div class="card-body py-2">
                                            <h4 class="mb-0"><?php echo $formatted_hour; ?></h4>
                                            <div class="small text-muted"><?php echo $count; ?> check-ins</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Navigation buttons -->
    <div class="mt-4">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
        <a href="qr-check-in.php" class="btn btn-primary">
            <i class="fas fa-qrcode me-2"></i> QR Check-in
        </a>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>