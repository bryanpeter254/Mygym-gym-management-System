<?php
// Include database configuration
require_once '../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if search term is provided
if (!isset($_GET['term']) || empty($_GET['term'])) {
    echo json_encode([]);
    exit;
}

// Sanitize search term
$term = sanitize($_GET['term']);

// Search for members matching the term
$query = "SELECT id, first_name, last_name, email, phone, status 
          FROM members 
          WHERE first_name LIKE '%$term%' OR 
                last_name LIKE '%$term%' OR 
                email LIKE '%$term%' OR 
                phone LIKE '%$term%'
          ORDER BY last_name, first_name
          LIMIT 10";

$result = mysqli_query($conn, $query);

// Check for error
if (!$result) {
    echo json_encode(['error' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

// Build results array
$members = [];
while ($row = mysqli_fetch_assoc($result)) {
    $members[] = $row;
}

// Return results as JSON
echo json_encode($members);