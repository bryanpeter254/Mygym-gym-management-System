<?php
/**
 * Database Migration Script - Add qrcode_path column to members table
 * Run this script once to add the required column to your database
 */

// Include database configuration
require_once 'config.php';

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// SQL to add qrcode_path column to members table if it doesn't exist
$check_column_sql = "SHOW COLUMNS FROM members LIKE 'qrcode_path'";
$column_exists = mysqli_query($conn, $check_column_sql);

if (mysqli_num_rows($column_exists) == 0) {
    // Column doesn't exist, add it
    $add_column_sql = "ALTER TABLE members ADD COLUMN qrcode_path TEXT";
    
    if (mysqli_query($conn, $add_column_sql)) {
        echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>Success!</h3>";
        echo "<p>The 'qrcode_path' column has been added to the members table.</p>";
        echo "</div>";
    } else {
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>Error!</h3>";
        echo "<p>Failed to add the column: " . mysqli_error($conn) . "</p>";
        echo "</div>";
    }
} else {
    echo "<div style='background-color: #cce5ff; color: #004085; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>Information</h3>";
    echo "<p>The 'qrcode_path' column already exists in the members table.</p>";
    echo "</div>";
}

// Close the connection
mysqli_close($conn);

echo "<div style='margin: 20px 0;'>";
echo "<a href='index.php' style='background-color: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>Return to Home</a>";
echo "</div>";
?>