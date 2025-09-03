<?php
// db.php

// --- PHP ERROR REPORTING (for debugging) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// -------------------------------------------

// --- Database Configuration ---
// !! IMPORTANT !!
// Replace these values with your actual database credentials.
$db_host = 'localhost';     // Usually 'localhost'
$db_name = 'wholesale_db';  // The name of the database
$db_user = 'root';          // Your database username (e.g., 'root' for local dev)
$db_pass = '';              // Your database password (leave empty for default XAMPP/WAMP)

// --- Data Source Name (DSN) ---
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";

// --- PDO Connection Options ---
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// --- Create the PDO Database Connection ---
try {
    // Attempt to create a new PDO instance to connect to the database.
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    // If the connection fails, stop the script and display an error message.
    error_log("Database Connection Error: " . $e->getMessage());
    die("Could not connect to the database. Check credentials in db.php.");
}

// If the script reaches this point, the connection was successful.
// The '$pdo' variable is now available for any file that includes this one.
?>