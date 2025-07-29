<?php
// Database connection parameters
$host = 'localhost';
$username = 'u8gr0sjr9p4p4';
$password = '9yxuqyo3mt85';
$database = 'dbxpdsqd5mx3op';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");
?>
