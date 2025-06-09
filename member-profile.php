<?php
/**
 * Member Profile Page - Displayed when QR code is scanned
 */

// Include database connection
include_once 'config.php';

// Get member ID from URL
$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if ID is valid
if ($member_id <= 0) {
    echo "Invalid member ID";
    exit;
}

// Fetch member data from database
$sql = "SELECT m.*, mt.name as membership_type_name 
        FROM members m 
        LEFT JOIN membership_types mt ON m.membership_type_id = mt.id 
        WHERE m.id = $member_id";
$result = mysqli_query($conn, $sql);

// Check if member exists
if (!$result || mysqli_num_rows($result) == 0) {
    echo "Member not found";
    exit;
}

// Get member data
$member = mysqli_fetch_assoc($result);

// Format dates
$renewal_date = date('F j, Y', strtotime($member['renewal_date']));
$today = date('Y-m-d');
$is_expired = $member['renewal_date'] < $today;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Profile: <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            padding: 20px; 
            background-color: #f8f9fa;
        }
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .profile-header {
            padding: 20px;
            background-color: #007bff;
            color: white;
        }
        .profile-body {
            padding: 30px;
        }
        .profile-photo {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
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
    </style>
</head>
<body>
    <div class="profile-container mt-5">
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <?php if (!empty($member['photo']) && file_exists($member['photo'])): ?>
                        <img src="<?php echo $member['photo']; ?>" alt="Member Photo" class="profile-photo">
                    <?php else: ?>
                        <div class="profile-photo bg-light d-flex align-items-center justify-content-center">
                            <i class="fas fa-user fa-4x text-secondary"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-9">
                    <h1><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h1>
                    <p class="lead mb-0">Member ID: <?php echo $member['id']; ?></p>
                    <div class="membership-badge <?php echo $is_expired ? 'badge-expired' : 'badge-active'; ?>">
                        <?php echo htmlspecialchars($member['membership_type_name']); ?>
                        <?php if ($is_expired): ?>
                            <span class="ms-2"><i class="fas fa-exclamation-circle"></i> EXPIRED</span>
                        <?php else: ?>
                            <span class="ms-2"><i class="fas fa-check-circle"></i> ACTIVE</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="profile-body">
            <div class="row">
                <div class="col-md-6">
                    <h4>Personal Information</h4>
                    <ul class="list-group list-group-flush mb-4">
                        <li class="list-group-item d-flex justify-content-between">
                            <strong><i class="fas fa-envelope me-2"></i> Email:</strong>
                            <span><?php echo htmlspecialchars($member['email']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <strong><i class="fas fa-phone me-2"></i> Phone:</strong>
                            <span><?php echo htmlspecialchars($member['phone']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <strong><i class="fas fa-venus-mars me-2"></i> Gender:</strong>
                            <span><?php echo ucfirst(htmlspecialchars($member['gender'])); ?></span>
                        </li>
                        <?php if (!empty($member['dob'])): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <strong><i class="fas fa-birthday-cake me-2"></i> Date of Birth:</strong>
                            <span><?php echo date('F j, Y', strtotime($member['dob'])); ?></span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="col-md-6">
                    <h4>Membership Details</h4>
                    <ul class="list-group list-group-flush mb-4">
                        <li class="list-group-item d-flex justify-content-between">
                            <strong><i class="fas fa-calendar-alt me-2"></i> Start Date:</strong>
                            <span><?php echo date('F j, Y', strtotime($member['start_date'])); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between <?php echo $is_expired ? 'text-danger' : ''; ?>">
                            <strong><i class="fas fa-calendar-check me-2"></i> Renewal Date:</strong>
                            <span><?php echo $renewal_date; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <strong><i class="fas fa-credit-card me-2"></i> Last Payment:</strong>
                            <span><?php echo date('F j, Y', strtotime($member['payment_date'])); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <strong><i class="fas fa-user-plus me-2"></i> Registered:</strong>
                            <span><?php echo date('F j, Y', strtotime($member['registration_date'])); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <?php if (!empty($member['special_comments']) || !empty($member['medical_conditions'])): ?>
            <div class="mt-4">
                <?php if (!empty($member['special_comments'])): ?>
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle me-2"></i> Special Comments</h5>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($member['special_comments'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($member['medical_conditions'])): ?>
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i> Medical Conditions</h5>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($member['medical_conditions'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <p class="text-muted">This profile was accessed via QR code scan</p>
                <a href="index.php" class="btn btn-primary"><i class="fas fa-home me-2"></i> Back to Home</a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>