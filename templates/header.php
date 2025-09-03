<?php
// templates/header.php

// Start the session on every page.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - Wholesale App' : 'Wholesale App'; ?></title>
    
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Link to your custom stylesheet (optional, for overrides) -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-gray-100 text-gray-900 font-sans">
    <div id="app" class="min-h-screen flex flex-col">

        <!-- Main Navigation -->
        <nav class="bg-white shadow-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <div class="flex items-center">
                        <a href="/dashboard.php" class="font-bold text-xl text-indigo-600">Wholesale App</a>
                    </div>
                    <div class="flex items-center">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <!-- Show these links only if the user is logged in -->
                            <span class="mr-4 text-gray-700">Welcome, <?php echo htmlspecialchars($_SESSION['user_full_name']); ?>!</span>
                            <a href="/logout.php" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg text-sm transition duration-300">Logout</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Start of Main Content Area -->
        <main class="flex-grow container mx-auto p-4 md:p-6">