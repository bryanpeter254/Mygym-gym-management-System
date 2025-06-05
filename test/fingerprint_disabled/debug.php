<?php
/**
 * Fingerprint Bridge Debug Endpoint
 * 
 * This script helps diagnose connection issues with the Java bridge service.
 */

// Include the bridge class
require_once 'bridge.php';

// Set content type to JSON
header('Content-Type: application/json');

// Create a log function
function log_debug($message) {
    $log_file = __DIR__ . '/bridge_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    // Test connecting to the bridge
    $bridge = new FingerprintBridge('localhost', 8099);
    
    // Log attempt
    log_debug("Attempting to connect to bridge at localhost:8099");
    
    // Try to connect and get status
    $connected = $bridge->connect();
    
    if ($connected) {
        log_debug("Successfully connected to bridge");
        
        // Try to initialize the scanner
        $status = $bridge->initializeScanner();
        log_debug("Scanner initialization result: " . json_encode($status));
        
        echo json_encode([
            'success' => true,
            'connected' => true,
            'status' => $status,
            'message' => 'Successfully connected to fingerprint bridge'
        ]);
    } else {
        log_debug("Failed to connect to bridge");
        
        echo json_encode([
            'success' => false,
            'connected' => false,
            'message' => 'Failed to connect to fingerprint bridge'
        ]);
    }
} catch (Exception $e) {
    // Log and return any exceptions
    log_debug("Exception: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}