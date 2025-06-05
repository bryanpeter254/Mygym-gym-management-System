<?php
/**
 * Migration script to update the check_ins table to include QRCode as a verification method
 */

// Include configuration
require_once '../config.php';

echo "<h2>Database Migration: Add QRCode Verification Method</h2>";

// Check if the check_ins table exists
$check_table_sql = "SHOW TABLES LIKE 'check_ins'";
$table_result = mysqli_query($conn, $check_table_sql);

if (mysqli_num_rows($table_result) > 0) {
    // Table exists, check if we need to modify the verification_method column
    $check_column_sql = "SHOW COLUMNS FROM `check_ins` LIKE 'verification_method'";
    $column_result = mysqli_query($conn, $check_column_sql);
    
    if ($column_result && mysqli_num_rows($column_result) > 0) {
        $column_info = mysqli_fetch_assoc($column_result);
        
        // Check if 'QRCode' is already in the enum values
        if (strpos($column_info['Type'], 'QRCode') === false) {
            // Add QRCode to the enum
            $update_sql = "ALTER TABLE `check_ins` MODIFY COLUMN `verification_method` ENUM('Fingerprint', 'Manual', 'QRCode') NOT NULL";
            
            if (mysqli_query($conn, $update_sql)) {
                echo "<p class='text-success'>Successfully updated check_ins table to include QRCode verification method.</p>";
            } else {
                echo "<p class='text-danger'>Error updating check_ins table: " . mysqli_error($conn) . "</p>";
            }
        } else {
            echo "<p>QRCode verification method already exists in the check_ins table.</p>";
        }
    } else {
        echo "<p class='text-danger'>Could not find verification_method column in check_ins table.</p>";
    }
} else {
    echo "<p class='text-warning'>check_ins table does not exist. Please run setup.php first.</p>";
}

echo "<p><a href='../index.php' class='btn btn-primary'>Return to Dashboard</a></p>";
?>