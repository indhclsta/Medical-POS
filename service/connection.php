<?php
$server_name = 'localhost';
$username = 'root'; 
$password = '';
$database = "medipos";

// Create connection
$conn = new mysqli($server_name, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die(json_encode([
        'success' => false,
        'error' => 'Database connection failed',
        'message' => $conn->connect_error
    ]));
}

$conn->set_charset("utf8mb4");

// Debugging - verify connection
error_log("Successfully connected to database: " . $database);
?>