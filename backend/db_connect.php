<?php
$host = "localhost";
$user = "jvu15";
$pass = "jvu15";
$dbname = "jvu15";

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8 for proper character handling
$conn->set_charset("utf8");
?>
