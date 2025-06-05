<?php
// Include header
include_once 'includes/header.php';

// Process check-in form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id'])) {
    $member_id = (int)$_POST['member_id'];
    $verification_method = sanitize($_POST['verification_method']);
    
    // Check if member exists
    $check_member = "SELECT id, first_name, last_name FROM members WHERE id = $member_id";
    $member_result = mysqli_query($conn, $check_member);
    
    if (mysqli_num_rows($member_result) > 0) {
        $member = mysqli_fetch_assoc($member_result);
        
        // Insert check-in record
        $sql = "INSERT INTO check_ins (member_id, verification_method) VALUES ($member_id, '$verification_method')";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['message'] = "Check-in successful for {$member['first_name']} {$member['last_name']}!";
            $_SESSION['message_type'] = "success";
            // Redirect to prevent form resubmission
            header("Location: check-in.php");
            exit;
        } else {
            $_SESSION['message'] = "Error recording check-in: " . mysqli_error($conn);
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Member not found!";
        $_SESSION['message_type'] = "danger";
    }
}

// Fetch recent check-ins for display
$recent_checkins_query = "SELECT c.*, m.first_name, m.last_name 
                         FROM check_ins c
                         JOIN members m ON c.member_id = m.id
                         ORDER BY c.check_in_time DESC
                         LIMIT 10";
$recent_checkins_result = mysqli_query($conn, $recent_checkins_query);
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Member Check-in</h1>
    </div>
</div>

<div class="row">
    <!-- QR Code Check-in -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-qrcode me-2"></i> QR Code Check-in</h4>
            </div>
            <div class="card-body">
                <div class="text-center">
                    <img src="https://cdn-icons-png.flaticon.com/512/3388/3388930.png" class="img-fluid mb-3" style="max-width: 180px;">
                    
                    <div id="scanStatus" class="alert alert-secondary">
                        <i class="fas fa-qrcode me-2"></i> Scan a member's QR code to check-in
                    </div>
                    
                    <a href="qr-check-in.php" id="qrCheckInBtn" class="btn btn-primary btn-lg">
                        <i class="fas fa-camera me-2"></i> Scan QR Code
                    </a>
                    
                    <div class="mt-3 small text-muted">
                        <p>QR code scanning will automatically find and check-in the member.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Manual Check-in -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-secondary text-white">
                <h4 class="mb-0"><i class="fas fa-keyboard me-2"></i> Manual Check-in</h4>
            </div>
            <div class="card-body">
                <!-- Member Search -->
                <div class="mb-4">
                    <label for="memberSearch" class="form-label">Search Member</label>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="memberSearch" placeholder="Enter name, email or phone number">
                    </div>
                    
                    <div id="searchResults" class="list-group mt-2">
                        <!-- Search results will be populated here via JavaScript -->
                    </div>
                </div>
                
                <!-- Manual Check-in Form -->
                <form method="POST" action="" id="checkInForm">
                    <div class="mb-3">
                        <label for="memberId" class="form-label">Member ID</label>
                        <input type="number" class="form-control" id="memberId" name="member_id" required>
                    </div>
                    
                    <input type="hidden" name="verification_method" value="Manual">
                    
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-sign-in-alt me-2"></i> Check-in Member
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Recent Check-ins -->
    <div class="col-md-12 mt-2">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h4 class="mb-0"><i class="fas fa-history me-2"></i> Recent Check-ins</h4>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($recent_checkins_result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Check-in Time</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Actions</th>
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
                                <td>
                                    <a href="member.php?id=<?php echo $checkin['member_id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-user me-1"></i> View
                                    </a>
                                    
                                    <?php if (!$checkin['check_out_time']): ?>
                                    <a href="check-out.php?id=<?php echo $checkin['id']; ?>" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-sign-out-alt me-1"></i> Check-out
                                    </a>
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
            </div>
        </div>
    </div>
</div>

<script>
// Member search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('memberSearch');
    const searchResults = document.getElementById('searchResults');
    const memberIdInput = document.getElementById('memberId');
    
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.trim();
            
            if (searchTerm.length < 2) {
                searchResults.innerHTML = '';
                return;
            }
            
            // AJAX request to search for members
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `ajax/search_members.php?term=${encodeURIComponent(searchTerm)}`, true);
            
            xhr.onload = function() {
                if (this.status === 200) {
                    try {
                        const response = JSON.parse(this.responseText);
                        
                        // Clear previous results
                        searchResults.innerHTML = '';
                        
                        if (response.length === 0) {
                            searchResults.innerHTML = '<div class="alert alert-info">No members found</div>';
                            return;
                        }
                        
                        // Create a result item for each member
                        response.forEach(member => {
                            const item = document.createElement('a');
                            item.href = '#';
                            item.className = 'list-group-item list-group-item-action';
                            item.innerHTML = `
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>${member.first_name} ${member.last_name}</strong>
                                        <small class="d-block text-muted">${member.email || ''}</small>
                                    </div>
                                    <span class="badge bg-${member.status === 'Active' ? 'success' : 'warning'}">${member.status}</span>
                                </div>
                            `;
                            
                            // Add click event to select this member
                            item.addEventListener('click', function(e) {
                                e.preventDefault();
                                memberIdInput.value = member.id;
                                searchResults.innerHTML = '';
                                searchInput.value = `${member.first_name} ${member.last_name}`;
                            });
                            
                            searchResults.appendChild(item);
                        });
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        searchResults.innerHTML = '<div class="alert alert-danger">Error processing request</div>';
                    }
                }
            };
            
            xhr.onerror = function() {
                searchResults.innerHTML = '<div class="alert alert-danger">Request failed</div>';
            };
            
            xhr.send();
        });
    }
});
</script>

<?php
// Include footer
include_once 'includes/footer.php';
?>