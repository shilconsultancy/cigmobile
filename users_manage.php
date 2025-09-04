<?php
// users_manage.php (with Category Access Control)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'db.php';

// --- Authorization Check ---
$allowed_roles_to_view_page = ['sales_head', 'supervisor', 'manager', 'owner'];
$current_user_role = $_SESSION['user_role'];
$current_user_id = $_SESSION['user_id'];

if (!in_array($current_user_role, $allowed_roles_to_view_page)) {
    die("Access Denied. You do not have permission to view this page.");
}

// --- Define which roles each manager can create ---
$creatable_roles = [];
switch ($current_user_role) {
    case 'owner': $creatable_roles = ['manager', 'supervisor', 'sales_head', 'sales']; break;
    case 'manager': $creatable_roles = ['supervisor', 'sales_head', 'sales']; break;
    case 'supervisor': $creatable_roles = ['sales_head', 'sales']; break;
    case 'sales_head': $creatable_roles = ['sales']; break;
}

// Initialize variables
$error_message = '';
$success_message = '';
$team_members = [];
$potential_managers = [];
$manager_accessible_categories = [];

// --- Fetch data required for the page ---
try {
    // 1. Fetch the categories the CURRENT LOGGED-IN MANAGER has access to.
    $cat_stmt = $pdo->prepare(
        "SELECT c.id, c.name FROM categories c
         JOIN user_category_access uca ON c.id = uca.category_id
         WHERE uca.user_id = ? ORDER BY c.name ASC"
    );
    $cat_stmt->execute([$current_user_id]);
    $manager_accessible_categories = $cat_stmt->fetchAll();

    // 2. Fetch all users who could be a manager for the "Reports To" dropdown.
    $manager_stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE role IN ('owner', 'manager', 'supervisor', 'sales_head') ORDER BY full_name");
    $manager_stmt->execute();
    $potential_managers = $manager_stmt->fetchAll();
    
    // 3. Fetch all subordinates of the current user to display in the list.
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
        } else { $ids_to_check = []; }
    }

    if (!empty($all_subordinate_ids)) {
        $placeholders = rtrim(str_repeat('?,', count($all_subordinate_ids)), ',');
        $user_list_stmt = $pdo->prepare(
            "SELECT u.id, u.full_name, u.username, u.role, m.full_name as manager_name,
             GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') as accessible_categories
             FROM users u
             LEFT JOIN users m ON u.reports_to = m.id
             LEFT JOIN user_category_access uca ON u.id = uca.user_id
             LEFT JOIN categories c ON uca.category_id = c.id
             WHERE u.id IN ($placeholders)
             GROUP BY u.id, u.full_name, u.username, u.role, m.full_name
             ORDER BY u.full_name"
        );
        $user_list_stmt->execute($all_subordinate_ids);
        $team_members = $user_list_stmt->fetchAll();
    }

} catch (PDOException $e) { die("A database error occurred while loading user data."); }

// --- Handle the "Add New User" form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);
    $reports_to = (int)$_POST['reports_to'];
    $assigned_category_ids = $_POST['category_ids'] ?? [];

    $manager_allowed_cat_ids = array_column($manager_accessible_categories, 'id');
    $is_permission_valid = empty(array_diff($assigned_category_ids, $manager_allowed_cat_ids));

    if (empty($full_name) || empty($username) || empty($password) || empty($role) || empty($reports_to)) {
        $error_message = 'All fields except categories are required.';
    } elseif (!in_array($role, $creatable_roles)) {
        $error_message = 'You are not authorized to create a user with this role.';
    } elseif (!$is_permission_valid) {
        $error_message = 'Security error: You cannot assign a category you do not have access to.';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) { throw new Exception('This username is already taken.'); }
            
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $pdo->prepare("INSERT INTO users (full_name, username, password_hash, role, reports_to) VALUES (?, ?, ?, ?, ?)");
            $insert_stmt->execute([$full_name, $username, $password_hash, $role, $reports_to]);
            $new_user_id = $pdo->lastInsertId();

            if (!empty($assigned_category_ids)) {
                $cat_access_stmt = $pdo->prepare("INSERT INTO user_category_access (user_id, category_id) VALUES (?, ?)");
                foreach ($assigned_category_ids as $cat_id) {
                    $cat_access_stmt->execute([$new_user_id, $cat_id]);
                }
            }
            
            $pdo->commit();
            $success_message = 'New user and their permissions have been created successfully!';
            // You might want to re-fetch team members here to show the new user instantly, or just rely on a page refresh.
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = 'A database error occurred: ' . $e->getMessage();
        }
    }
}

$page_title = 'Manage Users & Permissions';
require_once 'header.php';
?>

<div class="bg-white p-4 sm:p-6 lg:p-8 rounded-xl shadow-lg max-w-7xl mx-auto space-y-8">
    <div class="flex items-center justify-between border-b pb-4">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Manage Users & Permissions</h1>
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
        
        <form action="users_manage.php" method="POST" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <input type="text" name="full_name" required class="px-3 py-2 border border-gray-300 rounded-md shadow-sm" placeholder="Full Name">
                <input type="text" name="username" required class="px-3 py-2 border border-gray-300 rounded-md shadow-sm" placeholder="Username">
                <input type="password" name="password" required class="px-3 py-2 border border-gray-300 rounded-md shadow-sm" placeholder="Password">
                <select name="role" required class="px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm"><option value="">Select a role</option><?php foreach ($creatable_roles as $role_option): ?><option value="<?php echo $role_option; ?>"><?php echo ucfirst(str_replace('_', ' ', $role_option)); ?></option><?php endforeach; ?></select>
                <select name="reports_to" required class="px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm"><option value="">Select a manager</option><?php foreach ($potential_managers as $manager): ?><option value="<?php echo $manager['id']; ?>"><?php echo htmlspecialchars($manager['full_name']) . ' (' . ucfirst(str_replace('_', ' ', $manager['role'])) . ')'; ?></option><?php endforeach; ?></select>
            </div>
            
            <!-- NEW: Category Access Section -->
            <div class="pt-4 border-t">
                <label class="block text-sm font-medium text-gray-900 mb-2">Assign Category Access</label>
                <p class="text-xs text-gray-500 mb-3">You can only assign categories that you have access to.</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    <?php if (empty($manager_accessible_categories)): ?>
                        <p class="text-sm text-red-600 col-span-full">You do not have access to any categories to assign.</p>
                    <?php else: ?>
                        <?php foreach ($manager_accessible_categories as $category): ?>
                        <div class="flex items-center">
                            <input id="cat_<?php echo $category['id']; ?>" name="category_ids[]" type="checkbox" value="<?php echo $category['id']; ?>" class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                            <label for="cat_<?php echo $category['id']; ?>" class="ml-3 block text-sm text-gray-700"><?php echo htmlspecialchars($category['name']); ?></label>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex justify-end pt-4">
                <button type="submit" class="w-full sm:w-auto justify-center py-2 px-6 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">Create User</button>
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reports To</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category Access</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($team_members)): ?>
                        <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">You have no team members reporting to you.</td></tr>
                    <?php else: ?>
                        <?php foreach ($team_members as $user): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($user['role']))); ?></span></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($user['manager_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($user['accessible_categories'] ?? 'None'); ?></td>
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