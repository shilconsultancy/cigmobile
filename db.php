<?php
// includes/db.php

// --- Database Configuration ---
// !! IMPORTANT !!
// Replace these values with your actual database credentials.
$db_host = 'localhost';     // Usually 'localhost'
$db_name = 'wholesale_db';  // The name of the database we created
$db_user = 'root';          // Your database username (e.g., 'root' for local dev)
$db_pass = '';              // Your database password (leave empty for default XAMPP/WAMP)

// --- Data Source Name (DSN) ---
// This string tells PDO which driver to use and how to connect.
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";

// --- PDO Connection Options ---
// These options configure how PDO handles errors and fetches data.
$options = [
    // Throw exceptions on SQL errors, which makes them easier to catch.
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Fetch results as associative arrays (e.g., $row['full_name']) instead of indexed arrays.
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Disable emulation of prepared statements for security.
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// --- Create the PDO Database Connection ---
try {
    // Attempt to create a new PDO instance to connect to the database.
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    // If the connection fails, the script will stop and display an error message.
    // For a live application, you would log this error to a file instead of showing it.
    error_log("Database Connection Error: " . $e->getMessage());
    die("Could not connect to the database. Please try again later.");
}

// If the script reaches this point, the connection was successful.
// The '$pdo' variable can now be used to run queries on the database.
