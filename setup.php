<?php
// Database setup script
$host = 'localhost';
$username = 'root';
$password = '';

// Connect to MySQL without selecting a database
$conn = mysqli_connect($host, $username, $password);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Create the database if it doesn't exist
$db_name = 'gym_manager';
$sql = "CREATE DATABASE IF NOT EXISTS $db_name";
if (mysqli_query($conn, $sql)) {
    echo "<p>Database created successfully or already exists</p>";
} else {
    echo "<p>Error creating database: " . mysqli_error($conn) . "</p>";
    die();
}

// Select the database
mysqli_select_db($conn, $db_name);

// Create tables

// Membership Types Table
$sql = "CREATE TABLE IF NOT EXISTS `membership_types` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `duration` INT NOT NULL COMMENT 'Duration in days',
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "<p>Membership Types table created successfully</p>";
} else {
    echo "<p>Error creating Membership Types table: " . mysqli_error($conn) . "</p>";
}

// Members Table
$sql = "CREATE TABLE IF NOT EXISTS `members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) UNIQUE,
    `phone` VARCHAR(20),
    `gender` ENUM('Male', 'Female') NOT NULL,
    `dob` DATE,
    `address` TEXT,
    `emergency_contact` VARCHAR(100),
    `emergency_phone` VARCHAR(20),
    `emergency_relationship` VARCHAR(50),
    `membership_type_id` INT,
    `registration_date` DATE NOT NULL,
    `payment_date` DATE,
    `start_date` DATE,
    `renewal_date` DATE,
    `status` ENUM('Active', 'Inactive', 'Expired') DEFAULT 'Active',
    `qrcode_path` TEXT,
    `special_comments` TEXT,
    `medical_conditions` TEXT,
    `height` INT,
    `current_weight` INT,
    `desired_weight` INT,
    `social_media_consent` BOOLEAN DEFAULT 0,
    `photo` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`membership_type_id`) REFERENCES `membership_types`(`id`) ON DELETE SET NULL
)";

if (mysqli_query($conn, $sql)) {
    echo "<p>Members table created successfully</p>";
} else {
    echo "<p>Error creating Members table: " . mysqli_error($conn) . "</p>";
}

// Check-ins Table
$sql = "CREATE TABLE IF NOT EXISTS `check_ins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `member_id` INT NOT NULL,
    `check_in_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `check_out_time` TIMESTAMP NULL,
    `verification_method` ENUM('QR', 'Manual', 'QRCode') NOT NULL,
    `notes` TEXT,
    FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
)";

if (mysqli_query($conn, $sql)) {
    echo "<p>Check-ins table created successfully</p>";
} else {
    echo "<p>Error creating Check-ins table: " . mysqli_error($conn) . "</p>";
}

// Payments Table
$sql = "CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `member_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_date` DATE NOT NULL,
    `payment_method` VARCHAR(50),
    `receipt_number` VARCHAR(50),
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
)";

if (mysqli_query($conn, $sql)) {
    echo "<p>Payments table created successfully</p>";
} else {
    echo "<p>Error creating Payments table: " . mysqli_error($conn) . "</p>";
}

// System Settings Table
$sql = "CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT,
    `description` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "<p>Settings table created successfully</p>";
} else {
    echo "<p>Error creating Settings table: " . mysqli_error($conn) . "</p>";
}

// Admin Users Table
$sql = "CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) UNIQUE,
    `role` ENUM('Admin', 'Staff') NOT NULL DEFAULT 'Staff',
    `last_login` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "<p>Users table created successfully</p>";
} else {
    echo "<p>Error creating Users table: " . mysqli_error($conn) . "</p>";
}

// Insert default data

// Check if membership types exist
$check_query = "SELECT COUNT(*) as count FROM membership_types";
$result = mysqli_query($conn, $check_query);
$row = mysqli_fetch_assoc($result);

if ($row['count'] == 0) {
    // Insert default membership types
    $membership_types = [
        ['name' => 'Daily Access', 'price' => 500, 'duration' => 1, 'description' => 'Single day access to the gym'],
        ['name' => 'Weekly Access', 'price' => 2000, 'duration' => 7, 'description' => 'One week access to the gym'],
        ['name' => 'Monthly off-peak', 'price' => 4000, 'duration' => 30, 'description' => 'One month access during off-peak hours only'],
        ['name' => 'Standard Monthly', 'price' => 5000, 'duration' => 30, 'description' => 'Standard one month membership'],
        ['name' => 'Duo Monthly', 'price' => 9000, 'duration' => 30, 'description' => 'Membership for two people'],
        ['name' => '3 Months', 'price' => 14000, 'duration' => 90, 'description' => 'Three months membership'],
        ['name' => '6 Months', 'price' => 27000, 'duration' => 180, 'description' => 'Six months membership'],
        ['name' => '12 Months', 'price' => 52000, 'duration' => 365, 'description' => 'Annual membership']
    ];
    
    foreach ($membership_types as $type) {
        $name = $type['name'];
        $price = $type['price'];
        $duration = $type['duration'];
        $description = $type['description'];
        
        $sql = "INSERT INTO membership_types (name, price, duration, description) 
                VALUES ('$name', $price, $duration, '$description')";
        
        if (mysqli_query($conn, $sql)) {
            echo "<p>Inserted membership type: $name</p>";
        } else {
            echo "<p>Error inserting membership type: " . mysqli_error($conn) . "</p>";
        }
    }
}

// Check if admin user exists
$check_query = "SELECT COUNT(*) as count FROM users";
$result = mysqli_query($conn, $check_query);
$row = mysqli_fetch_assoc($result);

if ($row['count'] == 0) {
    // Insert default admin user (password is 'admin123')
    $username = 'admin';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $full_name = 'System Administrator';
    $email = 'admin@example.com';
    
    $sql = "INSERT INTO users (username, password, full_name, email, role) 
            VALUES ('$username', '$password', '$full_name', '$email', 'Admin')";
    
    if (mysqli_query($conn, $sql)) {
        echo "<p>Default admin user created with username: admin and password: admin123</p>";
    } else {
        echo "<p>Error creating default admin user: " . mysqli_error($conn) . "</p>";
    }
}

// Insert default settings
$settings = [
    ['key' => 'gym_name', 'value' => 'Fitness Center', 'description' => 'Name of the gym'],
    ['key' => 'gym_address', 'value' => '123 Main Street, City', 'description' => 'Physical address of the gym'],
    ['key' => 'gym_phone', 'value' => '+254 123 456789', 'description' => 'Contact phone number'],
    ['key' => 'gym_email', 'value' => 'info@example.com', 'description' => 'Contact email address'],
    ['key' => 'qrcode_enabled', 'value' => '1', 'description' => 'Enable or disable QR code functionality'],
    ['key' => 'check_in_notification', 'value' => '1', 'description' => 'Show notification on member check-in']
];

foreach ($settings as $setting) {
    $key = $setting['key'];
    $value = $setting['value'];
    $description = $setting['description'];
    
    // Check if setting exists
    $check_query = "SELECT COUNT(*) as count FROM settings WHERE setting_key = '$key'";
    $result = mysqli_query($conn, $check_query);
    $row = mysqli_fetch_assoc($result);
    
    if ($row['count'] == 0) {
        $sql = "INSERT INTO settings (setting_key, setting_value, description) 
                VALUES ('$key', '$value', '$description')";
        
        if (mysqli_query($conn, $sql)) {
            echo "<p>Inserted setting: $key</p>";
        } else {
            echo "<p>Error inserting setting: " . mysqli_error($conn) . "</p>";
        }
    }
}

echo "<p>Database setup completed successfully!</p>";
echo "<p><a href='index.php' class='btn btn-primary'>Go to Homepage</a></p>";

// Close connection
mysqli_close($conn);