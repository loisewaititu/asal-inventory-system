<?php
// Database configuration
$servername = "localhost";  // Server hosting the database
$username = "root";          // Default username for XAMPP
$password = "";              // Default password for XAMPP (empty)
$database = "jl_tracking_system"; // Correct database name (no dot, no space)

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
