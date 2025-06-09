<?php
// Start session
session_start();

// Database connection details
$host = 'localhost';     // Usually 'localhost' for XAMPP
$username = 'root';      // Default XAMPP username
$password = '';          // Default XAMPP password is empty
$database = 'gym_manager';  // Database name

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set timezone
date_default_timezone_set('Africa/Nairobi');

// Helper functions
function sanitize($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

function displayDate($date) {
    if (empty($date)) return '';
    return date('d-m-Y', strtotime($date));
}

function generateAlert() {
    if (isset($_SESSION['message']) && isset($_SESSION['message_type'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'];
        
        echo "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
        
        // Clear the message after displaying
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
}

// Define membership types and their durations
$membership_types = [
    ['name' => 'Daily Access', 'price' => 500, 'duration' => 1],
    ['name' => 'Weekly Access', 'price' => 2000, 'duration' => 7],
    ['name' => 'Monthly off-peak', 'price' => 4000, 'duration' => 30],
    ['name' => 'Standard Monthly', 'price' => 5000, 'duration' => 30],
    ['name' => 'Duo Monthly', 'price' => 9000, 'duration' => 30],
    ['name' => '3 Months', 'price' => 14000, 'duration' => 90],
    ['name' => '6 Months', 'price' => 27000, 'duration' => 180],
    ['name' => '12 Months', 'price' => 52000, 'duration' => 365]
];

// Constants
define('SITE_NAME', 'Gym Management System');
define('QR_CODE_ENABLED', true);
// Old fingerprint settings removed
// define('FINGERPRINT_BRIDGE_HOST', 'localhost');
// define('FINGERPRINT_BRIDGE_PORT', 8099);