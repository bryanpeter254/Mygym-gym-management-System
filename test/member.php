<?php
// Include header
include_once 'includes/header.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: members.php');
    exit;
}

// Get member ID from URL
$member_id = (int)$_GET['id'];

// Fetch member data
$sql = "SELECT m.*, mt.name as membership_type_name 
        FROM members m 
        LEFT JOIN membership_types mt ON m.membership_type_id = mt.id 
        WHERE m.id = $member_id";
$result = mysqli_query($conn, $sql);

// Check if member exists
if (!$result || mysqli_num_rows($result) == 0) {
    // Member not found
    $_SESSION['message'] = "Member not found!";
    $_SESSION['message_type'] = "danger";
    header('Location: members.php');
    exit;
}

// Get member data
$member = mysqli_fetch_assoc($result);

// Format dates
$registration_date = !empty($member['registration_date']) ? date('F j, Y', strtotime($member['registration_date'])) : 'N/A';
$payment_date = !empty($member['payment_date']) ? date('F j, Y', strtotime($member['payment_date'])) : 'N/A';
$start_date = !empty($member['start_date']) ? date('F j, Y', strtotime($member['start_date'])) : 'N/A';
$renewal_date = !empty($member['renewal_date']) ? date('F j, Y', strtotime($member['renewal_date'])) : 'N/A';
$today = date('Y-m-d');
$is_expired = !empty($member['renewal_date']) && $member['renewal_date'] < $today;

// Get recent check-ins for this member
$check_ins_sql = "SELECT * FROM check_ins WHERE member_id = $member_id ORDER BY check_in_time DESC LIMIT 10";
$check_ins_result = mysqli_query($conn, $check_ins_sql);

// Check if QR code image exists or generate it if missing
$qrcode_path = !empty($member['qrcode_path']) ? $member['qrcode_path'] : '';
$qrcode_exists = false;

// If QR code doesn't exist in the database, try to generate one
if (empty($qrcode_path) || !file_exists($qrcode_path)) {
    // Include the QR code functions
    if (file_exists('includes/qrcode-functions.php')) {
        require_once 'includes/qrcode-functions.php';
        
        // Try to generate QR code
        if (function_exists('generateQRCode')) {
            $qrcode_path = generateQRCode(
                $member_id, 
                $member['first_name'], 
                $member['last_name'], 
                $member['membership_type_name'], 
                $member['renewal_date']
            );
            
            // Update the database with the new QR code path
            if (!empty($qrcode_path)) {
                $update_sql = "UPDATE members SET qrcode_path = '" . mysqli_real_escape_string($conn, $qrcode_path) . "' WHERE id = $member_id";
                mysqli_query($conn, $update_sql);
            }
        }
    }
}

// Check if QR code file exists
$qrcode_exists = !empty($qrcode_path) && file_exists($qrcode_path);

// Generate QR code URL for display even if file doesn't exist
if (!$qrcode_exists) {
    // Generate a direct QR code URL using the QR Server API
    $member_data = [
        'id' => $member_id,
        'name' => trim($member['first_name'] . ' ' . $member['last_name']),
        'membership' => isset($member['membership_type_name']) ? $member['membership_type_name'] : '',
        'expiry' => isset($member['renewal_date']) ? $member['renewal_date'] : '',
    ];
    
    // Generate URL to member profile
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $profile_url = $base_url . dirname($_SERVER['PHP_SELF']) . "/member-profile.php?id=" . $member_id;
    
    // Create direct QR URL
    $direct_qr_url = "https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($profile_url) . "&size=300x300&ecc=H";
} else {
    $direct_qr_url = '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Details: <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></title>
    <!-- Bootstrap CSS is included in header.php -->
    <style>
        .profile-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .profile-photo {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        .profile-info {
            padding-left: 20px;
        }
        .membership-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 30px;
            font-weight: bold;
            margin-top: 10px;
        }
        .badge-active {
            background-color: #28a745;
            color: white;
        }
        .badge-expired {
            background-color: #dc3545;
            color: white;
        }
        .detail-card {
            height: 100%;
            transition: transform 0.3s;
        }
        .detail-card:hover {
            transform: translateY(-5px);
        }
        .action-buttons {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .qr-code-container {
            text-align: center;
            padding: 20px;
        }
    </style>
</head>
<body>

<div class="container py-4">
    <!-- Back button and actions -->
    <div class="d-flex justify-content-between mb-3">
        <a href="members.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Members
        </a>
        <div>
            <a href="register-new.php?edit=<?php echo $member_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i> Edit Member
            </a>
            <button type="button" class="btn btn-danger ms-2" data-bs-toggle="modal" data-bs-target="#deleteMemberModal">
                <i class="fas fa-trash me-2"></i> Delete
            </button>
        </div>
    </div>
    
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="row align-items-center">
            <div class="col-md-2 text-center">
                <?php if (!empty($member['photo']) && file_exists($member['photo'])): ?>
                    <img src="<?php echo $member['photo']; ?>" alt="Member Photo" class="profile-photo">
                <?php else: ?>
                    <div class="profile-photo bg-light d-flex align-items-center justify-content-center">
                        <i class="fas fa-user fa-4x text-secondary"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-10 profile-info">
                <h1><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h1>
                <p class="lead mb-0">
                    <i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($member['email']); ?> &nbsp;|&nbsp;
                    <i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($member['phone']); ?>
                </p>
                <div class="membership-badge <?php echo $is_expired ? 'badge-expired' : 'badge-active'; ?>">
                    <?php echo isset($member['membership_type_name']) ? htmlspecialchars($member['membership_type_name']) : 'No membership'; ?>
                    <?php if ($is_expired): ?>
                        <span class="ms-2"><i class="fas fa-exclamation-circle"></i> EXPIRED</span>
                    <?php else: ?>
                        <span class="ms-2"><i class="fas fa-check-circle"></i> ACTIVE</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <!-- Member Details -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Member Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Personal Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <th style="width: 40%;">Member ID</th>
                                    <td><?php echo $member_id; ?></td>
                                </tr>
                                <tr>
                                    <th>Gender</th>
                                    <td><?php echo ucfirst(htmlspecialchars($member['gender'])); ?></td>
                                </tr>
                                <?php if (!empty($member['dob'])): ?>
                                <tr>
                                    <th>Date of Birth</th>
                                    <td><?php echo date('F j, Y', strtotime($member['dob'])); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($member['address'])): ?>
                                <tr>
                                    <th>Address</th>
                                    <td><?php echo htmlspecialchars($member['address']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($member['height']) || !empty($member['current_weight']) || !empty($member['desired_weight'])): ?>
                                <tr>
                                    <th>Physical</th>
                                    <td>
                                        <?php if (!empty($member['height'])): ?>
                                            Height: <?php echo $member['height']; ?> cm<br>
                                        <?php endif; ?>
                                        <?php if (!empty($member['current_weight'])): ?>
                                            Current Weight: <?php echo $member['current_weight']; ?> kg<br>
                                        <?php endif; ?>
                                        <?php if (!empty($member['desired_weight'])): ?>
                                            Target Weight: <?php echo $member['desired_weight']; ?> kg
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <h6>Membership Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <th style="width: 40%;">Registration Date</th>
                                    <td><?php echo $registration_date; ?></td>
                                </tr>
                                <tr>
                                    <th>Start Date</th>
                                    <td><?php echo $start_date; ?></td>
                                </tr>
                                <tr>
                                    <th>Renewal Date</th>
                                    <td class="<?php echo $is_expired ? 'text-danger' : ''; ?>">
                                        <strong><?php echo $renewal_date; ?></strong>
                                        <?php if ($is_expired): ?>
                                            <span class="badge bg-danger ms-2">Expired</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Last Payment</th>
                                    <td><?php echo $payment_date; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <h6>Emergency Contact</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <th style="width: 40%;">Name</th>
                                    <td><?php echo htmlspecialchars($member['emergency_contact']); ?></td>
                                </tr>
                                <tr>
                                    <th>Phone</th>
                                    <td><?php echo htmlspecialchars($member['emergency_phone']); ?></td>
                                </tr>
                                <?php if (!empty($member['emergency_relationship'])): ?>
                                <tr>
                                    <th>Relationship</th>
                                    <td><?php echo htmlspecialchars($member['emergency_relationship']); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <h6>Additional Information</h6>
                            <table class="table table-borderless">
                                <?php if (isset($member['social_media_consent'])): ?>
                                <tr>
                                    <th style="width: 40%;">Social Media Consent</th>
                                    <td><?php echo $member['social_media_consent'] ? 'Yes' : 'No'; ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($member['special_comments'])): ?>
                                <tr>
                                    <th>Special Comments</th>
                                    <td><?php echo nl2br(htmlspecialchars($member['special_comments'])); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($member['medical_conditions'])): ?>
                                <tr>
                                    <th>Medical Conditions</th>
                                    <td><?php echo nl2br(htmlspecialchars($member['medical_conditions'])); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Check-ins -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i> Recent Check-ins</h5>
                </div>
                <div class="card-body">
                    <?php if ($check_ins_result && mysqli_num_rows($check_ins_result) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Method</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($check_in = mysqli_fetch_assoc($check_ins_result)): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($check_in['check_in_time'])); ?></td>
                                            <td><?php echo date('g:i A', strtotime($check_in['check_in_time'])); ?></td>
                                            <td>
                                                <?php 
                                                $method = strtolower($check_in['verification_method']);
                                                if ($method == 'qrcode' || $method == 'qr'): 
                                                ?>
                                                    <span class="badge bg-success">QR Code</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Manual</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="check-ins.php?member_id=<?php echo $member_id; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-history me-1"></i> View All Check-ins
                        </a>
                    <?php else: ?>
                        <p class="text-muted mb-0">No recent check-ins found for this member.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- QR Code and Quick Actions -->
        <div class="col-md-4">
            <!-- QR Code Card -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i> Member QR Code</h5>
                </div>
                <div class="card-body qr-code-container">
                    <?php if ($qrcode_exists || !empty($direct_qr_url)): ?>
                        <?php if ($qrcode_exists): ?>
                            <img src="<?php echo $qrcode_path; ?>" class="img-fluid mb-3" style="max-width: 200px;" alt="Member QR Code">
                        <?php else: ?>
                            <img src="<?php echo $direct_qr_url; ?>" class="img-fluid mb-3" style="max-width: 200px;" alt="Member QR Code">
                        <?php endif; ?>
                        
                        <p class="text-muted">Scan this QR code for instant check-in.</p>
                        
                        <div class="d-grid gap-2">
                            <?php if ($qrcode_exists): ?>
                                <a href="<?php echo $qrcode_path; ?>" download="member_<?php echo $member_id; ?>_qrcode.png" class="btn btn-primary">
                                    <i class="fas fa-download me-2"></i> Download QR Code
                                </a>
                            <?php else: ?>
                                <a href="<?php echo $direct_qr_url; ?>" download="member_<?php echo $member_id; ?>_qrcode.png" class="btn btn-primary">
                                    <i class="fas fa-download me-2"></i> Download QR Code
                                </a>
                            <?php endif; ?>
                            
                            <a href="member-profile.php?id=<?php echo $member_id; ?>" target="_blank" class="btn btn-outline-primary">
                                <i class="fas fa-eye me-2"></i> View Profile Page
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i> QR code is not available. Please contact an administrator.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="qr-check-in.php?member_id=<?php echo $member_id; ?>" class="btn btn-success">
                            <i class="fas fa-sign-in-alt me-2"></i> Check-in Member
                        </a>
                        <a href="update-membership.php?id=<?php echo $member_id; ?>" class="btn btn-warning">
                            <i class="fas fa-sync-alt me-2"></i> Renew Membership
                        </a>
                        <a href="print-id-card.php?id=<?php echo $member_id; ?>" class="btn btn-info">
                            <i class="fas fa-id-card me-2"></i> Print ID Card
                        </a>
                        <a href="send-message.php?id=<?php echo $member_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-envelope me-2"></i> Send Message
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteMemberModal" tabindex="-1" aria-labelledby="deleteMemberModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteMemberModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the member <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong>?</p>
                    <p class="mb-0"><strong>Warning:</strong> This action cannot be undone. All member data including check-in history will be permanently removed.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="delete-member.php?id=<?php echo $member_id; ?>" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>