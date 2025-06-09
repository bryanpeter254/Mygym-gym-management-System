<?php
// Include header
include_once 'includes/header.php';

// Fetch membership types for dropdown
$membership_query = "SELECT * FROM membership_types ORDER BY price ASC";
$membership_result = mysqli_query($conn, $membership_query);

// Check what step we're on (1: Info, 2: Confirmation, 3: QR Code)
$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$registration_complete = false;
$member = null;

// Process form submission for final confirmation (Step 2 -> 3)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 3) {
    // Get all the form data from the confirmation page
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $gender = sanitize($_POST['gender']);
    $dob = isset($_POST['dob']) && !empty($_POST['dob']) ? sanitize($_POST['dob']) : null;
    $address = isset($_POST['address']) ? sanitize($_POST['address']) : '';
    $emergency_contact = sanitize($_POST['emergency_contact']);
    $emergency_phone = sanitize($_POST['emergency_phone']);
    $emergency_relationship = isset($_POST['emergency_relationship']) ? sanitize($_POST['emergency_relationship']) : '';
    $membership_type_id = (int)$_POST['membership_type'];
    $registration_date = sanitize($_POST['registration_date']);
    $payment_date = sanitize($_POST['payment_date']);
    $start_date = sanitize($_POST['start_date']);
    $renewal_date = sanitize($_POST['renewal_date']);
    $special_comments = isset($_POST['special_comments']) ? sanitize($_POST['special_comments']) : '';
    $medical_conditions = isset($_POST['medical_conditions']) ? sanitize($_POST['medical_conditions']) : '';
    $height = !empty($_POST['height']) ? (int)$_POST['height'] : null;
    $current_weight = !empty($_POST['current_weight']) ? (int)$_POST['current_weight'] : null;
    $desired_weight = !empty($_POST['desired_weight']) ? (int)$_POST['desired_weight'] : null;
    $social_media_consent = isset($_POST['social_media_consent']) ? 1 : 0;
    
    // Check if email already exists
    $check_email = "SELECT id FROM members WHERE email = '$email'";
    $result = mysqli_query($conn, $check_email);
    
    if (mysqli_num_rows($result) > 0) {
        $_SESSION['message'] = "A member with this email already exists.";
        $_SESSION['message_type'] = "danger";
        $step = 1; // Go back to first step
    } else {
        // Handle file upload for photo if submitted
        $photo = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/members/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_name = time() . '_' . basename($_FILES['photo']['name']);
            $target_file = $upload_dir . $file_name;
            
            // Validate file is an image
            $image_info = getimagesize($_FILES['photo']['tmp_name']);
            if ($image_info !== false) {
                // Move file to uploads directory
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                    $photo = $target_file;
                }
            }
        }
        
        // Include the QR code functions
        require_once 'includes/qrcode-functions.php';
        
        // Get membership type name
        $membership_sql = "SELECT name FROM membership_types WHERE id = " . (int)$membership_type_id;
        $membership_result = mysqli_query($conn, $membership_sql);
        $membership_type_name = '';
        
        if ($membership_result && mysqli_num_rows($membership_result) > 0) {
            $membership_row = mysqli_fetch_assoc($membership_result);
            $membership_type_name = $membership_row['name'];
        }
            
        // Insert new member with QR code information
        $sql = "INSERT INTO members (
                first_name, last_name, email, phone, gender, dob, address,
                emergency_contact, emergency_phone, emergency_relationship, membership_type_id,
                registration_date, payment_date, start_date, renewal_date,
                special_comments, medical_conditions, height, current_weight, desired_weight,
                social_media_consent, photo, qrcode_path
            ) VALUES (
                '$first_name', '$last_name', '$email', '$phone', '$gender', " . 
                ($dob ? "'$dob'" : "NULL") . ", '$address',
                '$emergency_contact', '$emergency_phone', '$emergency_relationship', $membership_type_id,
                '$registration_date', '$payment_date', '$start_date', '$renewal_date',
                '$special_comments', '$medical_conditions', " . 
                ($height ? $height : "NULL") . ", " . 
                ($current_weight ? $current_weight : "NULL") . ", " . 
                ($desired_weight ? $desired_weight : "NULL") . ", 
                $social_media_consent, " .
                ($photo ? "'$photo'" : "NULL") . ", NULL
            )";
            
        if (mysqli_query($conn, $sql)) {
            $member_id = mysqli_insert_id($conn);
            
            // Generate QR code with error handling
            try {
                // Log QR code generation attempt
                error_log("Attempting to generate QR code for member ID: " . $member_id);
                
                // Generate QR code
                $qrcode_path = generate_member_qrcode(
                    $member_id,
                    $first_name,
                    $last_name,
                    $membership_type_name,
                    $renewal_date
                );
                
                // Verify the QR code path is valid
                if (!empty($qrcode_path)) {
                    error_log("QR code generated successfully. Path: " . $qrcode_path);
                    
                    // Update the member record with the QR code path
                    $update_sql = "UPDATE members SET qrcode_path = '" . mysqli_real_escape_string($conn, $qrcode_path) . "' WHERE id = $member_id";
                    $update_result = mysqli_query($conn, $update_sql);
                    
                    if (!$update_result) {
                        error_log("Error updating member with QR code path: " . mysqli_error($conn));
                    } else {
                        error_log("Member record updated with QR code path");
                    }
                } else {
                    error_log("QR code generation failed, path is empty");
                }
            } catch (Exception $e) {
                error_log("Exception during QR code generation: " . $e->getMessage());
            }
            
            $registration_complete = true;
            $member = [
                'id' => $member_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'membership_type_name' => $membership_type_name,
                'renewal_date' => $renewal_date,
                'qrcode_path' => $qrcode_path
            ];
        } else {
            $_SESSION['message'] = "Error registering member: " . mysqli_error($conn);
            $_SESSION['message_type'] = "danger";
            $step = 1; // Go back to first step
        }
    }
}

// Set default dates
$today = date('Y-m-d');
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mb-4">Register New Member</h1>
            
            <?php 
            // Display any messages
            if (isset($_SESSION['message']) && isset($_SESSION['message_type'])) {
                echo '<div class="alert alert-' . $_SESSION['message_type'] . ' alert-dismissible fade show" role="alert">';
                echo $_SESSION['message'];
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                echo '</div>';
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            }
            
            // Display progress steps
            ?>
            <div class="card mb-4">
                <div class="card-body p-4">
                    <ul class="nav nav-pills nav-justified step-nav">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($step == 1) ? 'active' : ($step > 1 ? 'completed' : ''); ?>">
                                <span class="step-number">1</span>
                                <span class="d-inline">Member Information</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($step == 2) ? 'active' : ($step > 2 ? 'completed' : ''); ?>">
                                <span class="step-number">2</span>
                                <span class="d-inline">Confirmation</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($step == 3 && $registration_complete) ? 'active' : ''; ?>">
                                <span class="step-number">3</span>
                                <span class="d-inline">QR Code</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <?php if ($registration_complete): ?>
                <!-- Step 3: Show QR Code and Completion Message -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="fas fa-check-circle me-2"></i> Registration Complete</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 text-center mb-4 mb-md-0">
                                <div class="p-4 border rounded bg-light">
                                    <h4 class="mb-3">Member QR Code</h4>
                                    <?php
                                    $absolute_qr_path = !empty($member['qrcode_path']) ? dirname(__DIR__) . '/' . $member['qrcode_path'] : '';
                                    $qr_exists = !empty($absolute_qr_path) && file_exists($absolute_qr_path);
                                    
                                    // Debug information
                                    error_log("Member QR code path: " . $member['qrcode_path']);
                                    error_log("Absolute QR path being checked: " . $absolute_qr_path);
                                    error_log("QR file exists? " . ($qr_exists ? 'Yes' : 'No'));
                                    
                                    if ($qr_exists): 
                                    ?>
                                        <img src="<?php echo $member['qrcode_path']; ?>" class="img-fluid mb-3" style="max-width: 250px;" alt="Member QR Code">
                                        <p>Use this QR code for quick check-in at the gym.</p>
                                        <a href="<?php echo $member['qrcode_path']; ?>" download="member_<?php echo $member['id']; ?>_qrcode.png" class="btn btn-primary">
                                            <i class="fas fa-download me-2"></i> Download QR Code
                                        </a>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i> 
                                            QR code generation is pending. Please try downloading it later or contact an administrator.
                                        </div>
                                        
                                        <!-- Generate a text representation -->
                                        <div class="bg-light p-3 mb-3 border rounded">
                                            <h5>Member ID: <?php echo $member['id']; ?></h5>
                                            <p>
                                                Name: <?php echo $member['first_name'] . ' ' . $member['last_name']; ?><br>
                                                Membership: <?php echo $member['membership_type_name']; ?><br>
                                                Expiry: <?php echo $member['renewal_date']; ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h4>Welcome, <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>!</h4>
                                <p class="lead">Your membership has been successfully registered.</p>
                                
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <h5 class="card-title">Membership Details</h5>
                                        <ul class="list-unstyled">
                                            <li><strong>Membership Type:</strong> <?php echo htmlspecialchars($member['membership_type_name']); ?></li>
                                            <li><strong>Start Date:</strong> <?php echo date('F j, Y', strtotime($start_date)); ?></li>
                                            <li><strong>Renewal Date:</strong> <?php echo date('F j, Y', strtotime($member['renewal_date'])); ?></li>
                                            <li><strong>Member ID:</strong> <?php echo $member['id']; ?></li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <a href="member.php?id=<?php echo $member['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-user me-2"></i> Go to Member Profile
                                    </a>
                                    <a href="members.php" class="btn btn-outline-secondary ms-2">
                                        <i class="fas fa-users me-2"></i> View All Members
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($step == 2): ?>
                <!-- Step 2: Show confirmation page -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-check-circle me-2"></i> Confirm Member Information</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <!-- Include all hidden fields to persist data -->
                            <input type="hidden" name="step" value="3">
                            <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name']); ?>">
                            <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name']); ?>">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($_POST['email']); ?>">
                            <input type="hidden" name="phone" value="<?php echo htmlspecialchars($_POST['phone']); ?>">
                            <input type="hidden" name="gender" value="<?php echo htmlspecialchars($_POST['gender']); ?>">
                            <?php if(isset($_POST['dob']) && !empty($_POST['dob'])): ?>
                                <input type="hidden" name="dob" value="<?php echo htmlspecialchars($_POST['dob']); ?>">
                            <?php endif; ?>
                            <?php if(isset($_POST['address'])): ?>
                                <input type="hidden" name="address" value="<?php echo htmlspecialchars($_POST['address']); ?>">
                            <?php endif; ?>
                            <input type="hidden" name="emergency_contact" value="<?php echo htmlspecialchars($_POST['emergency_contact']); ?>">
                            <input type="hidden" name="emergency_phone" value="<?php echo htmlspecialchars($_POST['emergency_phone']); ?>">
                            <?php if(isset($_POST['emergency_relationship'])): ?>
                                <input type="hidden" name="emergency_relationship" value="<?php echo htmlspecialchars($_POST['emergency_relationship']); ?>">
                            <?php endif; ?>
                            <input type="hidden" name="membership_type" value="<?php echo (int)$_POST['membership_type']; ?>">
                            <input type="hidden" name="registration_date" value="<?php echo htmlspecialchars($_POST['registration_date']); ?>">
                            <input type="hidden" name="payment_date" value="<?php echo htmlspecialchars($_POST['payment_date']); ?>">
                            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date']); ?>">
                            <input type="hidden" name="renewal_date" value="<?php echo htmlspecialchars($_POST['renewal_date']); ?>">
                            <?php if(isset($_POST['special_comments'])): ?>
                                <input type="hidden" name="special_comments" value="<?php echo htmlspecialchars($_POST['special_comments']); ?>">
                            <?php endif; ?>
                            <?php if(isset($_POST['medical_conditions'])): ?>
                                <input type="hidden" name="medical_conditions" value="<?php echo htmlspecialchars($_POST['medical_conditions']); ?>">
                            <?php endif; ?>
                            <?php if(isset($_POST['height'])): ?>
                                <input type="hidden" name="height" value="<?php echo (int)$_POST['height']; ?>">
                            <?php endif; ?>
                            <?php if(isset($_POST['current_weight'])): ?>
                                <input type="hidden" name="current_weight" value="<?php echo (int)$_POST['current_weight']; ?>">
                            <?php endif; ?>
                            <?php if(isset($_POST['desired_weight'])): ?>
                                <input type="hidden" name="desired_weight" value="<?php echo (int)$_POST['desired_weight']; ?>">
                            <?php endif; ?>
                            <?php if(isset($_POST['social_media_consent'])): ?>
                                <input type="hidden" name="social_media_consent" value="1">
                            <?php endif; ?>
                            
                            <!-- Transfer photo if uploaded -->
                            <?php if(isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK): ?>
                                <!-- We'll handle the photo in the final submit -->
                                <input type="hidden" name="photo_submitted" value="1">
                            <?php endif; ?>
                            
                            <!-- Display member information for confirmation -->
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="mb-3">Personal Information</h5>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th style="width: 35%">Name</th>
                                            <td><?php echo htmlspecialchars($_POST['first_name'] . ' ' . $_POST['last_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Email</th>
                                            <td><?php echo htmlspecialchars($_POST['email']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Phone</th>
                                            <td><?php echo htmlspecialchars($_POST['phone']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Gender</th>
                                            <td><?php echo htmlspecialchars($_POST['gender']); ?></td>
                                        </tr>
                                        <?php if(isset($_POST['dob']) && !empty($_POST['dob'])): ?>
                                        <tr>
                                            <th>Date of Birth</th>
                                            <td><?php echo date('F j, Y', strtotime($_POST['dob'])); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if(isset($_POST['address']) && !empty($_POST['address'])): ?>
                                        <tr>
                                            <th>Address</th>
                                            <td><?php echo nl2br(htmlspecialchars($_POST['address'])); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                    
                                    <h5 class="mb-3 mt-4">Emergency Contact</h5>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th style="width: 35%">Name</th>
                                            <td><?php echo htmlspecialchars($_POST['emergency_contact']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Phone</th>
                                            <td><?php echo htmlspecialchars($_POST['emergency_phone']); ?></td>
                                        </tr>
                                        <?php if(isset($_POST['emergency_relationship']) && !empty($_POST['emergency_relationship'])): ?>
                                        <tr>
                                            <th>Relationship</th>
                                            <td><?php echo htmlspecialchars($_POST['emergency_relationship']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                
                                <div class="col-md-6">
                                    <h5 class="mb-3">Membership Information</h5>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th style="width: 35%">Membership Type</th>
                                            <td>
                                                <?php 
                                                    $membership_id = (int)$_POST['membership_type'];
                                                    $membership_name = "Unknown";
                                                    
                                                    // Get membership type name
                                                    $membership_sql = "SELECT name, price FROM membership_types WHERE id = $membership_id";
                                                    $membership_result = mysqli_query($conn, $membership_sql);
                                                    
                                                    if ($membership_result && mysqli_num_rows($membership_result) > 0) {
                                                        $membership_row = mysqli_fetch_assoc($membership_result);
                                                        $membership_name = $membership_row['name'] . ' (KES ' . number_format($membership_row['price']) . ')';
                                                    }
                                                    
                                                    echo htmlspecialchars($membership_name);
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Registration Date</th>
                                            <td><?php echo date('F j, Y', strtotime($_POST['registration_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Payment Date</th>
                                            <td><?php echo date('F j, Y', strtotime($_POST['payment_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Start Date</th>
                                            <td><?php echo date('F j, Y', strtotime($_POST['start_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Renewal Date</th>
                                            <td><?php echo date('F j, Y', strtotime($_POST['renewal_date'])); ?></td>
                                        </tr>
                                    </table>
                                    
                                    <?php if(isset($_POST['medical_conditions']) && !empty($_POST['medical_conditions']) || 
                                             isset($_POST['height']) && !empty($_POST['height']) || 
                                             isset($_POST['current_weight']) && !empty($_POST['current_weight']) || 
                                             isset($_POST['desired_weight']) && !empty($_POST['desired_weight'])): ?>
                                    <h5 class="mb-3 mt-4">Health Information</h5>
                                    <table class="table table-bordered">
                                        <?php if(isset($_POST['medical_conditions']) && !empty($_POST['medical_conditions'])): ?>
                                        <tr>
                                            <th style="width: 35%">Medical Conditions</th>
                                            <td><?php echo nl2br(htmlspecialchars($_POST['medical_conditions'])); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if(isset($_POST['height']) && !empty($_POST['height'])): ?>
                                        <tr>
                                            <th>Height</th>
                                            <td><?php echo (int)$_POST['height']; ?> cm</td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if(isset($_POST['current_weight']) && !empty($_POST['current_weight'])): ?>
                                        <tr>
                                            <th>Current Weight</th>
                                            <td><?php echo (int)$_POST['current_weight']; ?> kg</td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if(isset($_POST['desired_weight']) && !empty($_POST['desired_weight'])): ?>
                                        <tr>
                                            <th>Desired Weight</th>
                                            <td><?php echo (int)$_POST['desired_weight']; ?> kg</td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                    <?php endif; ?>
                                    
                                    <?php if(isset($_POST['special_comments']) && !empty($_POST['special_comments']) || isset($_POST['social_media_consent'])): ?>
                                    <h5 class="mb-3 mt-4">Additional Information</h5>
                                    <table class="table table-bordered">
                                        <?php if(isset($_POST['special_comments']) && !empty($_POST['special_comments'])): ?>
                                        <tr>
                                            <th style="width: 35%">Special Comments</th>
                                            <td><?php echo nl2br(htmlspecialchars($_POST['special_comments'])); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if(isset($_POST['social_media_consent'])): ?>
                                        <tr>
                                            <th>Social Media Consent</th>
                                            <td>Consented to appear in social media posts</td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mt-4 d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary" onclick="history.back();">
                                    <i class="fas fa-arrow-left me-2"></i> Back to Edit
                                </button>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-user-check me-2"></i> Confirm & Register
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Step 1: Member information form -->
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data" id="registrationForm">
                            <input type="hidden" name="step" value="2">
                            <div class="row">
                                <!-- Personal Information -->
                                <div class="col-md-6">
                                    <h4 class="mb-3">Personal Information</h4>
                                    
                                    <div class="mb-3">
                                        <label for="first_name" class="form-label">First Name *</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="last_name" class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number *</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="gender" class="form-label">Gender *</label>
                                        <select class="form-select" id="gender" name="gender" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="dob" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="dob" name="dob">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="photo" class="form-label">Photo</label>
                                        <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                        <small class="text-muted">Optional: Upload a photo of the member</small>
                                    </div>
                                </div>
                                
                                <!-- Membership & Emergency Contact -->
                                <div class="col-md-6">
                                    <h4 class="mb-3">Membership Information</h4>
                                    
                                    <div class="mb-3">
                                        <label for="membershipType" class="form-label">Membership Type *</label>
                                        <select class="form-select" id="membershipType" name="membership_type" required>
                                            <option value="">Select Membership Type</option>
                                            <?php 
                                            // Reset the result pointer
                                            mysqli_data_seek($membership_result, 0);
                                            while ($membership = mysqli_fetch_assoc($membership_result)): 
                                            ?>
                                            <option value="<?php echo $membership['id']; ?>" data-duration="<?php echo $membership['duration']; ?>">
                                                <?php echo $membership['name']; ?> (KES <?php echo number_format($membership['price']); ?>)
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="registrationDate" class="form-label">Registration Date *</label>
                                        <input type="date" class="form-control" id="registrationDate" name="registration_date" value="<?php echo $today; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="paymentDate" class="form-label">Payment Date *</label>
                                        <input type="date" class="form-control" id="paymentDate" name="payment_date" value="<?php echo $today; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="startDate" class="form-label">Start Date *</label>
                                        <input type="date" class="form-control" id="startDate" name="start_date" value="<?php echo $today; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="renewalDate" class="form-label">Renewal Date *</label>
                                        <input type="date" class="form-control" id="renewalDate" name="renewal_date" required>
                                    </div>
                                    
                                    <h4 class="mb-3 mt-4">Emergency Contact</h4>
                                    
                                    <div class="mb-3">
                                        <label for="emergencyContact" class="form-label">Emergency Contact Name *</label>
                                        <input type="text" class="form-control" id="emergencyContact" name="emergency_contact" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="emergencyPhone" class="form-label">Emergency Contact Phone *</label>
                                        <input type="tel" class="form-control" id="emergencyPhone" name="emergency_phone" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="emergencyRelationship" class="form-label">Relationship</label>
                                        <input type="text" class="form-control" id="emergencyRelationship" name="emergency_relationship">
                                    </div>
                                </div>
                                
                                <!-- Health Information -->
                                <div class="col-md-6">
                                    <h4 class="mb-3 mt-4">Health Information</h4>
                                    
                                    <div class="mb-3">
                                        <label for="medicalConditions" class="form-label">Medical Conditions</label>
                                        <textarea class="form-control" id="medicalConditions" name="medical_conditions" rows="3"></textarea>
                                        <small class="text-muted">Any medical conditions, allergies, or concerns</small>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="height" class="form-label">Height (cm)</label>
                                                <input type="number" class="form-control" id="height" name="height" min="0" max="300">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="currentWeight" class="form-label">Current Weight (kg)</label>
                                                <input type="number" class="form-control" id="currentWeight" name="current_weight" min="0" max="500">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="desiredWeight" class="form-label">Target Weight (kg)</label>
                                                <input type="number" class="form-control" id="desiredWeight" name="desired_weight" min="0" max="500">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Additional Information -->
                                <div class="col-md-6">
                                    <h4 class="mb-3 mt-4">Additional Information</h4>
                                    
                                    <div class="mb-3">
                                        <label for="specialComments" class="form-label">Special Comments</label>
                                        <textarea class="form-control" id="specialComments" name="special_comments" rows="3"></textarea>
                                        <small class="text-muted">Any special notes or comments about this member</small>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="socialMediaConsent" name="social_media_consent" value="1">
                                        <label class="form-check-label" for="socialMediaConsent">Social Media Image Use Consent</label>
                                        <small class="d-block text-muted">Member consents to their image being used on gym social media</small>
                                    </div>
                                </div>
                                
                                <!-- Form Submission -->
                                <div class="col-12 mt-4">
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">
                                            <i class="fas fa-arrow-left me-2"></i> Cancel
                                        </button>
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-check-circle me-2"></i> Continue to Confirmation
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Custom Step Navigation Styling */
.step-nav {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
    position: relative;
    padding: 10px 5px;
}

/* Add connecting line between steps */
.step-nav:before {
    content: '';
    position: absolute;
    top: 50px;
    left: 5%;
    right: 5%;
    height: 4px;
    background-color: #dee2e6;
    z-index: 0;
}

.step-nav .nav-item {
    flex: 1;
    text-align: center;
    position: relative;
    z-index: 1;
    padding: 0 8px;
}

.step-nav .nav-link {
    border-radius: 10px;
    padding: 20px 10px;
    color: #6c757d !important; /* Override global styles */
    position: relative;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    text-align: center;
    display: block;
    font-weight: normal !important; /* Override global styles */
    transition: all 0.3s ease;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

/* Active Step */
.step-nav .nav-link.active {
    background-color: #0d6efd !important; /* Force override */
    color: #fff !important; /* Force override */
    font-weight: 600 !important; /* Force override */
    box-shadow: 0 4px 10px rgba(13, 110, 253, 0.3);
    transform: translateY(-5px);
    border-color: #0d6efd;
}

/* Completed Step */
.step-nav .nav-link.completed {
    background-color: #198754 !important; /* Force override */
    color: #fff !important; /* Force override */
    box-shadow: 0 4px 10px rgba(25, 135, 84, 0.3);
    border-color: #198754;
}

/* Step Number */
.step-nav .step-number {
    display: inline-block;
    width: 44px;
    height: 44px;
    line-height: 44px;
    text-align: center;
    border-radius: 50%;
    background-color: #ffffff;
    color: #212529;
    margin-right: 8px;
    margin-bottom: 8px;
    font-weight: 700;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    font-size: 1.25rem;
    border: 2px solid #dee2e6;
}

/* Active Step Number */
.step-nav .nav-link.active .step-number {
    background-color: #ffffff;
    color: #0d6efd;
    border-color: #0d6efd;
    box-shadow: 0 3px 6px rgba(13, 110, 253, 0.2);
}

/* Completed Step Number */
.step-nav .nav-link.completed .step-number {
    background-color: #ffffff;
    color: #198754;
    border-color: #198754;
    box-shadow: 0 3px 6px rgba(25, 135, 84, 0.2);
}

/* Responsive adjustments */
@media (max-width: 767.98px) {
    .step-nav .nav-link {
        padding: 15px 5px;
    }
    
    .step-nav .step-number {
        width: 36px;
        height: 36px;
        line-height: 36px;
        font-size: 1.1rem;
        margin-right: 0;
        margin-bottom: 5px;
    }
    
    .step-nav:before {
        top: 40px;
    }
}
</style>

<script>
// Enhance registration form with step navigation
document.addEventListener('DOMContentLoaded', function() {
    // For calculating renewal date
    const membershipTypeSelect = document.getElementById('membershipType');
    const startDateInput = document.getElementById('startDate');
    const renewalDateInput = document.getElementById('renewalDate');
    
    // Step navigation
    const currentStep = <?php echo $step; ?>;
    const stepItems = document.querySelectorAll('.step-nav .nav-item');
    
    // Highlight the current step
    if (stepItems.length > 0) {
        // Add 'active' class to current step
        if (currentStep > 0 && currentStep <= stepItems.length) {
            stepItems[currentStep-1].querySelector('.nav-link').classList.add('active');
        }
        
        // Add 'completed' class to previous steps
        for (let i = 0; i < currentStep-1 && i < stepItems.length; i++) {
            stepItems[i].querySelector('.nav-link').classList.add('completed');
        }
    }
    
    // Function to calculate renewal date
    function updateRenewalDate() {
        if (membershipTypeSelect.selectedIndex > 0 && startDateInput.value) {
            const startDate = new Date(startDateInput.value);
            const selectedOption = membershipTypeSelect.options[membershipTypeSelect.selectedIndex];
            const duration = parseInt(selectedOption.getAttribute('data-duration'));
            
            if (duration && !isNaN(duration)) {
                const renewalDate = new Date(startDate);
                renewalDate.setDate(renewalDate.getDate() + duration);
                
                // Format as YYYY-MM-DD
                const year = renewalDate.getFullYear();
                const month = String(renewalDate.getMonth() + 1).padStart(2, '0');
                const day = String(renewalDate.getDate()).padStart(2, '0');
                
                renewalDateInput.value = `${year}-${month}-${day}`;
            }
        }
    }
    
    // Update renewal date when membership type or start date changes
    if (membershipTypeSelect) {
        membershipTypeSelect.addEventListener('change', updateRenewalDate);
    }
    
    if (startDateInput) {
        startDateInput.addEventListener('change', updateRenewalDate);
    }
    
    // Set initial renewal date if values are already set
    if (membershipTypeSelect && startDateInput && membershipTypeSelect.selectedIndex > 0 && startDateInput.value) {
        updateRenewalDate();
    }
    
    // Form validation
    const registrationForm = document.getElementById('registrationForm');
    
    if (registrationForm) {
        registrationForm.addEventListener('submit', function(e) {
            // Any additional form validation can be added here
            return true; // Allow form submission
        });
    }
});
</script>

<?php
// Include footer
include_once 'includes/footer.php';
?>