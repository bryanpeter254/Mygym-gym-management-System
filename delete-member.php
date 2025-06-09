<?php
// Include header
include_once 'includes/header.php';

// Initialize variables
$member = null;

// Check if ID is provided and confirmation is made
if (isset($_GET['id']) && !empty($_GET['id']) && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    $member_id = (int)$_GET['id'];
    
    // Fetch member data to confirm it exists
    $sql = "SELECT * FROM members WHERE id = $member_id";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $member = mysqli_fetch_assoc($result);
        
        // Begin deletion process
        $deletion_success = true;
        $error_message = '';
        
        // Check for related tables and delete records
        $tables_to_check = ['check_ins', 'payments'];
        foreach ($tables_to_check as $table) {
            $check_table = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
            if (mysqli_num_rows($check_table) > 0) {
                // Delete related records
                $delete_sql = "DELETE FROM $table WHERE member_id = $member_id";
                mysqli_query($conn, $delete_sql);
            }
        }
        
        // Delete the member's QR code file if it exists
        if (!empty($member['qrcode_path']) && file_exists($member['qrcode_path'])) {
            @unlink($member['qrcode_path']); // Use @ to suppress errors
        }
        
        // Delete the member's photo if it exists
        if (!empty($member['photo']) && file_exists($member['photo'])) {
            @unlink($member['photo']); // Use @ to suppress errors
        }
        
        // Finally, delete the member
        $delete_member = "DELETE FROM members WHERE id = $member_id";
        $delete_result = mysqli_query($conn, $delete_member);
        
        if ($delete_result) {
            // Deletion successful
            $_SESSION['message'] = "Member '{$member['first_name']} {$member['last_name']}' has been permanently deleted.";
            $_SESSION['message_type'] = "success";
        } else {
            // Deletion failed
            $_SESSION['message'] = "Error deleting member: " . mysqli_error($conn);
            $_SESSION['message_type'] = "danger";
        }
    } else {
        // Member not found
        $_SESSION['message'] = "Member not found!";
        $_SESSION['message_type'] = "danger";
    }
    
    // Redirect back to the members list
    header('Location: members.php');
    exit;
} else {
    // Missing ID or confirmation, redirect
    $_SESSION['message'] = "Invalid delete request.";
    $_SESSION['message_type'] = "danger";
    header('Location: members.php');
    exit;
}
?>