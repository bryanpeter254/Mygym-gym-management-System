<?php
// petty-cash-categories.php - Manage petty cash categories
include_once 'includes/header.php';

// Initialize variables
$message = '';
$message_type = '';
$edit_id = 0;
$edit_data = null;

// Process form submission for adding/updating category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    
    // Basic validation
    if (empty($name) || empty($type)) {
        $message = "Name and type are required fields.";
        $message_type = "danger";
    } else {
        if ($edit_id > 0) {
            // Update existing category
            $sql = "UPDATE petty_cash_categories SET 
                    name = '$name', 
                    type = '$type', 
                    is_active = $is_active 
                    WHERE id = $edit_id";
            
            if (mysqli_query($conn, $sql)) {
                $message = "Category updated successfully!";
                $message_type = "success";
                $edit_id = 0; // Reset edit mode
            } else {
                $message = "Error updating category: " . mysqli_error($conn);
                $message_type = "danger";
            }
        } else {
            // Check if category with same name and type already exists
            $check_sql = "SELECT id FROM petty_cash_categories WHERE name = '$name' AND type = '$type'";
            $check_result = mysqli_query($conn, $check_sql);
            
            if (mysqli_num_rows($check_result) > 0) {
                $message = "A category with this name already exists for the selected type.";
                $message_type = "warning";
            } else {
                // Insert new category
                $sql = "INSERT INTO petty_cash_categories (name, type, is_active) VALUES ('$name', '$type', $is_active)";
                
                if (mysqli_query($conn, $sql)) {
                    $message = "New category added successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error adding category: " . mysqli_error($conn);
                    $message_type = "danger";
                }
            }
        }
    }
}

// Process deletion
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $delete_id = (int)$_GET['delete'];
    
    // Check if category is being used
    $check_sql = "SELECT COUNT(*) as count FROM petty_cash_transactions WHERE category = $delete_id";
    $check_result = mysqli_query($conn, $check_sql);
    $check_data = mysqli_fetch_assoc($check_result);
    
    if ($check_data['count'] > 0) {
        $message = "Cannot delete this category because it is being used by {$check_data['count']} transaction(s).";
        $message_type = "warning";
    } else {
        // Safe to delete
        $delete_sql = "DELETE FROM petty_cash_categories WHERE id = $delete_id";
        
        if (mysqli_query($conn, $delete_sql)) {
            $message = "Category deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting category: " . mysqli_error($conn);
            $message_type = "danger";
        }
    }
}

// Process edit request
if (isset($_GET['edit']) && $_GET['edit'] > 0) {
    $edit_id = (int)$_GET['edit'];
    $edit_sql = "SELECT * FROM petty_cash_categories WHERE id = $edit_id";
    $edit_result = mysqli_query($conn, $edit_sql);
    
    if ($edit_result && mysqli_num_rows($edit_result) > 0) {
        $edit_data = mysqli_fetch_assoc($edit_result);
    } else {
        $message = "Category not found!";
        $message_type = "danger";
        $edit_id = 0;
    }
}

// Get categories
$income_sql = "SELECT * FROM petty_cash_categories WHERE type = 'income' ORDER BY name";
$expense_sql = "SELECT * FROM petty_cash_categories WHERE type = 'expense' ORDER BY name";

$income_result = mysqli_query($conn, $income_sql);
$expense_result = mysqli_query($conn, $expense_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Petty Cash Categories</title>
    <style>
        .category-card {
            transition: all 0.3s ease;
            height: 100%;
        }
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .inactive-category {
            opacity: 0.6;
        }
        .income-header {
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        .expense-header {
            background-color: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Manage Categories</h1>
        <a href="petty-cash.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Petty Cash
        </a>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <?php if ($edit_id && $edit_data): ?>
            <!-- Edit Form -->
            <div class="col-md-12 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Edit Category</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="save_category" value="1">
                            <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Category Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($edit_data['name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="type" class="form-label">Type *</label>
                                    <select class="form-select" id="type" name="type" required>
                                        <option value="income" <?php echo $edit_data['type'] === 'income' ? 'selected' : ''; ?>>Income</option>
                                        <option value="expense" <?php echo $edit_data['type'] === 'expense' ? 'selected' : ''; ?>>Expense</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?php echo $edit_data['is_active'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                                <div class="form-text">Inactive categories won't be shown in transaction forms.</div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="petty-cash-categories.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Update Category
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Add New Category Form -->
            <div class="col-md-12 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Add New Category</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="save_category" value="1">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Category Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="type" class="form-label">Type *</label>
                                    <select class="form-select" id="type" name="type" required>
                                        <option value="income">Income</option>
                                        <option value="expense">Expense</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                                <div class="form-text">Inactive categories won't be shown in transaction forms.</div>
                            </div>
                            
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i> Add Category
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Income Categories -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header income-header">
                    <h5 class="mb-0"><i class="fas fa-arrow-down me-2"></i> Income Categories</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if ($income_result && mysqli_num_rows($income_result) > 0): ?>
                            <?php while ($category = mysqli_fetch_assoc($income_result)): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card category-card <?php echo $category['is_active'] ? '' : 'inactive-category'; ?>">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                                            <?php if (!$category['is_active']): ?>
                                                <span class="badge bg-secondary mb-2">Inactive</span>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex justify-content-between mt-3">
                                                <a href="petty-cash-categories.php?edit=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                
                                                <a href="#" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteCategoryModal<?php echo $category['id']; ?>">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Delete Confirmation Modal -->
                                    <div class="modal fade" id="deleteCategoryModal<?php echo $category['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title">Confirm Deletion</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to delete the <strong><?php echo htmlspecialchars($category['name']); ?></strong> category?</p>
                                                    <p class="mb-0"><strong>Note:</strong> This action cannot be undone. If transactions are using this category, you won't be able to delete it.</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <a href="petty-cash-categories.php?delete=<?php echo $category['id']; ?>" class="btn btn-danger">Delete</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> No income categories found.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Expense Categories -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header expense-header">
                    <h5 class="mb-0"><i class="fas fa-arrow-up me-2"></i> Expense Categories</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if ($expense_result && mysqli_num_rows($expense_result) > 0): ?>
                            <?php while ($category = mysqli_fetch_assoc($expense_result)): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card category-card <?php echo $category['is_active'] ? '' : 'inactive-category'; ?>">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                                            <?php if (!$category['is_active']): ?>
                                                <span class="badge bg-secondary mb-2">Inactive</span>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex justify-content-between mt-3">
                                                <a href="petty-cash-categories.php?edit=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                
                                                <a href="#" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteCategoryModal<?php echo $category['id']; ?>">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Delete Confirmation Modal -->
                                    <div class="modal fade" id="deleteCategoryModal<?php echo $category['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title">Confirm Deletion</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to delete the <strong><?php echo htmlspecialchars($category['name']); ?></strong> category?</p>
                                                    <p class="mb-0"><strong>Note:</strong> This action cannot be undone. If transactions are using this category, you won't be able to delete it.</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <a href="petty-cash-categories.php?delete=<?php echo $category['id']; ?>" class="btn btn-danger">Delete</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> No expense categories found.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include_once 'includes/footer.php';
?>