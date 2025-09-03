<?php
// signup.php (Temporary Super Admin Creator)

// --- PHP ERROR REPORTING (for debugging) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// -------------------------------------------

// We need the database connection.
require_once 'db.php';

// Initialize variables for messages.
$error_message = '';
$success_message = '';

// Process the form submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data and trim whitespace.
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // --- Validation ---
    if (empty($full_name) || empty($username) || empty($password) || empty($confirm_password)) {
        $error_message = 'All fields are required.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        // Validation passed, proceed with database operations.
        try {
            // Check if username already exists.
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->fetch()) {
                $error_message = 'This username is already taken. Please choose another.';
            } else {
                // Username is unique, proceed to create the account.
                
                // Securely hash the password. Never store plain text passwords!
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Prepare the SQL INSERT statement.
                // The role is hard-coded to 'owner' and reports_to is NULL.
                $insert_stmt = $pdo->prepare(
                    "INSERT INTO users (full_name, username, password_hash, role, reports_to) 
                     VALUES (?, ?, ?, 'owner', NULL)"
                );
                
                // Execute the statement with the user's data.
                $insert_stmt->execute([$full_name, $username, $password_hash]);
                
                $success_message = 'Super admin account created successfully! You can now log in using the main page.';
            }
        } catch (PDOException $e) {
            error_log("Signup Error: " . $e->getMessage());
            $error_message = 'A database error occurred. Please try again later.';
        }
    }
}

$page_title = 'Create Super Admin';
// We don't include header.php here because we don't want the navigation bar on this page.
// We will create a simplified header section directly in this file.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Wholesale App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-100 text-gray-900 font-sans">
    <div id="app" class="min-h-screen flex flex-col">
        <main class="flex-grow">
            <div class="flex items-center justify-center min-h-full py-12 px-4 sm:px-6 lg:px-8">
                <div class="w-full max-w-md space-y-8">
                    <div class="bg-white p-6 sm:p-8 lg:p-10 rounded-xl shadow-lg">
                        <div>
                            <h2 class="mt-2 text-center text-2xl sm:text-3xl font-extrabold text-gray-900">
                                Create Super Admin Account
                            </h2>
                            <p class="mt-2 text-center text-sm text-gray-600">
                                This page should be deleted after the first account is created.
                            </p>
                        </div>

                        <?php if (!empty($error_message)): ?>
                            <div class="mt-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                                <p><?php echo htmlspecialchars($error_message); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success_message)): ?>
                            <div class="mt-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                                <p><?php echo htmlspecialchars($success_message); ?></p>
                                <div class="mt-4">
                                    <a href="index.php" class="font-bold text-white bg-indigo-600 hover:bg-indigo-700 py-2 px-4 rounded-md">Go to Login Page</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Only show the form if the success message is not set -->
                            <form class="mt-8 space-y-6" action="signup.php" method="POST">
                                <div class="rounded-md shadow-sm -space-y-px">
                                    <div>
                                        <label for="full_name" class="sr-only">Full Name</label>
                                        <input id="full_name" name="full_name" type="text" required class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="Full Name">
                                    </div>
                                    <div>
                                        <label for="username" class="sr-only">Username</label>
                                        <input id="username" name="username" type="text" required class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="Username">
                                    </div>
                                    <div>
                                        <label for="password" class="sr-only">Password</label>
                                        <input id="password" name="password" type="password" required class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="Password (min. 6 characters)">
                                    </div>
                                    <div>
                                        <label for="confirm_password" class="sr-only">Confirm Password</label>
                                        <input id="confirm_password" name="confirm_password" type="password" required class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="Confirm Password">
                                    </div>
                                </div>

                                <div>
                                    <button type="submit" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        Create Account
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>