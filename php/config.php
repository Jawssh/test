<?php
// Database connection settings
$servername = "localhost";
$username = "root"; // Adjust based on your server setup
$password = "";
$dbname = "mappinghope";

// Create a connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
