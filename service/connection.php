<?php
$server_name = 'localhost';
$username = 'root';
$password = '';
$database = "kasir";

// Create connection with error handling
try {
    $conn = new mysqli($server_name, $username, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to prevent issues
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("System error. Please try again later.");
}
?>