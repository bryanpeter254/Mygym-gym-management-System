<?php
/**
 * New Member Registration Page with QR Code Generation
 * Compatible version that checks if qrcode_path column exists
 */

// Include header
include_once 'includes/header.php';

// Initialize variables
$step = 1;
$registration_complete = false;
$member = [];

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // STEP 1: Collect member information
    if (isset($_POST['step']) && $_POST['step'] == 1) {
        // Validate and sanitize inputs
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $gender = mysqli_real_escape_string($conn, $_POST['gender']);
        $dob = isset($_POST['dob']) ? mysqli_real_escape_string($conn, $_POST['dob']) : null;
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $emergency_contact = mysqli_real_escape_string($conn, $_POST['emergency_contact']);
        $emergency_phone = mysqli_real_escape_string($conn, $_POST['emergency_phone']);
        $emergency_relationship = mysqli_real_escape_string($conn, $_POST['emergency_relationship']);
        $membership_type_id = (int)$_POST['membership_type_id'];
        $registration_date = date('Y-m-d');
        $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
        $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
        $renewal_date = mysqli_real_escape_string($conn, $_POST['renewal_date']);
        $special_comments = mysqli_real_escape_string($conn, $_POST['special_comments']);
        $medical_conditions = mysqli_real_escape_string($conn, $_POST['medical_conditions']);
        $height = !empty($_POST['height']) ? (int)$_POST['height'] : null;
        $current_weight = !empty($_POST['current_weight']) ? (int)$_POST['current_weight'] : null;
        $desired_weight = !empty($_POST['desired_weight']) ? (int)$_POST['desired_weight'] : null;
        $social_media_consent = isset($_POST['social_media_consent']) ? 1 : 0;
        $photo = null;
        
        // Check if email is already registered
        $check_email_sql = "SELECT id FROM members WHERE email = '$email'";
        $check_email_result = mysqli_query($conn, $check_email_sql);
        
        if (mysqli_num_rows($check_email_result) > 0) {
            $_SESSION['message'] = "This email is already registered. Please use a different email.";
            $_SESSION['message_type'] = "danger";
        } else {
            // Process photo upload if available
            if (isset($_FILES['photo']) && $_FILES['photo']['size'] > 0) {
                $target_dir = "uploads/photos/";
                
                // Create directory if it doesn't exist
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $new_filename = uniqid() . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;
                
                // Check if it's a valid image
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array($file_extension, $allowed_types)) {
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                        $photo = $target_file;
                    }
                }
            }
            
            // Move to step 2
            $step = 2;
        }
    }
    // STEP 2: Confirm and register the member
    elseif (isset($_POST['step']) && $_POST['step'] == 2) {
        // Get all the data from POST
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $gender = mysqli_real_escape_string($conn, $_POST['gender']);
        $dob = !empty($_POST['dob']) ? mysqli_real_escape_string($conn, $_POST['dob']) : null;
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $emergency_contact = mysqli_real_escape_string($conn, $_POST['emergency_contact']);
        $emergency_phone = mysqli_real_escape_string($conn, $_POST['emergency_phone']);
        $emergency_relationship = mysqli_real_escape_string($conn, $_POST['emergency_relationship']);
        $membership_type_id = (int)$_POST['membership_type_id'];
        $registration_date = date('Y-m-d');
        $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
        $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
        $renewal_date = mysqli_real_escape_string($conn, $_POST['renewal_date']);
        $special_comments = mysqli_real_escape_string($conn, $_POST['special_comments']);
        $medical_conditions = mysqli_real_escape_string($conn, $_POST['medical_conditions']);
        $height = !empty($_POST['height']) ? (int)$_POST['height'] : null;
        $current_weight = !empty($_POST['current_weight']) ? (int)$_POST['current_weight'] : null;
        $desired_weight = !empty($_POST['desired_weight']) ? (int)$_POST['desired_weight'] : null;
        $social_media_consent = isset($_POST['social_media_consent']) ? 1 : 0;
        $photo = !empty($_POST['photo']) ? mysqli_real_escape_string($conn, $_POST['photo']) : null;
        
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
        
        // Check if qrcode_path column exists in members table
        $check_column_sql = "SHOW COLUMNS FROM members LIKE 'qrcode_path'";
        $column_exists = mysqli_query($conn, $check_column_sql);
        $has_qrcode_column = (mysqli_num_rows($column_exists) > 0);
        
        // Prepare the SQL statement based on column existence
        if ($has_qrcode_column) {
            // Column exists, include it in the INSERT
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
        } else {
            // Column doesn't exist, exclude it from the INSERT
            $sql = "INSERT INTO members (
                    first_name, last_name, email, phone, gender, dob, address,
                    emergency_contact, emergency_phone, emergency_relationship, membership_type_id,
                    registration_date, payment_date, start_date, renewal_date,
                    special_comments, medical_conditions, height, current_weight, desired_weight,
                    social_media_consent, photo
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
                    ($photo ? "'$photo'" : "NULL") . "
                )";
        }
        
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
                
                // Verify the QR code path is valid and update DB if column exists
                if (!empty($qrcode_path) && $has_qrcode_column) {
                    error_log("QR code generated successfully. Path: " . $qrcode_path);
                    
                    // Update the member record with the QR code path
                    $update_sql = "UPDATE members SET qrcode_path = '" . mysqli_real_escape_string($conn, $qrcode_path) . "' WHERE id = $member_id";
                    $update_result = mysqli_query($conn, $update_sql);
                    
                    if (!$update_result) {
                        error_log("Error updating member with QR code path: " . mysqli_error($conn));
                    } else {
                        error_log("Member record updated with QR code path");
                    }
                } else if (!$has_qrcode_column) {
                    error_log("QR code generated but qrcode_path column doesn't exist in database");
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
                'qrcode_path' => $qrcode_path ?? ''
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
        <div class="col-12">
            <h1 class="mb-4">
                <i class="fas fa-user-plus text-primary me-2"></i>
                Register New Member
            </h1>
            
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['message'];
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php 
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
                                <p class="lead">Your registration is complete. Here's your membership information:</p>
                                
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <strong>Member ID:</strong>
                                                <span><?php echo $member['id']; ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <strong>Email:</strong>
                                                <span><?php echo htmlspecialchars($member['email']); ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <strong>Phone:</strong>
                                                <span><?php echo htmlspecialchars($member['phone']); ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <strong>Membership:</strong>
                                                <span class="badge bg-primary rounded-pill"><?php echo htmlspecialchars($member['membership_type_name']); ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <strong>Renewal Date:</strong>
                                                <span><?php echo date('F j, Y', strtotime($member['renewal_date'])); ?></span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <h5><i class="fas fa-info-circle me-2"></i> What's Next?</h5>
                                    <p>
                                        You can now use your QR code for quick check-in at the gym. 
                                        The staff will explain your membership benefits and give you a tour of the facilities.
                                    </p>
                                </div>
                                
                                <div class="mt-4">
                                    <a href="index.php" class="btn btn-secondary me-2">
                                        <i class="fas fa-home me-2"></i> Go to Home
                                    </a>
                                    <a href="register-new.php" class="btn btn-primary">
                                        <i class="fas fa-user-plus me-2"></i> Register Another Member
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($step == 1): ?>
                <!-- Step 1: Member Information Form -->
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="step" value="1">
                    
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Personal Information</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="firstName" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="firstName" name="first_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="lastName" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="lastName" name="last_name" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone *</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label">Gender *</label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="dob" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="dob" name="dob">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address *</label>
                                <textarea class="form-control" id="address" name="address" rows="2" required></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="height" class="form-label">Height (cm)</label>
                                    <input type="number" class="form-control" id="height" name="height">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="currentWeight" class="form-label">Current Weight (kg)</label>
                                    <input type="number" class="form-control" id="currentWeight" name="current_weight">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="desiredWeight" class="form-label">Target Weight (kg)</label>
                                    <input type="number" class="form-control" id="desiredWeight" name="desired_weight">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="medicalConditions" class="form-label">Medical Conditions</label>
                                <textarea class="form-control" id="medicalConditions" name="medical_conditions" rows="2"></textarea>
                                <div class="form-text">List any medical conditions or limitations that may affect your workout.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="photo" class="form-label">Profile Photo</label>
                                <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="socialMediaConsent" name="social_media_consent">
                                <label class="form-check-label" for="socialMediaConsent">I consent to my image being used on gym social media</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Emergency Contact</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="emergencyContact" class="form-label">Emergency Contact Name *</label>
                                    <input type="text" class="form-control" id="emergencyContact" name="emergency_contact" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="emergencyPhone" class="form-label">Emergency Contact Phone *</label>
                                    <input type="tel" class="form-control" id="emergencyPhone" name="emergency_phone" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="emergencyRelationship" class="form-label">Relationship to Member *</label>
                                <input type="text" class="form-control" id="emergencyRelationship" name="emergency_relationship" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Membership Details</h4>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="membershipType" class="form-label">Membership Type *</label>
                                <select class="form-select" id="membershipType" name="membership_type_id" required>
                                    <option value="">Select Membership Type</option>
                                    <?php
                                    $membership_types_sql = "SELECT * FROM membership_types ORDER BY id";
                                    $membership_types_result = mysqli_query($conn, $membership_types_sql);
                                    
                                    if ($membership_types_result && mysqli_num_rows($membership_types_result) > 0) {
                                        while ($type = mysqli_fetch_assoc($membership_types_result)) {
                                            echo '<option value="' . $type['id'] . '">' . htmlspecialchars($type['name']) . ' - ' . htmlspecialchars($type['price']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="paymentDate" class="form-label">Payment Date *</label>
                                    <input type="date" class="form-control" id="paymentDate" name="payment_date" value="<?php echo $today; ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="startDate" class="form-label">Start Date *</label>
                                    <input type="date" class="form-control" id="startDate" name="start_date" value="<?php echo $today; ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="renewalDate" class="form-label">Renewal Date *</label>
                                    <input type="date" class="form-control" id="renewalDate" name="renewal_date" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="specialComments" class="form-label">Special Comments</label>
                                <textarea class="form-control" id="specialComments" name="special_comments" rows="2"></textarea>
                                <div class="form-text">Any special requirements or notes for this membership.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-end mb-4">
                        <a href="members.php" class="btn btn-secondary me-2">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-arrow-right me-2"></i> Continue to Confirmation
                        </button>
                    </div>
                </form>
                
                <script>
                    // Calculate renewal date based on membership type
                    document.addEventListener('DOMContentLoaded', function() {
                        const membershipTypeSelect = document.getElementById('membershipType');
                        const startDateInput = document.getElementById('startDate');
                        const renewalDateInput = document.getElementById('renewalDate');
                        
                        function updateRenewalDate() {
                            const startDate = new Date(startDateInput.value);
                            const selectedOption = membershipTypeSelect.options[membershipTypeSelect.selectedIndex];
                            const membershipText = selectedOption.text.toLowerCase();
                            
                            if (startDate && !isNaN(startDate.getTime())) {
                                let renewalDate = new Date(startDate);
                                
                                if (membershipText.includes('daily')) {
                                    renewalDate.setDate(renewalDate.getDate() + 1);
                                } else if (membershipText.includes('weekly')) {
                                    renewalDate.setDate(renewalDate.getDate() + 7);
                                } else if (membershipText.includes('3 months')) {
                                    renewalDate.setMonth(renewalDate.getMonth() + 3);
                                } else if (membershipText.includes('6 months')) {
                                    renewalDate.setMonth(renewalDate.getMonth() + 6);
                                } else if (membershipText.includes('12 months')) {
                                    renewalDate.setFullYear(renewalDate.getFullYear() + 1);
                                } else {
                                    // Default to monthly for all other options
                                    renewalDate.setMonth(renewalDate.getMonth() + 1);
                                }
                                
                                // Format renewal date as YYYY-MM-DD
                                const year = renewalDate.getFullYear();
                                const month = String(renewalDate.getMonth() + 1).padStart(2, '0');
                                const day = String(renewalDate.getDate()).padStart(2, '0');
                                renewalDateInput.value = `${year}-${month}-${day}`;
                            }
                        }
                        
                        // Update renewal date when membership type or start date changes
                        membershipTypeSelect.addEventListener('change', updateRenewalDate);
                        startDateInput.addEventListener('change', updateRenewalDate);
                        
                        // Initial update
                        if (startDateInput.value) {
                            updateRenewalDate();
                        }
                    });
                </script>
            <?php elseif ($step == 2): ?>
                <!-- Step 2: Confirmation Page -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Confirm Member Information</h4>
                    </div>
                    <div class="card-body">
                        <p class="lead">Please review the information below before proceeding to QR code creation.</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">Personal Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <dl class="row mb-0">
                                            <dt class="col-sm-4">Full Name:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></dd>
                                            
                                            <dt class="col-sm-4">Email:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($email); ?></dd>
                                            
                                            <dt class="col-sm-4">Phone:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($phone); ?></dd>
                                            
                                            <dt class="col-sm-4">Gender:</dt>
                                            <dd class="col-sm-8"><?php echo ucfirst(htmlspecialchars($gender)); ?></dd>
                                            
                                            <dt class="col-sm-4">Date of Birth:</dt>
                                            <dd class="col-sm-8"><?php echo $dob ? date('F j, Y', strtotime($dob)) : 'Not specified'; ?></dd>
                                            
                                            <dt class="col-sm-4">Address:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($address); ?></dd>
                                            
                                            <?php if ($height || $current_weight || $desired_weight): ?>
                                                <dt class="col-sm-4">Physical:</dt>
                                                <dd class="col-sm-8">
                                                    <?php if ($height): ?>Height: <?php echo $height; ?> cm<br><?php endif; ?>
                                                    <?php if ($current_weight): ?>Current Weight: <?php echo $current_weight; ?> kg<br><?php endif; ?>
                                                    <?php if ($desired_weight): ?>Target Weight: <?php echo $desired_weight; ?> kg<?php endif; ?>
                                                </dd>
                                            <?php endif; ?>
                                            
                                            <?php if ($medical_conditions): ?>
                                                <dt class="col-sm-4">Medical:</dt>
                                                <dd class="col-sm-8"><?php echo htmlspecialchars($medical_conditions); ?></dd>
                                            <?php endif; ?>
                                        </dl>
                                    </div>
                                </div>
                                
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">Emergency Contact</h5>
                                    </div>
                                    <div class="card-body">
                                        <dl class="row mb-0">
                                            <dt class="col-sm-4">Name:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($emergency_contact); ?></dd>
                                            
                                            <dt class="col-sm-4">Phone:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($emergency_phone); ?></dd>
                                            
                                            <dt class="col-sm-4">Relationship:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($emergency_relationship); ?></dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">Membership Details</h5>
                                    </div>
                                    <div class="card-body">
                                        <dl class="row mb-0">
                                            <dt class="col-sm-4">Type:</dt>
                                            <dd class="col-sm-8">
                                                <?php
                                                $membership_sql = "SELECT name, price FROM membership_types WHERE id = " . (int)$membership_type_id;
                                                $membership_result = mysqli_query($conn, $membership_sql);
                                                
                                                if ($membership_result && mysqli_num_rows($membership_result) > 0) {
                                                    $membership = mysqli_fetch_assoc($membership_result);
                                                    echo htmlspecialchars($membership['name'] . ' - ' . $membership['price']);
                                                } else {
                                                    echo 'Unknown';
                                                }
                                                ?>
                                            </dd>
                                            
                                            <dt class="col-sm-4">Payment Date:</dt>
                                            <dd class="col-sm-8"><?php echo date('F j, Y', strtotime($payment_date)); ?></dd>
                                            
                                            <dt class="col-sm-4">Start Date:</dt>
                                            <dd class="col-sm-8"><?php echo date('F j, Y', strtotime($start_date)); ?></dd>
                                            
                                            <dt class="col-sm-4">Renewal Date:</dt>
                                            <dd class="col-sm-8"><?php echo date('F j, Y', strtotime($renewal_date)); ?></dd>
                                            
                                            <?php if ($special_comments): ?>
                                                <dt class="col-sm-4">Comments:</dt>
                                                <dd class="col-sm-8"><?php echo htmlspecialchars($special_comments); ?></dd>
                                            <?php endif; ?>
                                            
                                            <dt class="col-sm-4">Social Media:</dt>
                                            <dd class="col-sm-8"><?php echo $social_media_consent ? 'Consent given' : 'No consent'; ?></dd>
                                        </dl>
                                    </div>
                                </div>
                                
                                <?php if ($photo): ?>
                                    <div class="card mb-3">
                                        <div class="card-header bg-light">
                                            <h5 class="mb-0">Profile Photo</h5>
                                        </div>
                                        <div class="card-body text-center">
                                            <img src="<?php echo $photo; ?>" class="img-fluid rounded" style="max-height: 200px;" alt="Profile Photo">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="step" value="2">
                    <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>">
                    <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <input type="hidden" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                    <input type="hidden" name="gender" value="<?php echo htmlspecialchars($gender); ?>">
                    <input type="hidden" name="dob" value="<?php echo htmlspecialchars($dob); ?>">
                    <input type="hidden" name="address" value="<?php echo htmlspecialchars($address); ?>">
                    <input type="hidden" name="emergency_contact" value="<?php echo htmlspecialchars($emergency_contact); ?>">
                    <input type="hidden" name="emergency_phone" value="<?php echo htmlspecialchars($emergency_phone); ?>">
                    <input type="hidden" name="emergency_relationship" value="<?php echo htmlspecialchars($emergency_relationship); ?>">
                    <input type="hidden" name="membership_type_id" value="<?php echo (int)$membership_type_id; ?>">
                    <input type="hidden" name="payment_date" value="<?php echo htmlspecialchars($payment_date); ?>">
                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    <input type="hidden" name="renewal_date" value="<?php echo htmlspecialchars($renewal_date); ?>">
                    <input type="hidden" name="special_comments" value="<?php echo htmlspecialchars($special_comments); ?>">
                    <input type="hidden" name="medical_conditions" value="<?php echo htmlspecialchars($medical_conditions); ?>">
                    <input type="hidden" name="height" value="<?php echo (int)$height; ?>">
                    <input type="hidden" name="current_weight" value="<?php echo (int)$current_weight; ?>">
                    <input type="hidden" name="desired_weight" value="<?php echo (int)$desired_weight; ?>">
                    <?php if ($social_media_consent): ?>
                        <input type="hidden" name="social_media_consent" value="1">
                    <?php endif; ?>
                    <?php if ($photo): ?>
                        <input type="hidden" name="photo" value="<?php echo htmlspecialchars($photo); ?>">
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between mb-4">
                        <button type="button" class="btn btn-secondary" onclick="window.history.back();">
                            <i class="fas fa-arrow-left me-2"></i> Back to Member Information
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-qrcode me-2"></i> Continue to QR Code Generation
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>