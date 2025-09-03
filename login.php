<?php
// login.php

// Start the session to manage user login state.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If the user is already logged in, redirect them to the dashboard.
// They don't need to see the login page again.
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

// Include the database connection file.
require_once 'includes/db.php';

// Initialize a variable to hold any error messages.
$error_message = '';

// Check if the form was submitted using the POST method.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the username and password from the form submission.
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Check if username and password are provided.
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        try {
            // Prepare a secure SQL statement to find the user by their username.
            // Using a prepared statement prevents SQL injection attacks.
            $stmt = $pdo->prepare("SELECT id, full_name, password_hash, role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // Verify if a user was found AND if the submitted password matches the hashed password in the database.
            if ($user && password_verify($password, $user['password_hash'])) {
                // Login successful!
                
                // Regenerate the session ID for security. This helps prevent session fixation attacks.
                session_regenerate_id(true);

                // Store user information in the session.
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_full_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];

                // Redirect the user to their dashboard.
                header('Location: /dashboard.php');
                exit; // Stop script execution after redirect.
            } else {
                // Login failed. Set an error message.
                $error_message = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            // If there's a database error, show a generic error message.
            error_log("Login Error: " . $e->getMessage());
            $error_message = 'An error occurred. Please try again later.';
        }
    }
}

// Set the page title for the header.
$page_title = 'Login';
// Include the header template.
require_once 'templates/header.php';
?>

<!-- HTML Form for Login -->
<div class="flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-10 rounded-xl shadow-lg">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Sign in to your account
            </h2>
        </div>

        <?php if (!empty($error_message)): ?>
            <!-- Display error message if it exists -->
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>

        <form class="mt-8 space-y-6" action="login.php" method="POST">
            <input type="hidden" name="remember" value="true">
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="username" class="sr-only">Username</label>
                    <input id="username" name="username" type="text" required class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="Username">
                </div>
                <div>
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" name="password" type="password" required class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="Password">
                </div>
            </div>

            <div>
                <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Sign in
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Include the footer template.
require_once 'templates/footer.php';
?>