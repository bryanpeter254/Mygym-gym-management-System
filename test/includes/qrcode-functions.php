<?php
/**
 * QR Code Functions for Gym Management System
 */

// Include the QR Code library
require_once __DIR__ . '/phpqrcode/phpqrcode.php';

/**
 * Generates a QR code for a member
 * 
 * @param int $member_id Member ID
 * @param string $first_name Member's first name
 * @param string $last_name Member's last name
 * @param string $membership_type Membership type name
 * @param string $expiry_date Membership expiry date (YYYY-MM-DD)
 * @param bool $return_as_base64 Whether to return as base64 encoded string
 * @return string Path to QR code image file or base64 encoded image
 */
function generate_member_qrcode($member_id, $first_name = '', $last_name = '', $membership_type = '', $expiry_date = '', $return_as_base64 = false) {
    // Improved error logging
    error_log("Starting QR code generation for member ID: " . $member_id);
    
    // Create the qrcodes directory if it doesn't exist
    $qrcode_dir = dirname(__DIR__) . '/uploads/qrcodes';
    if (!is_dir($qrcode_dir)) {
        if (!mkdir($qrcode_dir, 0777, true)) {
            error_log("Failed to create QR code directory: " . $qrcode_dir);
            // Try using an alternative location
            $qrcode_dir = dirname(__DIR__);
            error_log("Trying alternative directory: " . $qrcode_dir);
        } else {
            error_log("Successfully created QR code directory: " . $qrcode_dir);
        }
    }
    
    // Ensure directory is writable
    if (!is_writable($qrcode_dir)) {
        chmod($qrcode_dir, 0777);
        error_log("Changed permissions on directory: " . $qrcode_dir);
    }
    
    // Generate a unique filename for this member
    $filename = $qrcode_dir . '/member_' . $member_id . '.png';
    $web_path = 'uploads/qrcodes/member_' . $member_id . '.png';
    
    // Create the member data for the QR code
    $member_data = [
        'id' => $member_id,
        'name' => trim($first_name . ' ' . $last_name),
        'membership' => $membership_type,
        'expiry' => $expiry_date,
        'timestamp' => time()
    ];
    
    // Convert to JSON
    $qr_content = json_encode($member_data);
    error_log("QR content generated: " . $qr_content);
    
    // Try to generate the QR code
    $success = false;
    try {
        // Generate the QR code with high error correction
        $success = QRcode::png($qr_content, $filename, QR_ECLEVEL_H, 10, 2);
        
        if ($success) {
            error_log("QR code successfully generated at: " . $filename);
        } else {
            error_log("QR code generation failed with primary method");
            
            // Try alternative QR code generation using the Google Charts API
            $fallback_url = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($qr_content) . '&choe=UTF-8';
            $fallback_content = @file_get_contents($fallback_url);
            
            if ($fallback_content !== false) {
                if (file_put_contents($filename, $fallback_content) !== false) {
                    $success = true;
                    error_log("QR code generated using fallback Google Charts API");
                }
            }
        }
    } catch (Exception $e) {
        error_log("Exception during QR code generation: " . $e->getMessage());
    }
    
    // If all attempts to generate QR code failed, create a simple text file with the data
    if (!$success || !file_exists($filename)) {
        $text_filename = $qrcode_dir . '/member_' . $member_id . '.txt';
        file_put_contents($text_filename, $qr_content);
        error_log("Fallback: Created text file with QR content: " . $text_filename);
    }
    
    // Return as base64 if requested
    if ($return_as_base64) {
        if (file_exists($filename)) {
            $image_data = file_get_contents($filename);
            if ($image_data !== false) {
                return 'data:image/png;base64,' . base64_encode($image_data);
            }
        }
        
        // If we get here, something went wrong with base64 encoding
        error_log("Failed to read file for base64 encoding: " . $filename);
        return '';
    }
    
    // Return the relative path to the QR code
    return $web_path;
}
?>