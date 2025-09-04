<?php
// index.php (Login Page with Permission Setup)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'db.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, full_name, password_hash, role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);

                // --- Store Basic User Info in Session ---
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_full_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];

                // --- NEW: Set Category Access Permissions ---
                $allowed_categories = [];
                if ($user['role'] === 'owner') {
                    // The owner gets access to ALL categories. We fetch all IDs.
                    $cat_stmt = $pdo->query("SELECT id FROM categories");
                    $allowed_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
                } else {
                    // All other users get their specifically assigned categories.
                    $cat_stmt = $pdo->prepare("SELECT category_id FROM user_category_access WHERE user_id = ?");
                    $cat_stmt->execute([$user['id']]);
                    $allowed_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
                }
                $_SESSION['allowed_categories'] = $allowed_categories;
                // ---------------------------------------------

                header('Location: dashboard.php');
                exit;
            } else {
                $error_message = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $error_message = 'An error occurred. Please try again later.';
        }
    }
}

$page_title = 'Login';
require_once 'header.php';
?>

<div class="flex items-center justify-center min-h-full py-12 px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-md space-y-8">
        <div class="bg-white p-6 sm:p-8 lg:p-10 rounded-xl shadow-lg">
            <div><h2 class="mt-2 text-center text-2xl sm:text-3xl font-extrabold text-gray-900">Sign in to your account</h2></div>
            <?php if (!empty($error_message)): ?>
                <div class="mt-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert"><p><?php echo htmlspecialchars($error_message); ?></p></div>
            <?php endif; ?>
            <form class="mt-8 space-y-6" action="index.php" method="POST">
                <div class="rounded-md shadow-sm -space-y-px">
                    <div><label for="username" class="sr-only">Username</label><input id="username" name="username" type="text" required class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Username"></div>
                    <div><label for="password" class="sr-only">Password</label><input id="password" name="password" type="password" required class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Password"></div>
                </div>
                <div><button type="submit" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Sign in</button></div>
            </form>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>