<?php
// auth.php

// --- PHP ERROR REPORTING (for debugging) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// -------------------------------------------

// Ensure the session is started.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user_id session variable is NOT set.
// This means the user is not logged in.
if (!isset($_SESSION['user_id'])) {
    // Redirect them to the login page (index.php).
    header('Location: index.php');
    // Stop the script from running further.
    exit;
}
?>