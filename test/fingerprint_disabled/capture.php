<?php
/**
 * Fingerprint Capture Endpoint
 * 
 * This script handles fingerprint capture requests from the web interface
 * and communicates with the Java bridge service through the FingerprintBridge class.
 */

// Include the bridge class
require_once 'bridge.php';

// Set content type to JSON
header('Content-Type: application/json');

// Initialize the bridge
$bridge = new FingerprintBridge('localhost', 8099);

// Set capture options with higher thresholds to prevent false positives
$options = [
    'qualityThreshold' => 75,     // Higher quality requirement (0-100)
    'timeout' => 15000,           // 15 second timeout
    'forcedCapture' => true       // Require actual finger detection
];

try {
    // First check if we can connect to the bridge
    $connected = $bridge->connect();
    
    if (!$connected) {
        // Bridge service is unavailable, return graceful error
        echo json_encode([
            'success' => false,
            'bridgeAvailable' => false,
            'message' => 'Fingerprint scanner service is not available. You can still register without a fingerprint.'
        ]);
        exit;
    }
    
    // Get member ID from request if available
    $memberId = isset($_POST['memberId']) ? $_POST['memberId'] : null;
    
    // Capture the fingerprint
    $result = $bridge->captureFingerprint($memberId, $options);
    
    // Additional validation to prevent false positives
    if ($result['success'] && (empty($result['template']) || $result['quality'] < 50)) {
        $result['success'] = false;
        $result['message'] = 'No valid fingerprint detected or quality too low. Please try again.';
    }
    
    // Add bridge availability flag
    $result['bridgeAvailable'] = true;
    
    // Output the result
    echo json_encode($result);
    
} catch (Exception $e) {
    // Handle any exceptions
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}