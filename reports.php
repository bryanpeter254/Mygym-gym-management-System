<?php
// Include header
include_once 'includes/header.php';

// Get report parameters
$report_type = isset($_GET['type']) ? sanitize($_GET['type']) : 'membership';
$time_frame = isset($_GET['time_frame']) ? sanitize($_GET['time_frame']) : 'all';
$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : '';

// Build query based on report type and filters
$members_query = "SELECT m.*, mt.name as membership_name, mt.price as membership_price 
                  FROM members m
                  LEFT JOIN membership_types mt ON m.membership_type_id = mt.id
                  WHERE 1=1";

// Build checkins query
$checkins_query = "SELECT c.*, m.first_name, m.last_name, m.email, m.phone, m.gender, mt.name as membership_name 
                   FROM check_ins c
                   JOIN members m ON c.member_id = m.id
                   LEFT JOIN membership_types mt ON m.membership_type_id = mt.id
                   WHERE 1=1";

// Apply time frame filter to both queries
if ($time_frame !== 'all') {
    $date_condition = '';
    
    switch ($time_frame) {
        case 'today':
            $date_condition = "DATE(c.check_in_time) = CURDATE()";
            $members_date_condition = "DATE(m.registration_date) = CURDATE()";
            break;
        case 'yesterday':
            $date_condition = "DATE(c.check_in_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            $members_date_condition = "DATE(m.registration_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $date_condition = "YEARWEEK(c.check_in_time, 1) = YEARWEEK(CURDATE(), 1)";
            $members_date_condition = "YEARWEEK(m.registration_date, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'last_week':
            $date_condition = "YEARWEEK(c.check_in_time, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)";
            $members_date_condition = "YEARWEEK(m.registration_date, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)";
            break;
        case 'this_month':
            $date_condition = "YEAR(c.check_in_time) = YEAR(CURDATE()) AND MONTH(c.check_in_time) = MONTH(CURDATE())";
            $members_date_condition = "YEAR(m.registration_date) = YEAR(CURDATE()) AND MONTH(m.registration_date) = MONTH(CURDATE())";
            break;
        case 'last_month':
            $date_condition = "YEAR(c.check_in_time) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(c.check_in_time) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
            $members_date_condition = "YEAR(m.registration_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(m.registration_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
            break;
        case 'custom':
            if (!empty($start_date) && !empty($end_date)) {
                $date_condition = "DATE(c.check_in_time) BETWEEN '$start_date' AND '$end_date'";
                $members_date_condition = "DATE(m.registration_date) BETWEEN '$start_date' AND '$end_date'";
            }
            break;
    }
    
    if (!empty($date_condition)) {
        $checkins_query .= " AND $date_condition";
    }
    
    if (!empty($members_date_condition)) {
        $members_query .= " AND $members_date_condition";
    }
}

// Add ordering
$members_query .= " ORDER BY m.last_name, m.first_name";
$checkins_query .= " ORDER BY c.check_in_time DESC";

// Execute the appropriate query based on report type
$members_result = mysqli_query($conn, $members_query);
$checkins_result = mysqli_query($conn, $checkins_query);

// Calculate summary statistics
$total_members = mysqli_num_rows($members_result);

// Count by gender
$males = 0;
$females = 0;
$active_members = 0;
$inactive_members = 0;
$total_revenue = 0;

if ($report_type == 'membership' || $report_type == 'revenue') {
    mysqli_data_seek($members_result, 0);
    while ($member = mysqli_fetch_assoc($members_result)) {
        if ($member['gender'] == 'Male') $males++;
        if ($member['gender'] == 'Female') $females++;
        if ($member['status'] == 'Active') $active_members++;
        if ($member['status'] == 'Inactive') $inactive_members++;
        
        // Add to revenue
        if ($member['status'] == 'Active' && !empty($member['membership_price'])) {
            $total_revenue += $member['membership_price'];
        }
    }
    mysqli_data_seek($members_result, 0);
}

// Count check-ins
$qr_checkins = 0;
$manual_checkins = 0;
$total_checkins = mysqli_num_rows($checkins_result);

if ($report_type == 'attendance') {
    while ($checkin = mysqli_fetch_assoc($checkins_result)) {
        if ($checkin['verification_method'] == 'QR') $qr_checkins++;
        if ($checkin['verification_method'] == 'Manual') $manual_checkins++;
    }
    mysqli_data_seek($checkins_result, 0);
}

// Get membership types for summary
$membership_types_query = "SELECT mt.id, mt.name, COUNT(m.id) as member_count 
                           FROM membership_types mt
                           LEFT JOIN members m ON mt.id = m.membership_type_id AND m.status = 'Active'
                           GROUP BY mt.id
                           ORDER BY member_count DESC";
$membership_types_result = mysqli_query($conn, $membership_types_query);

// Get report title
function getReportTitle($report_type) {
    switch ($report_type) {
        case 'membership':
            return 'Membership Report';
        case 'attendance':
            return 'Attendance Report';
        case 'revenue':
            return 'Revenue Report';
        default:
            return 'Membership Report';
    }
}

// Get time frame label
function getTimeFrameLabel($time_frame, $start_date = '', $end_date = '') {
    switch ($time_frame) {
        case 'today':
            return 'Today (' . date('d-m-Y') . ')';
        case 'yesterday':
            return 'Yesterday (' . date('d-m-Y', strtotime('-1 day')) . ')';
        case 'this_week':
            return 'This Week (' . date('d-m-Y', strtotime('monday this week')) . ' to ' . date('d-m-Y', strtotime('sunday this week')) . ')';
        case 'last_week':
            return 'Last Week (' . date('d-m-Y', strtotime('monday last week')) . ' to ' . date('d-m-Y', strtotime('sunday last week')) . ')';
        case 'this_month':
            return 'This Month (' . date('F Y') . ')';
        case 'last_month':
            return 'Last Month (' . date('F Y', strtotime('first day of last month')) . ')';
        case 'custom':
            if (!empty($start_date) && !empty($end_date)) {
                return 'Custom Period (' . date('d-m-Y', strtotime($start_date)) . ' to ' . date('d-m-Y', strtotime($end_date)) . ')';
            }
            return 'Custom Period';
        default:
            return 'All Time';
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><?php echo getReportTitle($report_type); ?></h1>
            
            <div>
                <!-- Export buttons -->
                <a href="export.php?format=csv&report=<?php echo $report_type; ?>&time_frame=<?php echo $time_frame; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-success">
                    <i class="fas fa-file-csv me-2"></i> Export CSV
                </a>
                <a href="export.php?format=pdf&report=<?php echo $report_type; ?>&time_frame=<?php echo $time_frame; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-danger">
                    <i class="fas fa-file-pdf me-2"></i> Export PDF
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Report Filters -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <!-- Report type -->
                    <div class="col-md-3">
                        <label for="type" class="form-label">Report Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="membership" <?php echo $report_type === 'membership' ? 'selected' : ''; ?>>Membership Report</option>
                            <option value="attendance" <?php echo $report_type === 'attendance' ? 'selected' : ''; ?>>Attendance Report</option>
                            <option value="revenue" <?php echo $report_type === 'revenue' ? 'selected' : ''; ?>>Revenue Report</option>
                        </select>
                    </div>
                    
                    <!-- Time frame -->
                    <div class="col-md-3">
                        <label for="time_frame" class="form-label">Time Frame</label>
                        <select class="form-select" id="time_frame" name="time_frame">
                            <option value="all" <?php echo $time_frame === 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo $time_frame === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="yesterday" <?php echo $time_frame === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                            <option value="this_week" <?php echo $time_frame === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="last_week" <?php echo $time_frame === 'last_week' ? 'selected' : ''; ?>>Last Week</option>
                            <option value="this_month" <?php echo $time_frame === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                            <option value="last_month" <?php echo $time_frame === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                            <option value="custom" <?php echo $time_frame === 'custom' ? 'selected' : ''; ?>>Custom Period</option>
                        </select>
                    </div>
                    
                    <!-- Custom date range -->
                    <div class="col-md-3 custom-date <?php echo $time_frame !== 'custom' ? 'd-none' : ''; ?>">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    
                    <div class="col-md-3 custom-date <?php echo $time_frame !== 'custom' ? 'd-none' : ''; ?>">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    
                    <!-- Apply filters button -->
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i> Generate Report
                        </button>
                        <a href="reports.php" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-2"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Report Header -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <h2 class="text-center mb-3"><?php echo getReportTitle($report_type); ?></h2>
                <h5 class="text-center text-muted mb-4"><?php echo getTimeFrameLabel($time_frame, $start_date, $end_date); ?></h5>
                
                <div class="row">
                    <?php if ($report_type === 'membership'): ?>
                    <!-- Membership Stats -->
                    <div class="col-md-3 mb-3">
                        <div class="card h-100 bg-primary text-white">
                            <div class="card-body text-center">
                                <h1 class="display-4"><?php echo $total_members; ?></h1>
                                <p class="lead">Total Members</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card h-100 bg-success text-white">
                            <div class="card-body text-center">
                                <h1 class="display-4"><?php echo $active_members; ?></h1>
                                <p class="lead">Active Members</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card h-100 bg-info text-white">
                            <div class="card-body text-center">
                                <h1 class="display-4"><?php echo $males; ?></h1>
                                <p class="lead">Male Members</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card h-100 bg-warning text-dark">
                            <div class="card-body text-center">
                                <h1 class="display-4"><?php echo $females; ?></h1>
                                <p class="lead">Female Members</p>
                            </div>
                        </div>
                    </div>
                    
                    <?php elseif ($report_type === 'attendance'): ?>
                    <!-- Attendance Stats -->
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 bg-primary text-white">
                            <div class="card-body text-center">
                                <h1 class="display-4"><?php echo $total_checkins; ?></h1>
                                <p class="lead">Total Check-ins</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 bg-success text-white">
                            <div class="card-body text-center">
                                <h1 class="display-4"><?php echo $qr_checkins; ?></h1>
                                <p class="lead">QR Code Check-ins</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 bg-info text-white">
                            <div class="card-body text-center">
                                <h1 class="display-4"><?php echo $manual_checkins; ?></h1>
                                <p class="lead">Manual Check-ins</p>
                            </div>
                        </div>
                    </div>
                    
                    <?php elseif ($report_type === 'revenue'): ?>
                    <!-- Revenue Stats -->
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 bg-success text-white">
                            <div class="card-body text-center">
                                <h1 class="display-4">KES <?php echo number_format($total_revenue); ?></h1>
                                <p class="lead">Total Revenue</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 bg-primary text-white">
                            <div class="card-body text-center">
                                <h1 class="display-4"><?php echo $active_members; ?></h1>
                                <p class="lead">Active Memberships</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 bg-info text-white">
                            <div class="card-body text-center">
                                <h1 class="display-4">KES <?php echo $active_members > 0 ? number_format($total_revenue / $active_members) : 0; ?></h1>
                                <p class="lead">Avg. Revenue Per Member</p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Details -->
<div class="row">
    <div class="col-md-12">
        <?php if ($report_type === 'membership'): ?>
        <!-- Membership by Type -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Membership by Type</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Membership Type</th>
                                <th class="text-center">Number of Members</th>
                                <th class="text-center">Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_by_type = 0;
                            while ($type = mysqli_fetch_assoc($membership_types_result)): 
                                $total_by_type += $type['member_count'];
                            endwhile;
                            
                            mysqli_data_seek($membership_types_result, 0);
                            while ($type = mysqli_fetch_assoc($membership_types_result)): 
                                $percentage = $active_members > 0 ? round(($type['member_count'] / $active_members) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><?php echo $type['name']; ?></td>
                                <td class="text-center"><?php echo $type['member_count']; ?></td>
                                <td class="text-center">
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%;" 
                                             aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?php echo $percentage; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Members List -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Members List</h5>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($members_result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Membership Type</th>
                                <th>Registration Date</th>
                                <th>Renewal Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($member = mysqli_fetch_assoc($members_result)): ?>
                            <tr>
                                <td><?php echo $member['id']; ?></td>
                                <td><?php echo $member['first_name'] . ' ' . $member['last_name']; ?></td>
                                <td>
                                    <div><?php echo $member['email']; ?></div>
                                    <small class="text-muted"><?php echo $member['phone']; ?></small>
                                </td>
                                <td><?php echo $member['membership_name']; ?></td>
                                <td><?php echo displayDate($member['registration_date']); ?></td>
                                <td><?php echo displayDate($member['renewal_date']); ?></td>
                                <td>
                                    <?php if ($member['status'] === 'Active'): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php elseif ($member['status'] === 'Inactive'): ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Expired</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No members found for the selected period.
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php elseif ($report_type === 'attendance'): ?>
        <!-- Check-ins List -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Check-ins List</h5>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($checkins_result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Member</th>
                                <th>Check-in Time</th>
                                <th>Check-out Time</th>
                                <th>Duration</th>
                                <th>Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($checkin = mysqli_fetch_assoc($checkins_result)): 
                                // Calculate duration if checked out
                                $duration = '';
                                if (!empty($checkin['check_out_time'])) {
                                    $check_in = new DateTime($checkin['check_in_time']);
                                    $check_out = new DateTime($checkin['check_out_time']);
                                    $interval = $check_in->diff($check_out);
                                    
                                    $hours = $interval->h;
                                    $minutes = $interval->i;
                                    
                                    $duration = $hours > 0 ? "$hours hrs " : "";
                                    $duration .= "$minutes mins";
                                }
                            ?>
                            <tr>
                                <td><?php echo $checkin['id']; ?></td>
                                <td>
                                    <div><?php echo $checkin['first_name'] . ' ' . $checkin['last_name']; ?></div>
                                    <small class="text-muted"><?php echo $checkin['membership_name']; ?></small>
                                </td>
                                <td><?php echo date('d-m-Y H:i', strtotime($checkin['check_in_time'])); ?></td>
                                <td>
                                    <?php if (!empty($checkin['check_out_time'])): ?>
                                    <?php echo date('d-m-Y H:i', strtotime($checkin['check_out_time'])); ?>
                                    <?php else: ?>
                                    <span class="badge bg-info">Still Active</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo !empty($duration) ? $duration : '-'; ?></td>
                                <td>
                                    <?php if ($checkin['verification_method'] == 'QR'): ?>
                                    <span class="badge bg-primary"><i class="fas fa-qrcode me-1"></i> QR Code</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary"><i class="fas fa-keyboard me-1"></i> Manual</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No check-ins found for the selected period.
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php elseif ($report_type === 'revenue'): ?>
        <!-- Revenue by Membership Type -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Revenue by Membership Type</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Membership Type</th>
                                <th class="text-center">Number of Members</th>
                                <th class="text-end">Price (KES)</th>
                                <th class="text-end">Total Revenue (KES)</th>
                                <th class="text-center">Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($membership_types_result, 0);
                            
                            // Get membership type prices
                            $membership_prices_query = "SELECT id, price FROM membership_types";
                            $membership_prices_result = mysqli_query($conn, $membership_prices_query);
                            $membership_prices = [];
                            
                            while ($price_row = mysqli_fetch_assoc($membership_prices_result)) {
                                $membership_prices[$price_row['id']] = $price_row['price'];
                            }
                            
                            while ($type = mysqli_fetch_assoc($membership_types_result)): 
                                $type_price = isset($membership_prices[$type['id']]) ? $membership_prices[$type['id']] : 0;
                                $type_revenue = $type['member_count'] * $type_price;
                                $percentage = $total_revenue > 0 ? round(($type_revenue / $total_revenue) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><?php echo $type['name']; ?></td>
                                <td class="text-center"><?php echo $type['member_count']; ?></td>
                                <td class="text-end"><?php echo number_format($type_price); ?></td>
                                <td class="text-end"><?php echo number_format($type_revenue); ?></td>
                                <td class="text-center">
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentage; ?>%;" 
                                             aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?php echo $percentage; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-active fw-bold">
                                <td>Total</td>
                                <td class="text-center"><?php echo $active_members; ?></td>
                                <td class="text-end">-</td>
                                <td class="text-end"><?php echo number_format($total_revenue); ?></td>
                                <td class="text-center">100%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle custom date range visibility
    const timeFrameSelect = document.getElementById('time_frame');
    const customDateFields = document.querySelectorAll('.custom-date');
    
    if (timeFrameSelect) {
        timeFrameSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                customDateFields.forEach(field => field.classList.remove('d-none'));
            } else {
                customDateFields.forEach(field => field.classList.add('d-none'));
            }
        });
    }
});
</script>

<?php
// Include footer
include_once 'includes/footer.php';
?>