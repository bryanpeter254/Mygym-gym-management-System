<?php 
// Include header
include_once 'includes/header.php';

// Get counts for dashboard
$query = "SELECT 
    (SELECT COUNT(*) FROM members WHERE status = 'Active') as active_members,
    (SELECT COUNT(*) FROM members WHERE status = 'Inactive') as inactive_members,
    (SELECT COUNT(*) FROM check_ins WHERE DATE(check_in_time) = CURDATE()) as todays_checkins,
    (SELECT COUNT(*) FROM members WHERE renewal_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status = 'Active') as renewals_due";
$result = mysqli_query($conn, $query);
$counts = mysqli_fetch_assoc($result);

// Get recent check-ins
$recent_checkins_query = "SELECT c.*, m.first_name, m.last_name 
                         FROM check_ins c
                         JOIN members m ON c.member_id = m.id
                         ORDER BY c.check_in_time DESC
                         LIMIT 5";
$recent_checkins_result = mysqli_query($conn, $recent_checkins_query);
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="display-4 mb-4">Gym Management Dashboard</h1>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card dashboard-card h-100 bg-primary text-white">
            <div class="card-body text-center">
                <i class="fas fa-users card-icon"></i>
                <h2 class="card-title"><?php echo $counts['active_members']; ?></h2>
                <p class="card-text">Active Members</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card dashboard-card h-100 bg-info text-white">
            <div class="card-body text-center">
                <i class="fas fa-sign-in-alt card-icon"></i>
                <h2 class="card-title"><?php echo $counts['todays_checkins']; ?></h2>
                <p class="card-text">Today's Check-ins</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card dashboard-card h-100 bg-warning text-dark">
            <div class="card-body text-center">
                <i class="fas fa-sync-alt card-icon"></i>
                <h2 class="card-title"><?php echo $counts['renewals_due']; ?></h2>
                <p class="card-text">Renewals Due</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card dashboard-card h-100 bg-danger text-white">
            <div class="card-body text-center">
                <i class="fas fa-user-times card-icon"></i>
                <h2 class="card-title"><?php echo $counts['inactive_members']; ?></h2>
                <p class="card-text">Inactive Members</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Quick Links -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Quick Links</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="register.php" class="list-group-item list-group-item-action">
    <i class="fas fa-user-plus me-2"></i> Register New Member
</a>
<a href="check-in.php" class="list-group-item list-group-item-action">
    <i class="fas fa-qrcode me-2"></i> Member Check-in
</a>
<a href="members.php" class="list-group-item list-group-item-action">
    <i class="fas fa-search me-2"></i> Find Member
</a>
<a href="reports.php" class="list-group-item list-group-item-action">
    <i class="fas fa-chart-line me-2"></i> Generate Reports
</a>
<a href="membership-types.php" class="list-group-item list-group-item-action">
    <i class="fas fa-tags me-2"></i> Manage Membership Types
</a>
<a href="petty-cash.php" class="list-group-item list-group-item-action">
    <i class="fas fa-money-bill-wave me-2"></i> Petty Cash
</a>

                </div>
            </div>
        </div>
    </div>

    <!-- Recent Check-ins -->
    <div class="col-md-8 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">Recent Check-ins</h5>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($recent_checkins_result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Time</th>
                                <th>Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($checkin = mysqli_fetch_assoc($recent_checkins_result)): ?>
                            <tr>
                                <td><?php echo $checkin['first_name'] . ' ' . $checkin['last_name']; ?></td>
                                <td><?php echo date('d-m-Y H:i', strtotime($checkin['check_in_time'])); ?></td>
                                <td>
                                    <?php if ($checkin['verification_method'] == 'QR'): ?>
                                        <span class="badge bg-primary"><i class="fas fa-qrcode me-1"></i> QR Code</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="fas fa-keyboard me-1"></i> Manual</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($checkin['check_out_time']): ?>
                                        <span class="badge bg-success">Checked Out</span>
                                    <?php else: ?>
                                        <span class="badge bg-info">Active</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-center">No recent check-ins found.</p>
                <?php endif; ?>
                <div class="text-end mt-3">
                    <a href="check-ins.php" class="btn btn-sm btn-outline-primary">View All Check-ins</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Features -->
<div class="row mt-3">
    <div class="col-12">
        <h2 class="text-center mb-4">System Features</h2>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card feature-card">
            <div class="card-body text-center">
                <div class="feature-icon">
                    <i class="fas fa-qrcode"></i>
                </div>
                <h4>QR Code Authentication</h4>
                <p>Secure and fast member check-in with QR code scanning. Each member receives a unique QR code for easy gym access.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card feature-card">
            <div class="card-body text-center">
                <div class="feature-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <h4>Comprehensive Reports</h4>
                <p>Generate detailed membership, attendance, and revenue reports with CSV and PDF export options.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card feature-card">
            <div class="card-body text-center">
                <div class="feature-icon">
                    <i class="fas fa-user-cog"></i>
                </div>
                <h4>Member Management</h4>
                <p>Complete member profile management with membership tracking, renewals, and alerts.</p>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>