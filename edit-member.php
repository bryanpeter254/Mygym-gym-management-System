<?php
// Include header
include_once 'includes/header.php';

// Initialize variables
$message = '';
$message_type = '';
$member = null;

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "No member ID provided for editing.";
    $_SESSION['message_type'] = "danger";
    header('Location: members.php');
    exit;
}

// Get member ID from URL
$member_id = (int)$_GET['id'];

// Fetch member data
$sql = "SELECT * FROM members WHERE id = $member_id";
$result = mysqli_query($conn, $sql);

// Check if member exists
if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['message'] = "Member not found!";
    $_SESSION['message_type'] = "danger";
    header('Location: members.php');
    exit;
}

// Get member data
$member = mysqli_fetch_assoc($result);

// Get membership types for dropdown
$membership_types_sql = "SELECT * FROM membership_types ORDER BY name ASC";
$membership_types_result = mysqli_query($conn, $membership_types_sql);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $dob = !empty($_POST['dob']) ? mysqli_real_escape_string($conn, $_POST['dob']) : null;
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    $membership_type_id = (int)$_POST['membership_type_id'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $emergency_contact = mysqli_real_escape_string($conn, $_POST['emergency_contact'] ?? '');
    $emergency_phone = mysqli_real_escape_string($conn, $_POST['emergency_phone'] ?? '');
    $emergency_relationship = mysqli_real_escape_string($conn, $_POST['emergency_relationship'] ?? '');
    $start_date = !empty($_POST['start_date']) ? mysqli_real_escape_string($conn, $_POST['start_date']) : null;
    $renewal_date = !empty($_POST['renewal_date']) ? mysqli_real_escape_string($conn, $_POST['renewal_date']) : null;
    $special_comments = mysqli_real_escape_string($conn, $_POST['special_comments'] ?? '');
    
    // Validate data
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
        $message = "Please fill in all required fields.";
        $message_type = "danger";
    } else {
        // Check if email is already in use by another member
        $check_email_sql = "SELECT id FROM members WHERE email = '$email' AND id != $member_id";
        $check_email_result = mysqli_query($conn, $check_email_sql);
        
        if ($check_email_result && mysqli_num_rows($check_email_result) > 0) {
            $message = "Email address is already in use by another member.";
            $message_type = "danger";
        } else {
            // Prepare the update query
            $update_sql = "UPDATE members SET 
                          first_name = '$first_name',
                          last_name = '$last_name',
                          email = '$email',
                          phone = '$phone',
                          gender = '$gender',
                          ";
            
            // Add optional fields
            $update_sql .= $dob ? "dob = '$dob', " : "dob = NULL, ";
            $update_sql .= "address = '$address', ";
            $update_sql .= "membership_type_id = $membership_type_id, ";
            $update_sql .= "status = '$status', ";
            $update_sql .= "emergency_contact = '$emergency_contact', ";
            $update_sql .= "emergency_phone = '$emergency_phone', ";
            $update_sql .= "emergency_relationship = '$emergency_relationship', ";
            $update_sql .= $start_date ? "start_date = '$start_date', " : "start_date = NULL, ";
            $update_sql .= $renewal_date ? "renewal_date = '$renewal_date', " : "renewal_date = NULL, ";
            $update_sql .= "special_comments = '$special_comments'";
            
            // Add where clause
            $update_sql .= " WHERE id = $member_id";
            
            // Execute update
            if (mysqli_query($conn, $update_sql)) {
                // Handle photo upload if a new one is provided
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
                    $upload_dir = 'uploads/member_photos/';
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_name = $member_id . '_' . time() . '_' . basename($_FILES['photo']['name']);
                    $upload_path = $upload_dir . $file_name;
                    
                    // Move uploaded file
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                        // Delete old photo if exists
                        if (!empty($member['photo']) && file_exists($member['photo'])) {
                            @unlink($member['photo']);
                        }
                        
                        // Update photo path in database
                        mysqli_query($conn, "UPDATE members SET photo = '$upload_path' WHERE id = $member_id");
                    }
                }
                
                $_SESSION['message'] = "Member updated successfully!";
                $_SESSION['message_type'] = "success";
                header('Location: member.php?id=' . $member_id);
                exit;
            } else {
                $message = "Error updating member: " . mysqli_error($conn);
                $message_type = "danger";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Member - <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></title>
    <!-- Bootstrap CSS is included in header.php -->
    <style>
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .form-section:last-child {
            border-bottom: none;
        }
        .form-section-title {
            margin-bottom: 20px;
            font-weight: 600;
            color: #333;
        }
        .member-photo-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 15px;
            border: 3px solid #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .required-field {
            color: #dc3545;
        }
    </style>
</head>
<body>

<div class="container py-4">
    <!-- Back button and page title -->
    <div class="d-flex justify-content-between mb-3">
        <a href="member.php?id=<?php echo $member_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Member
        </a>
        <a href="members.php" class="btn btn-outline-secondary">
            <i class="fas fa-users me-2"></i> All Members
        </a>
    </div>
    
    <h1 class="mb-4">Edit Member</h1>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Edit Member Form -->
    <form method="POST" action="" enctype="multipart/form-data" class="card">
        <div class="card-body">
            <!-- Personal Information Section -->
            <div class="form-section">
                <h4 class="form-section-title"><i class="fas fa-user me-2"></i> Personal Information</h4>
                <div class="row">
                    <div class="col-md-3 text-center">
                        <!-- Photo Upload -->
                        <?php if (!empty($member['photo']) && file_exists($member['photo'])): ?>
                            <img src="<?php echo $member['photo']; ?>" alt="Member Photo" class="member-photo-preview" id="photoPreview">
                        <?php else: ?>
                            <div class="member-photo-preview bg-light d-flex align-items-center justify-content-center" id="photoPreview">
                                <i class="fas fa-user fa-4x text-secondary"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="photo" class="form-label">Member Photo</label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                            <div class="form-text">Optional. Upload a new photo to replace the current one.</div>
                        </div>
                    </div>
                    
                    <div class="col-md-9">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name <span class="required-field">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($member['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name <span class="required-field">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($member['last_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address <span class="required-field">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($member['email']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number <span class="required-field">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($member['phone']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="gender" class="form-label">Gender <span class="required-field">*</span></label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="male" <?php echo $member['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo $member['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="dob" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="dob" name="dob" value="<?php echo $member['dob']; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="status" class="form-label">Status <span class="required-field">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="Active" <?php echo $member['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?php echo $member['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="Expired" <?php echo $member['status'] === 'Expired' ? 'selected' : ''; ?>>Expired</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($member['address']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Membership Information Section -->
            <div class="form-section">
                <h4 class="form-section-title"><i class="fas fa-id-card me-2"></i> Membership Information</h4>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="membership_type_id" class="form-label">Membership Type <span class="required-field">*</span></label>
                        <select class="form-select" id="membership_type_id" name="membership_type_id" required>
                            <option value="">Select Membership Type</option>
                            <?php if ($membership_types_result && mysqli_num_rows($membership_types_result) > 0): ?>
                                <?php while ($type = mysqli_fetch_assoc($membership_types_result)): ?>
                                    <option value="<?php echo $type['id']; ?>" 
                                            <?php echo $member['membership_type_id'] == $type['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $member['start_date']; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="renewal_date" class="form-label">Renewal Date</label>
                        <input type="date" class="form-control" id="renewal_date" name="renewal_date" value="<?php echo $member['renewal_date']; ?>">
                    </div>
                </div>
            </div>
            
            <!-- Emergency Contact Section -->
            <div class="form-section">
                <h4 class="form-section-title"><i class="fas fa-ambulance me-2"></i> Emergency Contact</h4>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="emergency_contact" class="form-label">Contact Name</label>
                        <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" value="<?php echo htmlspecialchars($member['emergency_contact']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="emergency_phone" class="form-label">Contact Phone</label>
                        <input type="tel" class="form-control" id="emergency_phone" name="emergency_phone" value="<?php echo htmlspecialchars($member['emergency_phone']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="emergency_relationship" class="form-label">Relationship</label>
                        <input type="text" class="form-control" id="emergency_relationship" name="emergency_relationship" value="<?php echo htmlspecialchars($member['emergency_relationship']); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Additional Information Section -->
            <div class="form-section">
                <h4 class="form-section-title"><i class="fas fa-info-circle me-2"></i> Additional Information</h4>
                <div class="mb-3">
                    <label for="special_comments" class="form-label">Special Comments</label>
                    <textarea class="form-control" id="special_comments" name="special_comments" rows="3"><?php echo htmlspecialchars($member['special_comments']); ?></textarea>
                    <div class="form-text">Add any notes, medical conditions, or special requirements.</div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <a href="member.php?id=<?php echo $member_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i> Save Changes
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Photo preview functionality
    const photoInput = document.getElementById('photo');
    const photoPreview = document.getElementById('photoPreview');
    
    photoInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                // If photoPreview is a div with an icon
                if (photoPreview.tagName === 'DIV') {
                    // Replace div with an img element
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'member-photo-preview';
                    img.id = 'photoPreview';
                    photoPreview.parentNode.replaceChild(img, photoPreview);
                } else {
                    // Just update the src of the existing img
                    photoPreview.src = e.target.result;
                }
            }
            
            reader.readAsDataURL(this.files[0]);
        }
    });
});
</script>

<?php
// Include footer
include_once 'includes/footer.php';
?>