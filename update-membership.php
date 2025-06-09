<?php
// Include header
include_once 'includes/header.php';

// Initialize variables
$message = '';
$message_type = '';
$member = null;
$today = date('Y-m-d');

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: members.php');
    exit;
}

// Get member ID from URL
$member_id = (int)$_GET['id'];

// Fetch member data
$sql = "SELECT m.*, mt.name as membership_type_name, mt.price as membership_price 
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
$registration_date = !empty($member['registration_date']) ? date('Y-m-d', strtotime($member['registration_date'])) : '';
$payment_date = !empty($member['payment_date']) ? date('Y-m-d', strtotime($member['payment_date'])) : $today;
$start_date = !empty($member['start_date']) ? date('Y-m-d', strtotime($member['start_date'])) : $today;
$renewal_date = !empty($member['renewal_date']) ? date('Y-m-d', strtotime($member['renewal_date'])) : '';
$is_expired = !empty($renewal_date) && $renewal_date < $today;

// Calculate default renewal date (today + 30 days)
$default_new_renewal_date = date('Y-m-d', strtotime('+30 days'));

// If already expired, set default start date to today
// If not expired, set default start date to day after current expiry
$default_new_start_date = $is_expired ? $today : date('Y-m-d', strtotime($renewal_date . ' +1 day'));

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $membership_type_id = isset($_POST['membership_type_id']) ? (int)$_POST['membership_type_id'] : $member['membership_type_id'];
    $payment_amount = isset($_POST['payment_amount']) ? mysqli_real_escape_string($conn, $_POST['payment_amount']) : '';
    $payment_method = isset($_POST['payment_method']) ? mysqli_real_escape_string($conn, $_POST['payment_method']) : '';
    $new_payment_date = isset($_POST['payment_date']) ? mysqli_real_escape_string($conn, $_POST['payment_date']) : $today;
    $new_start_date = isset($_POST['start_date']) ? mysqli_real_escape_string($conn, $_POST['start_date']) : $default_new_start_date;
    $new_renewal_date = isset($_POST['renewal_date']) ? mysqli_real_escape_string($conn, $_POST['renewal_date']) : $default_new_renewal_date;
    $payment_reference = isset($_POST['payment_reference']) ? mysqli_real_escape_string($conn, $_POST['payment_reference']) : '';
    $comments = isset($_POST['comments']) ? mysqli_real_escape_string($conn, $_POST['comments']) : '';
    
    // Validate data
    if (empty($membership_type_id) || empty($payment_amount) || empty($new_payment_date) || empty($new_start_date) || empty($new_renewal_date)) {
        $message = "Please fill in all required fields.";
        $message_type = "danger";
    } else {
        // Update membership information
        $update_sql = "UPDATE members SET 
                      membership_type_id = $membership_type_id,
                      payment_date = '$new_payment_date',
                      start_date = '$new_start_date',
                      renewal_date = '$new_renewal_date'";
                      
        // Check if we should add payment comments to special_comments
        if (!empty($comments) || !empty($payment_reference)) {
            // Get existing special comments if any
            $existing_comments = !empty($member['special_comments']) ? $member['special_comments'] : '';
            
            // Add new payment comment with timestamp
            $payment_note = date('Y-m-d H:i') . " - Payment: " . $payment_amount . " (" . $payment_method . ")";
            if (!empty($payment_reference)) {
                $payment_note .= ", Ref: " . $payment_reference;
            }
            if (!empty($comments)) {
                $payment_note .= "\nNote: " . $comments;
            }
            
            // Combine existing and new comments
            $updated_comments = $existing_comments;
            if (!empty($updated_comments)) {
                $updated_comments .= "\n\n";
            }
            $updated_comments .= $payment_note;
            
            // Escape for database
            $updated_comments = mysqli_real_escape_string($conn, $updated_comments);
            
            // Add to update query
            $update_sql .= ", special_comments = '$updated_comments'";
        }
        
        $update_sql .= " WHERE id = $member_id";
        
        // Execute update query
        if (mysqli_query($conn, $update_sql)) {
            // Check if payments table exists and get its columns
            $check_payments_table = mysqli_query($conn, "SHOW TABLES LIKE 'payments'");
            
            if (mysqli_num_rows($check_payments_table) > 0) {
                // Get columns in the payments table
                $columns_query = "SHOW COLUMNS FROM payments";
                $columns_result = mysqli_query($conn, $columns_query);
                $columns = [];
                
                if ($columns_result) {
                    while ($column = mysqli_fetch_assoc($columns_result)) {
                        $columns[] = $column['Field'];
                    }
                    
                    // Build the insert query based on existing columns
                    $payment_sql = "INSERT INTO payments (member_id, amount, payment_date, payment_method";
                    $payment_values = " VALUES ($member_id, '$payment_amount', '$new_payment_date', '$payment_method'";
                    
                    // Check for reference column
                    if (in_array('reference_number', $columns) && !empty($payment_reference)) {
                        $payment_sql .= ", reference_number";
                        $payment_values .= ", '$payment_reference'";
                    } elseif (in_array('payment_reference', $columns) && !empty($payment_reference)) {
                        $payment_sql .= ", payment_reference";
                        $payment_values .= ", '$payment_reference'";
                    }
                    
                    // Check for comments column
                    if (in_array('comments', $columns) && !empty($comments)) {
                        $payment_sql .= ", comments";
                        $payment_values .= ", '$comments'";
                    } elseif (in_array('note', $columns) && !empty($comments)) {
                        $payment_sql .= ", note";
                        $payment_values .= ", '$comments'";
                    }
                    
                    $payment_sql .= ")" . $payment_values . ")";
                    mysqli_query($conn, $payment_sql);
                }
            }
            
            // Success message
            $message = "Membership updated successfully!";
            $message_type = "success";
            
            // Refresh member data
            $result = mysqli_query($conn, $sql);
            $member = mysqli_fetch_assoc($result);
            
            // Update formatted dates
            $payment_date = !empty($member['payment_date']) ? date('Y-m-d', strtotime($member['payment_date'])) : '';
            $start_date = !empty($member['start_date']) ? date('Y-m-d', strtotime($member['start_date'])) : '';
            $renewal_date = !empty($member['renewal_date']) ? date('Y-m-d', strtotime($member['renewal_date'])) : '';
            $is_expired = !empty($renewal_date) && $renewal_date < $today;
        } else {
            $message = "Error updating membership: " . mysqli_error($conn);
            $message_type = "danger";
        }
    }
}

// Get all membership types for dropdown
$membership_types_sql = "SELECT * FROM membership_types ORDER BY name ASC";
$membership_types_result = mysqli_query($conn, $membership_types_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Membership - <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></title>
    <!-- Bootstrap CSS is included in header.php -->
    <style>
        .member-header {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .membership-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: bold;
        }
        .badge-active {
            background-color: #28a745;
            color: white;
        }
        .badge-expired {
            background-color: #dc3545;
            color: white;
        }
        .form-check-label {
            cursor: pointer;
        }
        .payment-method-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .payment-method-option {
            flex: 1;
            min-width: 120px;
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
    
    <h1 class="mb-4">Update Membership</h1>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Member Information -->
    <div class="member-header">
        <div class="row">
            <div class="col-md-8">
                <h3><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h3>
                <p class="mb-2">
                    <i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($member['email']); ?><br>
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
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body p-3">
                        <h5 class="card-title">Current Status</h5>
                        <ul class="list-unstyled mb-0">
                            <li><strong>Start Date:</strong> <?php echo !empty($start_date) ? date('M j, Y', strtotime($start_date)) : 'N/A'; ?></li>
                            <li><strong>Renewal Date:</strong> <?php echo !empty($renewal_date) ? date('M j, Y', strtotime($renewal_date)) : 'N/A'; ?></li>
                            <li><strong>Last Payment:</strong> <?php echo !empty($payment_date) ? date('M j, Y', strtotime($payment_date)) : 'N/A'; ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Update Membership Form -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Update Membership Details</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="membership_type_id" class="form-label">Membership Type *</label>
                        <select class="form-select" id="membership_type_id" name="membership_type_id" required>
                            <option value="">Select Membership Type</option>
                            <?php if ($membership_types_result && mysqli_num_rows($membership_types_result) > 0): ?>
                                <?php while ($type = mysqli_fetch_assoc($membership_types_result)): ?>
                                    <option value="<?php echo $type['id']; ?>" 
                                            data-price="<?php echo $type['price']; ?>"
                                            <?php echo $member['membership_type_id'] == $type['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['name']); ?> (<?php echo htmlspecialchars($type['price']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="payment_amount" class="form-label">Payment Amount *</label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="text" class="form-control" id="payment_amount" name="payment_amount" value="<?php echo htmlspecialchars($member['membership_price'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="payment_date" class="form-label">Payment Date *</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo $today; ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Start Date *</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $default_new_start_date; ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="renewal_date" class="form-label">Renewal Date *</label>
                        <input type="date" class="form-control" id="renewal_date" name="renewal_date" value="<?php echo $default_new_renewal_date; ?>" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="payment_method" class="form-label">Payment Method *</label>
                        <div class="payment-method-options">
                            <div class="form-check payment-method-option">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_cash" value="Cash" checked>
                                <label class="form-check-label" for="payment_cash">
                                    <i class="fas fa-money-bill-wave me-2"></i> Cash
                                </label>
                            </div>
                            <div class="form-check payment-method-option">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_mpesa" value="M-Pesa">
                                <label class="form-check-label" for="payment_mpesa">
                                    <i class="fas fa-mobile-alt me-2"></i> M-Pesa
                                </label>
                            </div>
                            <div class="form-check payment-method-option">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_card" value="Card">
                                <label class="form-check-label" for="payment_card">
                                    <i class="fas fa-credit-card me-2"></i> Card
                                </label>
                            </div>
                            <div class="form-check payment-method-option">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_bank" value="Bank Transfer">
                                <label class="form-check-label" for="payment_bank">
                                    <i class="fas fa-university me-2"></i> Bank
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="payment_reference" class="form-label">Payment Reference</label>
                        <input type="text" class="form-control" id="payment_reference" name="payment_reference" placeholder="Transaction ID, Receipt #, etc.">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="comments" class="form-label">Comments</label>
                    <textarea class="form-control" id="comments" name="comments" rows="3" placeholder="Any notes about this payment or membership update"></textarea>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="member.php?id=<?php echo $member_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Update Membership
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Auto-calculate section -->
    <div class="card mt-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-calculator me-2"></i> Membership Duration Calculator</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Quick Duration</label>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary duration-btn" data-days="1">1 Day</button>
                            <button type="button" class="btn btn-sm btn-outline-primary duration-btn" data-days="7">1 Week</button>
                            <button type="button" class="btn btn-sm btn-outline-primary duration-btn" data-days="30">1 Month</button>
                            <button type="button" class="btn btn-sm btn-outline-primary duration-btn" data-days="90">3 Months</button>
                            <button type="button" class="btn btn-sm btn-outline-primary duration-btn" data-days="180">6 Months</button>
                            <button type="button" class="btn btn-sm btn-outline-primary duration-btn" data-days="365">1 Year</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Custom Duration</label>
                        <div class="row g-2">
                            <div class="col-7">
                                <input type="number" class="form-control" id="custom_duration" min="1" value="30">
                            </div>
                            <div class="col-5">
                                <button type="button" class="btn btn-primary w-100" id="apply_custom_duration">Apply</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle membership type change to update payment amount
    const membershipTypeSelect = document.getElementById('membership_type_id');
    const paymentAmountInput = document.getElementById('payment_amount');
    
    membershipTypeSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption && selectedOption.dataset.price) {
            paymentAmountInput.value = selectedOption.dataset.price;
        }
    });
    
    // Handle duration buttons
    const durationButtons = document.querySelectorAll('.duration-btn');
    const startDateInput = document.getElementById('start_date');
    const renewalDateInput = document.getElementById('renewal_date');
    
    durationButtons.forEach(button => {
        button.addEventListener('click', function() {
            const days = parseInt(this.dataset.days);
            calculateRenewalDate(days);
        });
    });
    
    // Handle custom duration
    const customDurationInput = document.getElementById('custom_duration');
    const applyCustomDurationBtn = document.getElementById('apply_custom_duration');
    
    applyCustomDurationBtn.addEventListener('click', function() {
        const days = parseInt(customDurationInput.value);
        if (days > 0) {
            calculateRenewalDate(days);
        }
    });
    
    // Calculate renewal date based on start date and duration
    function calculateRenewalDate(days) {
        const startDate = new Date(startDateInput.value);
        if (isNaN(startDate.getTime())) {
            alert('Please select a valid start date first');
            return;
        }
        
        // Calculate new renewal date (start date + days)
        const renewalDate = new Date(startDate);
        renewalDate.setDate(renewalDate.getDate() + days - 1); // -1 because the start date counts as day 1
        
        // Format date as YYYY-MM-DD for input
        const year = renewalDate.getFullYear();
        const month = String(renewalDate.getMonth() + 1).padStart(2, '0');
        const day = String(renewalDate.getDate()).padStart(2, '0');
        renewalDateInput.value = `${year}-${month}-${day}`;
    }
});
</script>

<?php
// Include footer
include_once 'includes/footer.php';
?>