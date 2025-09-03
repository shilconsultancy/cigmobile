<?php
// includes/config.php

// --- Dynamic Base URL Configuration ---
// This code automatically detects the folder your project is in.
// This makes the application portable, so you can move it to a different
// folder or a live server without changing any links.

// Get the directory of the current script, relative to the server's document root.
// We replace backslashes with forward slashes for consistency across operating systems.
$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

// If the project is in the server's root directory, the base path is just '/'.
// Otherwise, it's the directory name followed by a trailing slash.
$base_path = ($script_dir === '/') ? '/' : $script_dir . '/';

// Define the BASE_URL constant that the rest of the application will use.
define('BASE_URL', $base_path);

?>