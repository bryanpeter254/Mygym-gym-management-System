<?php
/**
 * Fingerprint Verification Endpoint
 * 
 * This script handles fingerprint verification requests for check-ins
 * and communicates with the Java bridge service through the FingerprintBridge class.
 */

// Include the bridge class and database connection
require_once 'bridge.php';
require_once '../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Initialize the bridge
$bridge = new FingerprintBridge('localhost', 8099);

try {
    // Capture the fingerprint for verification
    $options = [
        'qualityThreshold' => 65,     // Quality requirement for verification
        'timeout' => 15000,           // 15 second timeout
        'forcedCapture' => true       // Require actual finger detection
    ];
    
    $capture = $bridge->captureFingerprint(null, $options);
    
    // Check if capture was successful
    if (!$capture['success'] || empty($capture['template'])) {
        echo json_encode([
            'success' => false,
            'message' => $capture['message'] ?? 'Failed to capture fingerprint'
        ]);
        exit;
    }
    
    // We now have a fingerprint template to match against database
    $template = $capture['template'];
    
    // Look for a matching fingerprint in the database
    // This will need to use the fingerprint matching functionality of the scanner
    
    // Get all members with fingerprints
    $query = "SELECT id, first_name, last_name, fingerprint_template FROM members WHERE fingerprint_template IS NOT NULL";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . mysqli_error($conn)
        ]);
        exit;
    }
    
    $matched_member = null;
    $highest_score = 0;
    
    // Try matching against each stored template
    while ($member = mysqli_fetch_assoc($result)) {
        // Call the bridge to verify the fingerprint
        $verification = $bridge->verifyFingerprint($template, $member['fingerprint_template']);
        
        if ($verification['success'] && $verification['score'] > $highest_score) {
            $highest_score = $verification['score'];
            $matched_member = $member;
        }
    }
    
    if ($matched_member) {
        // Record the check-in
        $member_id = $matched_member['id'];
        $check_in_query = "INSERT INTO check_ins (member_id) VALUES ($member_id)";
        mysqli_query($conn, $check_in_query);
        
        echo json_encode([
            'success' => true,
            'message' => 'Fingerprint matched successfully',
            'member' => [
                'id' => $matched_member['id'],
                'name' => $matched_member['first_name'] . ' ' . $matched_member['last_name']
            ],
            'score' => $highest_score
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No matching fingerprint found in the database'
        ]);
    }
    
} catch (Exception $e) {
    // Handle any exceptions
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}