<?php
// Include header
include_once 'includes/header.php';
require_once 'includes/qrcode-functions.php';

// Process check-in
$message = '';
$message_type = '';
$member = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['qr_content'])) {
        // Manual entry of QR code content
        $qr_content = $_POST['qr_content'];
        
        // Try to decode the JSON data
        $member_data = json_decode($qr_content, true);
        
        if ($member_data && isset($member_data['id'])) {
            $member_id = $member_data['id'];
            
            // Get the member details
            $sql = "SELECT m.*, mt.name as membership_type_name 
                    FROM members m 
                    LEFT JOIN membership_types mt ON m.membership_type_id = mt.id 
                    WHERE m.id = $member_id";
            $result = mysqli_query($conn, $sql);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $member = mysqli_fetch_assoc($result);
                
                // Check if membership is still valid
                $today = date('Y-m-d');
                if ($member['renewal_date'] < $today) {
                    $message = "Membership has expired. Renewal date: " . date('F j, Y', strtotime($member['renewal_date']));
                    $message_type = 'warning';
                } else {
                    // Record the check-in
                    $check_in_sql = "INSERT INTO check_ins (member_id, check_in_time, verification_method) 
                                     VALUES ($member_id, NOW(), 'QRCode')";
                    
                    if (mysqli_query($conn, $check_in_sql)) {
                        $message = "Check-in successful!";
                        $message_type = 'success';
                    } else {
                        $message = "Error recording check-in: " . mysqli_error($conn);
                        $message_type = 'danger';
                    }
                }
            } else {
                $message = "Member not found in database.";
                $message_type = 'danger';
            }
        } else {
            $message = "Invalid QR code. Please scan a valid member QR code.";
            $message_type = 'danger';
        }
    } elseif (isset($_POST['member_id'])) {
        // Manual member ID entry
        $member_id = (int)$_POST['member_id'];
        
        // Get the member details
        $sql = "SELECT m.*, mt.name as membership_type_name 
                FROM members m 
                LEFT JOIN membership_types mt ON m.membership_type_id = mt.id 
                WHERE m.id = $member_id";
        $result = mysqli_query($conn, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $member = mysqli_fetch_assoc($result);
            
            // Check if membership is still valid
            $today = date('Y-m-d');
            if ($member['renewal_date'] < $today) {
                $message = "Membership has expired. Renewal date: " . date('F j, Y', strtotime($member['renewal_date']));
                $message_type = 'warning';
            } else {
                // Record the check-in
                $check_in_sql = "INSERT INTO check_ins (member_id, check_in_time, verification_method) 
                                 VALUES ($member_id, NOW(), 'manual')";
                
                if (mysqli_query($conn, $check_in_sql)) {
                    $message = "Check-in successful!";
                    $message_type = 'success';
                } else {
                    $message = "Error recording check-in: " . mysqli_error($conn);
                    $message_type = 'danger';
                }
            }
        } else {
            $message = "Member not found in database.";
            $message_type = 'danger';
        }
    }
}

// Get recent check-ins
$recent_check_ins_sql = "SELECT c.*, m.first_name, m.last_name, mt.name as membership_type_name
                        FROM check_ins c
                        JOIN members m ON c.member_id = m.id
                        JOIN membership_types mt ON m.membership_type_id = mt.id
                        ORDER BY c.check_in_time DESC
                        LIMIT 10";
$recent_check_ins_result = mysqli_query($conn, $recent_check_ins_sql);
?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Member Check-in</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($member && $message_type === 'success'): ?>
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <div class="py-4">
                            <h2>Welcome, <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>!</h2>
                            <p class="lead">You've been checked in successfully.</p>
                            <div class="d-flex justify-content-center mt-3">
                                <div class="card bg-light mx-2" style="width: 12rem;">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Membership</h5>
                                        <p class="card-text"><?php echo htmlspecialchars($member['membership_type_name']); ?></p>
                                    </div>
                                </div>
                                <div class="card bg-light mx-2" style="width: 12rem;">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">ID</h5>
                                        <p class="card-text"><?php echo $member['id']; ?></p>
                                    </div>
                                </div>
                                <div class="card bg-light mx-2" style="width: 12rem;">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Check-in Time</h5>
                                        <p class="card-text"><?php echo date('g:i A'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i> QR Code Check-in</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="qrContent" class="form-label">QR Code Content</label>
                                    <textarea class="form-control" id="qrContent" name="qr_content" rows="4" placeholder="Paste the QR code content here..."></textarea>
                                    <div class="form-text">Enter the content decoded from the member's QR code.</div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt me-2"></i> Check-in with QR Code
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="fas fa-id-card me-2"></i> Manual Check-in</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="memberId" class="form-label">Member ID</label>
                                    <input type="number" class="form-control" id="memberId" name="member_id" placeholder="Enter member ID..." required>
                                    <div class="form-text">Enter the member's ID number for manual check-in.</div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-secondary">
                                        <i class="fas fa-sign-in-alt me-2"></i> Manual Check-in
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Check-ins</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Member ID</th>
                                    <th>Name</th>
                                    <th>Membership Type</th>
                                    <th>Check-in Time</th>
                                    <th>Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_check_ins_result && mysqli_num_rows($recent_check_ins_result) > 0): ?>
                                    <?php while ($check_in = mysqli_fetch_assoc($recent_check_ins_result)): ?>
                                        <tr>
                                            <td><?php echo $check_in['member_id']; ?></td>
                                            <td><?php echo htmlspecialchars($check_in['first_name'] . ' ' . $check_in['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($check_in['membership_type_name']); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($check_in['check_in_time'])); ?></td>
                                            <td>
                                                <?php if ($check_in['verification_method'] === 'qrcode'): ?>
                                                    <span class="badge bg-primary"><i class="fas fa-qrcode me-1"></i> QR Code</span>
                                                <?php elseif ($check_in['verification_method'] === 'QR'): ?>
                                                    <span class="badge bg-success"><i class="fas fa-qrcode me-1"></i> QR Code</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><i class="fas fa-keyboard me-1"></i> Manual</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No recent check-ins found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>