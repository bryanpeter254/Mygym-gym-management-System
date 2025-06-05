<?php
// Include header
include_once 'includes/header.php';

// Initialize variables
$message = '';
$message_type = '';
$edit_id = 0;
$edit_data = null;

// Process form submission for adding/updating membership type
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine if we're adding or editing
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    
    // Validate and sanitize inputs
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    // We'll skip duration_days since the column doesn't exist
    $description = isset($_POST['description']) ? mysqli_real_escape_string($conn, $_POST['description']) : '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Basic validation
    if (empty($name) || empty($price)) {
        $message = "Name and price are required fields.";
        $message_type = "danger";
    } else {
        if ($edit_id > 0) {
            // Update existing membership type - removed duration_days
            $sql = "UPDATE membership_types SET 
                    name = '$name', 
                    price = '$price', 
                    description = '$description'";
                    
            // Only include is_active if your table has this column
            $check_column_sql = "SHOW COLUMNS FROM membership_types LIKE 'is_active'";
            $check_column_result = mysqli_query($conn, $check_column_sql);
            if ($check_column_result && mysqli_num_rows($check_column_result) > 0) {
                $sql .= ", is_active = $is_active";
            }
            
            $sql .= " WHERE id = $edit_id";
                    
            if (mysqli_query($conn, $sql)) {
                $message = "Membership type updated successfully!";
                $message_type = "success";
                $edit_id = 0; // Reset edit mode
            } else {
                $message = "Error updating membership type: " . mysqli_error($conn);
                $message_type = "danger";
            }
        } else {
            // Insert new membership type - removed duration_days
            $sql = "INSERT INTO membership_types (name, price";
            
            // Only include additional columns if they exist
            if (!empty($description)) {
                $sql .= ", description";
            }
            
            // Check if is_active column exists
            $check_column_sql = "SHOW COLUMNS FROM membership_types LIKE 'is_active'";
            $check_column_result = mysqli_query($conn, $check_column_sql);
            if ($check_column_result && mysqli_num_rows($check_column_result) > 0) {
                $sql .= ", is_active";
            }
            
            $sql .= ") VALUES ('$name', '$price'";
            
            // Add values for additional columns if they exist
            if (!empty($description)) {
                $sql .= ", '$description'";
            }
            
            // Add is_active value if column exists
            if ($check_column_result && mysqli_num_rows($check_column_result) > 0) {
                $sql .= ", $is_active";
            }
            
            $sql .= ")";
                    
            if (mysqli_query($conn, $sql)) {
                $message = "New membership type added successfully!";
                $message_type = "success";
            } else {
                $message = "Error adding membership type: " . mysqli_error($conn);
                $message_type = "danger";
            }
        }
    }
}

// Process deletion
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $delete_id = (int)$_GET['delete'];
    
    // Check if membership type is being used by any members
    $check_sql = "SELECT COUNT(*) as count FROM members WHERE membership_type_id = $delete_id";
    $check_result = mysqli_query($conn, $check_sql);
    $check_data = mysqli_fetch_assoc($check_result);
    
    if ($check_data['count'] > 0) {
        $message = "Cannot delete this membership type because it is being used by {$check_data['count']} member(s).";
        $message_type = "warning";
    } else {
        // Safe to delete
        $delete_sql = "DELETE FROM membership_types WHERE id = $delete_id";
        
        if (mysqli_query($conn, $delete_sql)) {
            $message = "Membership type deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting membership type: " . mysqli_error($conn);
            $message_type = "danger";
        }
    }
}

// Process edit request
if (isset($_GET['edit']) && $_GET['edit'] > 0) {
    $edit_id = (int)$_GET['edit'];
    $edit_sql = "SELECT * FROM membership_types WHERE id = $edit_id";
    $edit_result = mysqli_query($conn, $edit_sql);
    
    if ($edit_result && mysqli_num_rows($edit_result) > 0) {
        $edit_data = mysqli_fetch_assoc($edit_result);
    } else {
        $message = "Membership type not found!";
        $message_type = "danger";
        $edit_id = 0;
    }
}

// Get all membership types - updated to not use duration_days
$sql = "SELECT * FROM membership_types ORDER BY name ASC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Membership Types</title>
    <!-- Bootstrap CSS is included in header.php -->
    <style>
        .membership-card {
            height: 100%;
            transition: all 0.3s ease;
        }
        .membership-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .membership-price {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .inactive-membership {
            opacity: 0.7;
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="fas fa-tags text-primary me-2"></i> Membership Types</h1>
            <p class="lead">Manage your gym's membership options and pricing</p>
        </div>
        <div class="col-md-4 text-end align-self-center">
            <?php if (!$edit_id): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMembershipModal">
                    <i class="fas fa-plus-circle me-2"></i> Add New Membership
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($edit_id && $edit_data): ?>
        <!-- Edit Form -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Edit Membership Type</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Membership Name *</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($edit_data['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="price" class="form-label">Price *</label>
                            <div class="input-group">
                                <span class="input-group-text">KES</span>
                                <input type="text" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($edit_data['price']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (isset($edit_data['is_active'])): ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="is_active" class="form-label">Status</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?php echo $edit_data['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                            <div class="form-text">Inactive membership types won't be shown in registration forms.</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($edit_data['description'])): ?>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($edit_data['description']); ?></textarea>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between">
                        <a href="membership-types.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Update Membership Type
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Membership Types Grid -->
    <div class="row">
        <?php if ($result && mysqli_num_rows($result) > 0): ?>
            <?php while ($membership = mysqli_fetch_assoc($result)): ?>
                <div class="col-md-4 col-lg-3 mb-4">
                    <div class="card membership-card <?php echo isset($membership['is_active']) && !$membership['is_active'] ? 'inactive-membership' : ''; ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($membership['name']); ?></h5>
                            <div class="membership-price text-primary mb-2">
                                <?php echo htmlspecialchars($membership['price']); ?>
                            </div>
                            
                            <?php if (isset($membership['description']) && !empty($membership['description'])): ?>
                                <p class="card-text small">
                                    <?php echo nl2br(htmlspecialchars($membership['description'])); ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if (isset($membership['is_active']) && !$membership['is_active']): ?>
                                <div class="badge bg-secondary mb-2">Inactive</div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between mt-3">
                                <a href="membership-types.php?edit=<?php echo $membership['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                
                                <a href="#" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteMembershipModal<?php echo $membership['id']; ?>">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Delete Confirmation Modal -->
                    <div class="modal fade" id="deleteMembershipModal<?php echo $membership['id']; ?>" tabindex="-1" aria-labelledby="deleteMembershipModalLabel<?php echo $membership['id']; ?>" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title" id="deleteMembershipModalLabel<?php echo $membership['id']; ?>">Confirm Deletion</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Are you sure you want to delete the <strong><?php echo htmlspecialchars($membership['name']); ?></strong> membership type?</p>
                                    <p class="mb-0"><strong>Note:</strong> This action cannot be undone. If members are using this membership type, you won't be able to delete it.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <a href="membership-types.php?delete=<?php echo $membership['id']; ?>" class="btn btn-danger">Delete</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No membership types found. Click "Add New Membership" to create your first membership type.
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Add New Membership Modal -->
    <div class="modal fade" id="addMembershipModal" tabindex="-1" aria-labelledby="addMembershipModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addMembershipModalLabel">Add New Membership Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="new_name" class="form-label">Membership Name *</label>
                                <input type="text" class="form-control" id="new_name" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="new_price" class="form-label">Price *</label>
                                <div class="input-group">
                                    <span class="input-group-text">KES</span>
                                    <input type="text" class="form-control" id="new_price" name="price" required>
                                </div>
                            </div>
                        </div>
                        
                        <?php
                        // Check if is_active column exists
                        $check_is_active_sql = "SHOW COLUMNS FROM membership_types LIKE 'is_active'";
                        $check_is_active_result = mysqli_query($conn, $check_is_active_sql);
                        if ($check_is_active_result && mysqli_num_rows($check_is_active_result) > 0):
                        ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="new_is_active" class="form-label">Status</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="new_is_active" name="is_active" checked>
                                    <label class="form-check-label" for="new_is_active">Active</label>
                                </div>
                                <div class="form-text">Inactive membership types won't be shown in registration forms.</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php
                        // Check if description column exists
                        $check_desc_sql = "SHOW COLUMNS FROM membership_types LIKE 'description'";
                        $check_desc_result = mysqli_query($conn, $check_desc_sql);
                        if ($check_desc_result && mysqli_num_rows($check_desc_result) > 0):
                        ?>
                        <div class="mb-3">
                            <label for="new_description" class="form-label">Description</label>
                            <textarea class="form-control" id="new_description" name="description" rows="3" placeholder="Enter any special features or benefits of this membership type"></textarea>
                        </div>
                        <?php endif; ?>
                        
                        <div class="modal-footer px-0 pb-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Membership Type</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Templates Section -->
    <div class="card mt-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-bolt me-2"></i> Quick Templates</h5>
        </div>
        <div class="card-body">
            <p>Quickly add standard membership types with preset names and prices. Click any template to pre-fill the add form.</p>
            
            <div class="row">
                <div class="col-md-3 mb-2">
                    <button type="button" class="btn btn-outline-primary w-100 template-btn" 
                            data-name="Daily Access" 
                            data-price="500" 
                            data-description="Valid for one day only. Perfect for visitors or one-time users.">
                        Daily Access (KES 500)
                    </button>
                </div>
                <div class="col-md-3 mb-2">
                    <button type="button" class="btn btn-outline-primary w-100 template-btn" 
                            data-name="Weekly Access" 
                            data-price="2,000" 
                            data-description="Valid for 7 days from activation. Great for travelers or short-term visitors.">
                        Weekly Access (KES 2,000)
                    </button>
                </div>
                <div class="col-md-3 mb-2">
                    <button type="button" class="btn btn-outline-primary w-100 template-btn" 
                            data-name="Monthly Off-Peak" 
                            data-price="4,000" 
                            data-description="Access during off-peak hours only (10am-4pm weekdays, all day weekends). Valid for 30 days.">
                        Monthly Off-Peak (KES 4,000)
                    </button>
                </div>
                <div class="col-md-3 mb-2">
                    <button type="button" class="btn btn-outline-primary w-100 template-btn" 
                            data-name="Standard Monthly" 
                            data-price="5,000" 
                            data-description="Full access to gym facilities. Valid for 30 days from activation.">
                        Standard Monthly (KES 5,000)
                    </button>
                </div>
            </div>
            
            <div class="row mt-2">
                <div class="col-md-3 mb-2">
                    <button type="button" class="btn btn-outline-primary w-100 template-btn" 
                            data-name="Duo Monthly" 
                            data-price="9,000" 
                            data-description="Membership for couples or friends. Allows two people to access gym under one membership. Valid for 30 days.">
                        Duo Monthly (KES 9,000)
                    </button>
                </div>
                <div class="col-md-3 mb-2">
                    <button type="button" class="btn btn-outline-primary w-100 template-btn" 
                            data-name="3 Months" 
                            data-price="14,000" 
                            data-description="Full access to gym facilities. Valid for 3 months (90 days) from activation.">
                        3 Months (KES 14,000)
                    </button>
                </div>
                <div class="col-md-3 mb-2">
                    <button type="button" class="btn btn-outline-primary w-100 template-btn" 
                            data-name="6 Months" 
                            data-price="27,000" 
                            data-description="Full access to gym facilities. Valid for 6 months (180 days) from activation.">
                        6 Months (KES 27,000)
                    </button>
                </div>
                <div class="col-md-3 mb-2">
                    <button type="button" class="btn btn-outline-primary w-100 template-btn" 
                            data-name="12 Months" 
                            data-price="52,000" 
                            data-description="Full access to gym facilities. Valid for 12 months (365 days) from activation.">
                        12 Months (KES 52,000)
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Navigation buttons -->
    <div class="mt-4">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- JavaScript for template buttons -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle quick template buttons
    const templateButtons = document.querySelectorAll('.template-btn');
    
    templateButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const name = this.getAttribute('data-name');
            const price = this.getAttribute('data-price');
            const description = this.getAttribute('data-description');
            
            // Open the modal
            const addModal = new bootstrap.Modal(document.getElementById('addMembershipModal'));
            addModal.show();
            
            // Set the values
            document.getElementById('new_name').value = name;
            document.getElementById('new_price').value = price;
            
            // Set description if the field exists
            const descriptionField = document.getElementById('new_description');
            if (descriptionField) {
                descriptionField.value = description;
            }
        });
    });
});
</script>

<?php
// Include footer
include_once 'includes/footer.php';
?>