<?php
$conn = new mysqli('localhost', 'root', '', 'shell');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
