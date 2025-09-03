<?php
// users_manage.php

// --- PHP ERROR REPORTING (for debugging) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// -------------------------------------------

require_once 'auth.php';
require_once 'db.php';

// --- Authorization Check ---
// UPDATED: Changed 'head' to 'sales_head'
$allowed_roles_to_view_page = ['sales_head', 'supervisor', 'manager', 'owner'];
$current_user_role = $_SESSION['user_role'];
$current_user_id = $_SESSION['user_id'];

if (!in_array($current_user_role, $allowed_roles_to_view_page)) {
    die("Access Denied. You do not have permission to view this page.");
}

// --- Define which roles each manager can create based on the hierarchy ---
// UPDATED: Changed 'head' to 'sales_head'
$creatable_roles = [];
switch ($current_user_role) {
    case 'owner':
        $creatable_roles = ['manager', 'supervisor', 'sales_head', 'sales'];
        break;
    case 'manager':
        $creatable_roles = ['supervisor', 'sales_head', 'sales'];
        break;
    case 'supervisor':
        $creatable_roles = ['sales_head', 'sales'];
        break;
    case 'sales_head':
        $creatable_roles = ['sales'];
        break;
}

// Initialize variables
$error_message = '';
$success_message = '';
$users = [];
$potential_managers = [];

// Handle the "Add New User" form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);
    $reports_to = (int)$_POST['reports_to'];

    if (empty($full_name) || empty($username) || empty($password) || empty($role) || empty($reports_to)) {
        $error_message = 'All fields are required.';
    } elseif (!in_array($role, $creatable_roles)) {
        $error_message = 'You are not authorized to create a user with this role.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error_message = 'This username is already taken.';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $insert_stmt = $pdo->prepare(
                    "INSERT INTO users (full_name, username, password_hash, role, reports_to)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $insert_stmt->execute([$full_name, $username, $password_hash, $role, $reports_to]);
                $success_message = 'New user created successfully!';
            }
        } catch (PDOException $e) {
            error_log("User Management Error: " . $e->getMessage());
            $error_message = 'A database error occurred.';
        }
    }
}

// --- Fetch data for displaying on the page ---
try {
    // UPDATED: Changed 'head' to 'sales_head' in the IN clause
    $manager_stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE role IN ('owner', 'manager', 'supervisor', 'sales_head') ORDER BY full_name");
    $manager_stmt->execute();
    $potential_managers = $manager_stmt->fetchAll();
    
    $ids_to_check = [$current_user_id];
    $all_subordinate_ids = [];
    while (!empty($ids_to_check)) {
        $placeholders = rtrim(str_repeat('?,', count($ids_to_check)), ',');
        $sub_stmt = $pdo->prepare("SELECT id FROM users WHERE reports_to IN ($placeholders)");
        $sub_stmt->execute($ids_to_check);
        $subordinates = $sub_stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($subordinates)) {
            $all_subordinate_ids = array_merge($all_subordinate_ids, $subordinates);
            $ids_to_check = $subordinates;
        } else {
            $ids_to_check = [];
        }
    }

    if (!empty($all_subordinate_ids)) {
        $placeholders = rtrim(str_repeat('?,', count($all_subordinate_ids)), ',');
        $user_list_stmt = $pdo->prepare(
            "SELECT u.id, u.full_name, u.username, u.role, m.full_name as manager_name
             FROM users u
             LEFT JOIN users m ON u.reports_to = m.id
             WHERE u.id IN ($placeholders)
             ORDER BY u.full_name"
        );
        $user_list_stmt->execute($all_subordinate_ids);
        $users = $user_list_stmt->fetchAll();
    }

} catch (PDOException $e) {
    error_log("User Management Fetch Error: " . $e->getMessage());
    die("A database error occurred while loading user data.");
}

$page_title = 'Manage Users';
require_once 'header.php';
?>

<div class="bg-white p-4 sm:p-6 lg:p-8 rounded-xl shadow-lg max-w-7xl mx-auto space-y-8">
    <div class="flex items-center justify-between border-b pb-4">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Manage Users</h1>
        <a href="dashboard.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to Dashboard</a>
    </div>

    <!-- Add New User Form Section -->
    <div class="border p-5 rounded-lg bg-gray-50">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New User</h2>
        <?php if (!empty($error_message)): ?>
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded"><p><?php echo htmlspecialchars($success_message); ?></p></div>
        <?php endif; ?>
        
        <form action="users_manage.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div>
                <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                <input type="text" name="full_name" id="full_name" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                <input type="text" name="username" id="username" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" id="password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                <select name="role" id="role" required class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Select a role</option>
                    <?php foreach ($creatable_roles as $role_option): ?>
                        <option value="<?php echo $role_option; ?>"><?php echo ucfirst(str_replace('_', ' ', $role_option)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="reports_to" class="block text-sm font-medium text-gray-700">Reports To</label>
                <select name="reports_to" id="reports_to" required class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Select a manager</option>
                    <?php foreach ($potential_managers as $manager): ?>
                        <option value="<?php echo $manager['id']; ?>">
                            <?php echo htmlspecialchars($manager['full_name']) . ' (' . ucfirst(str_replace('_', ' ', $manager['role'])) . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2 lg:col-span-1 flex items-end">
                <button type="submit" class="w-full justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Create User
                </button>
            </div>
        </form>
    </div>

    <!-- User List Section -->
    <div>
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Your Team Members</h2>
        <div class="overflow-x-auto border rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reports To</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($users)): ?>
                        <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">You have not added any team members yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($user['role']))); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($user['manager_name']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>