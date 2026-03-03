<?php
// config/db.php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ocean_expense_manager";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    die();
}
?>
