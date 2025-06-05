<?php
//this is members.php 
// Include header
include_once 'includes/header.php';

// Check for messages
if (isset($_SESSION['message']) && isset($_SESSION['message_type'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    // Clear the messages
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$membership_filter = isset($_GET['membership']) ? (int)$_GET['membership'] : 0;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query based on filters
$query = "SELECT m.*, mt.name as membership_name 
          FROM members m
          LEFT JOIN membership_types mt ON m.membership_type_id = mt.id
          WHERE 1=1";

// Apply status filter
if ($status_filter !== 'all') {
    $query .= " AND m.status = '$status_filter'";
}

// Apply membership filter
if ($membership_filter > 0) {
    $query .= " AND m.membership_type_id = $membership_filter";
}

// Apply search filter
if (!empty($search)) {
    $query .= " AND (m.first_name LIKE '%$search%' OR m.last_name LIKE '%$search%' OR 
                      m.email LIKE '%$search%' OR m.phone LIKE '%$search%')";
}

// Order by
$query .= " ORDER BY m.last_name, m.first_name";

// Execute query
$result = mysqli_query($conn, $query);

// Get membership types for filter dropdown
$membership_query = "SELECT * FROM membership_types ORDER BY name";
$membership_result = mysqli_query($conn, $membership_query);
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Members</h1>
            <a href="register.php" class="btn btn-primary">
                <i class="fas fa-user-plus me-2"></i> Add New Member
            </a>
        </div>
    </div>
</div>

<?php if (isset($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <!-- Search field -->
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Name, email, phone" value="<?php echo $search; ?>">
                        </div>
                    </div>
                    
                    <!-- Status filter -->
                    <div class="col-md-4">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $status_filter === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="Expired" <?php echo $status_filter === 'Expired' ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>
                    
                    <!-- Membership type filter -->
                    <div class="col-md-4">
                        <label for="membership" class="form-label">Membership Type</label>
                        <select class="form-select" id="membership" name="membership">
                            <option value="0" <?php echo $membership_filter === 0 ? 'selected' : ''; ?>>All Types</option>
                            <?php while ($membership = mysqli_fetch_assoc($membership_result)): ?>
                            <option value="<?php echo $membership['id']; ?>" 
                                    <?php echo $membership_filter === (int)$membership['id'] ? 'selected' : ''; ?>>
                                <?php echo $membership['name']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <!-- Apply filters button -->
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i> Apply Filters
                        </button>
                        <a href="members.php" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-2"></i> Reset
                        </a>
                        
                        <!-- Export buttons -->
                        <div class="float-end">
                            <a href="export.php?format=csv&<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                                <i class="fas fa-file-csv me-2"></i> Export CSV
                            </a>
                            <a href="export.php?format=pdf&<?php echo http_build_query($_GET); ?>" class="btn btn-danger">
                                <i class="fas fa-file-pdf me-2"></i> Export PDF
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Members Table -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="membersTable">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Membership</th>
                                <th>Status</th>
                                <th>Renewal Date</th>
                                <th>QR Code</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($member = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?php echo $member['id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($member['photo'])): ?>
                                        <img src="<?php echo $member['photo']; ?>" alt="Profile" class="rounded-circle me-2" width="40" height="40">
                                        <?php else: ?>
                                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                            <i class="fas fa-user text-white"></i>
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold"><?php echo $member['first_name'] . ' ' . $member['last_name']; ?></div>
                                            <small class="text-muted"><?php echo !empty($member['dob']) ? 'DOB: ' . displayDate($member['dob']) : ''; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo $member['email']; ?></div>
                                    <small class="text-muted"><?php echo $member['phone']; ?></small>
                                </td>
                                <td><?php echo $member['membership_name']; ?></td>
                                <td>
                                    <?php if ($member['status'] === 'Active'): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php elseif ($member['status'] === 'Inactive'): ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Expired</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($member['renewal_date'])): ?>
                                    <?php 
                                        $renewal_date = new DateTime($member['renewal_date']);
                                        $today = new DateTime();
                                        $days_until_renewal = $today->diff($renewal_date)->days;
                                        $is_past_due = $renewal_date < $today;
                                    ?>
                                    
                                    <?php if ($is_past_due): ?>
                                    <span class="text-danger fw-bold">
                                        <?php echo displayDate($member['renewal_date']); ?>
                                        <span class="badge bg-danger">Overdue</span>
                                    </span>
                                    <?php elseif ($days_until_renewal <= 7): ?>
                                    <span class="text-warning fw-bold">
                                        <?php echo displayDate($member['renewal_date']); ?>
                                        <span class="badge bg-warning text-dark">Soon</span>
                                    </span>
                                    <?php else: ?>
                                    <?php echo displayDate($member['renewal_date']); ?>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($member['qrcode_path'])): ?>
                                    <span class="badge bg-primary"><i class="fas fa-qrcode me-1"></i> QR Generated</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Not Generated</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="member.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit-member.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Edit Member">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" class="btn btn-sm btn-success check-in-btn" data-id="<?php echo $member['id']; ?>" data-bs-toggle="tooltip" title="Check-in">
                                            <i class="fas fa-sign-in-alt"></i>
                                        </a>
                                        <a href="#" class="btn btn-sm btn-danger delete-btn" data-id="<?php echo $member['id']; ?>" data-name="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>" data-bs-toggle="tooltip" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No members found with the current filters.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Check-in Modal -->
<div class="modal fade" id="quickCheckInModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-sign-in-alt me-2"></i> Quick Check-in</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="check-in.php" id="quickCheckInForm">
                    <input type="hidden" id="modalMemberId" name="member_id">
                    <input type="hidden" name="verification_method" value="Manual">
                    
                    <p class="mb-4">Confirm check-in for <span id="memberNameDisplay" class="fw-bold"></span>?</p>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check-circle me-2"></i> Confirm Check-in
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Delete Member</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <span id="deleteMemberName" class="fw-bold"></span>?</p>
                <p class="text-danger">This action cannot be undone. All data associated with this member will be permanently deleted.</p>
                
                <div class="d-grid gap-2">
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i> Delete Permanently
                    </a>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Quick check-in modal functionality
    const quickCheckInModal = new bootstrap.Modal(document.getElementById('quickCheckInModal'));
    
    document.querySelectorAll('.check-in-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const memberId = this.dataset.id;
            const memberName = this.closest('tr').querySelector('td:nth-child(2)').textContent.trim();
            
            document.getElementById('modalMemberId').value = memberId;
            document.getElementById('memberNameDisplay').textContent = memberName;
            
            quickCheckInModal.show();
        });
    });
    
    // Delete modal functionality
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const memberId = this.dataset.id;
            const memberName = this.dataset.name;
            
            document.getElementById('deleteMemberName').textContent = memberName;
            
            // Update the confirm delete button href
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            confirmDeleteBtn.href = 'delete-member.php?id=' + memberId + '&confirm=yes';
            
            deleteModal.show();
        });
    });
});
</script>

<?php
// Include footer
include_once 'includes/footer.php';
?>