<?php
// users_manage.php (FINAL - with Owner Creation & Full CRUD)

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

// --- Define which roles each manager can create/edit ---
// UPDATED: Owners can now create other Owners.
$creatable_roles = [];
switch ($current_user_role) {
    case 'owner': $creatable_roles = ['owner', 'manager', 'supervisor', 'sales_head', 'sales']; break;
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
    if ($current_user_role === 'owner') {
        $cat_stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
        $manager_accessible_categories = $cat_stmt->fetchAll();
    } else {
        $cat_stmt = $pdo->prepare("SELECT c.id, c.name FROM categories c JOIN user_category_access uca ON c.id = uca.category_id WHERE uca.user_id = ? ORDER BY c.name ASC");
        $cat_stmt->execute([$current_user_id]);
        $manager_accessible_categories = $cat_stmt->fetchAll();
    }
    
    // UPDATED: Owners can report to no one (NULL), so we add this option for the dropdown.
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
        } else { $ids_to_check = []; }
    }

    if (!empty($all_subordinate_ids)) {
        $placeholders = rtrim(str_repeat('?,', count($all_subordinate_ids)), ',');
        $user_list_stmt = $pdo->prepare(
            "SELECT u.id, u.full_name, u.username, u.role, u.reports_to, m.full_name as manager_name,
             GROUP_CONCAT(uca.category_id) as assigned_category_ids,
             GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') as accessible_categories
             FROM users u
             LEFT JOIN users m ON u.reports_to = m.id
             LEFT JOIN user_category_access uca ON u.id = uca.user_id
             LEFT JOIN categories c ON uca.category_id = c.id
             WHERE u.id IN ($placeholders)
             GROUP BY u.id
             ORDER BY u.full_name"
        );
        $user_list_stmt->execute($all_subordinate_ids);
        $team_members = $user_list_stmt->fetchAll();
    }

} catch (PDOException $e) { die("A database error occurred while loading user data."); }

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        if (isset($_POST['add_new_user'])) {
            $full_name = trim($_POST['full_name']); $username = trim($_POST['username']); $password = trim($_POST['password']); $role = trim($_POST['role']); 
            // UPDATED: Handle NULL for 'reports_to' when creating an owner
            $reports_to = ($role === 'owner') ? null : (int)$_POST['reports_to'];
            $assigned_category_ids = $_POST['category_ids'] ?? [];
            $manager_allowed_cat_ids = array_column($manager_accessible_categories, 'id');
            $is_permission_valid = empty(array_diff($assigned_category_ids, $manager_allowed_cat_ids));

            if (empty($full_name) || empty($username) || empty($password) || empty($role)) { throw new Exception('Full name, username, password, and role are required.'); }
            if ($role !== 'owner' && empty($reports_to)) { throw new Exception('A manager must be selected for non-owner roles.'); }
            if (!in_array($role, $creatable_roles)) { throw new Exception('You are not authorized to create a user with this role.'); }
            if (!$is_permission_valid) { throw new Exception('Security error: You cannot assign a category you do not have access to.'); }
            
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?"); $stmt->execute([$username]);
            if ($stmt->fetch()) { throw new Exception('This username is already taken.'); }
            
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $pdo->prepare("INSERT INTO users (full_name, username, password_hash, role, reports_to) VALUES (?, ?, ?, ?, ?)");
            $insert_stmt->execute([$full_name, $username, $password_hash, $role, $reports_to]);
            $new_user_id = $pdo->lastInsertId();

            if (!empty($assigned_category_ids)) {
                $cat_access_stmt = $pdo->prepare("INSERT INTO user_category_access (user_id, category_id) VALUES (?, ?)");
                foreach ($assigned_category_ids as $cat_id) { $cat_access_stmt->execute([$new_user_id, $cat_id]); }
            }
            $success_message = 'New user created successfully!';
        }
        elseif (isset($_POST['update_user'])) {
            $user_id_to_update = (int)$_POST['user_id']; $full_name = trim($_POST['full_name']); $username = trim($_POST['username']); $password = trim($_POST['password']); $role = trim($_POST['role']); 
            $reports_to = ($role === 'owner') ? null : (int)$_POST['reports_to'];
            $assigned_category_ids = $_POST['category_ids'] ?? [];
            $manager_allowed_cat_ids = array_column($manager_accessible_categories, 'id');
            $is_permission_valid = empty(array_diff($assigned_category_ids, $manager_allowed_cat_ids));
            
            if (empty($full_name) || empty($username) || empty($role)) { throw new Exception('Full name, username, and role are required.'); }
            if ($role !== 'owner' && empty($reports_to)) { throw new Exception('A manager must be selected for non-owner roles.'); }
            if (!in_array($role, $creatable_roles)) { throw new Exception('You are not authorized to assign this role.'); }
            if (!$is_permission_valid) { throw new Exception('Security error: You cannot assign a category you do not have access to.'); }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?"); $stmt->execute([$username, $user_id_to_update]);
            if ($stmt->fetch()) { throw new Exception('This username is already taken by another user.'); }

            $sql = "UPDATE users SET full_name = ?, username = ?, role = ?, reports_to = ?"; $params = [$full_name, $username, $role, $reports_to];
            if (!empty($password)) { $sql .= ", password_hash = ?"; $params[] = password_hash($password, PASSWORD_DEFAULT); }
            $sql .= " WHERE id = ?"; $params[] = $user_id_to_update;
            $update_stmt = $pdo->prepare($sql); $update_stmt->execute($params);

            $delete_cats_stmt = $pdo->prepare("DELETE FROM user_category_access WHERE user_id = ?"); $delete_cats_stmt->execute([$user_id_to_update]);
            if (!empty($assigned_category_ids)) {
                $cat_access_stmt = $pdo->prepare("INSERT INTO user_category_access (user_id, category_id) VALUES (?, ?)");
                foreach ($assigned_category_ids as $cat_id) { $cat_access_stmt->execute([$user_id_to_update, $cat_id]); }
            }
            $success_message = 'User updated successfully!';
        }
        elseif (isset($_POST['delete_user'])) {
            $user_id_to_delete = (int)$_POST['user_id'];
            $delete_stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $delete_stmt->execute([$user_id_to_delete]);
            $success_message = 'User deleted successfully!';
        }
        $pdo->commit();
        header("Location: users_manage.php?success=" . urlencode($success_message));
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}
if(isset($_GET['success'])) {
    $success_message = $_GET['success'];
}

$page_title = 'Manage Users & Permissions';
require_once 'header.php';
?>

<div class="bg-white p-4 sm:p-6 lg:p-8 rounded-xl shadow-lg max-w-7xl mx-auto space-y-8">
    <div class="flex items-center justify-between border-b pb-4"><h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Manage Users & Permissions</h1><a href="dashboard.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to Dashboard</a></div>
    <?php if (!empty($error_message)): ?><div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded"><p><?php echo htmlspecialchars($error_message); ?></p></div><?php endif; ?>
    <?php if (!empty($success_message)): ?><div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded"><p><?php echo htmlspecialchars($success_message); ?></p></div><?php endif; ?>
    
    <div class="border p-5 rounded-lg bg-gray-50">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New User</h2>
        <form action="users_manage.php" method="POST" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <input type="text" name="full_name" required class="px-3 py-2 border border-gray-300 rounded-md shadow-sm" placeholder="Full Name">
                <input type="text" name="username" required class="px-3 py-2 border border-gray-300 rounded-md shadow-sm" placeholder="Username">
                <input type="password" name="password" required class="px-3 py-2 border border-gray-300 rounded-md shadow-sm" placeholder="Password">
                <select name="role" id="add-role-select" required class="px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm"><option value="">Select a role</option><?php foreach ($creatable_roles as $role_option): ?><option value="<?php echo $role_option; ?>"><?php echo ucfirst(str_replace('_', ' ', $role_option)); ?></option><?php endforeach; ?></select>
                <select name="reports_to" id="add-reports-to-select" class="px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm"><option value="">Select a manager</option><?php foreach ($potential_managers as $manager): ?><option value="<?php echo $manager['id']; ?>"><?php echo htmlspecialchars($manager['full_name']) . ' (' . ucfirst(str_replace('_', ' ', $manager['role'])) . ')'; ?></option><?php endforeach; ?></select>
            </div>
            <div class="pt-4 border-t"><label class="block text-sm font-medium text-gray-900 mb-2">Assign Category Access</label>
                <?php if ($current_user_role !== 'owner'): ?><p class="text-xs text-gray-500 mb-3">You can only assign categories that you have access to.</p><?php endif; ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    <?php if (empty($manager_accessible_categories)): ?><p class="text-sm text-red-600 col-span-full">You do not have access to any categories to assign.</p><?php else: ?><?php foreach ($manager_accessible_categories as $category): ?><div class="flex items-center"><input id="add_cat_<?php echo $category['id']; ?>" name="category_ids[]" type="checkbox" value="<?php echo $category['id']; ?>" class="h-4 w-4 text-indigo-600 border-gray-300 rounded"><label for="add_cat_<?php echo $category['id']; ?>" class="ml-3 block text-sm text-gray-700"><?php echo htmlspecialchars($category['name']); ?></label></div><?php endforeach; ?><?php endif; ?>
                </div>
            </div>
            <div class="flex justify-end pt-4"><button type="submit" name="add_new_user" class="w-full sm:w-auto justify-center py-2 px-6 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">Create User</button></div>
        </form>
    </div>
    
    <div>
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Your Team Members</h2>
        <div class="overflow-x-auto border rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category Access</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th></tr></thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($team_members)): ?><tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">You have no team members reporting to you.</td></tr>
                    <?php else: ?><?php foreach ($team_members as $user): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><a href="user_profile.php?id=<?php echo $user['id']; ?>" class="text-indigo-600 hover:underline"><?php echo htmlspecialchars($user['full_name']); ?></a></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($user['role']))); ?></span></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($user['accessible_categories'] ?? 'None'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-4">
                                <button type="button" class="edit-user-btn text-indigo-600 hover:text-indigo-900" data-user-id="<?php echo $user['id']; ?>" data-full-name="<?php echo htmlspecialchars($user['full_name']); ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>" data-role="<?php echo $user['role']; ?>" data-reports-to="<?php echo $user['reports_to']; ?>" data-category-ids="<?php echo htmlspecialchars($user['assigned_category_ids']); ?>">Edit</button>
                                <button type="button" class="delete-user-btn text-red-600 hover:text-red-900" data-user-id="<?php echo $user['id']; ?>" data-full-name="<?php echo htmlspecialchars($user['full_name']); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div id="edit-user-modal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-75 z-50 flex items-center justify-center p-4"><div class="relative bg-white rounded-lg shadow-xl p-6 sm:p-8 w-full max-w-2xl mx-auto"><button id="edit-modal-close-btn" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">&times;</button><h3 class="text-xl leading-6 font-bold text-gray-900 mb-4">Edit User</h3><form action="users_manage.php" method="POST" class="space-y-6"><input type="hidden" name="user_id" id="edit-user-id"><div class="grid grid-cols-1 md:grid-cols-2 gap-6"><input type="text" name="full_name" id="edit-full-name" required placeholder="Full Name" class="px-3 py-2 border border-gray-300 rounded-md"><input type="text" name="username" id="edit-username" required placeholder="Username" class="px-3 py-2 border border-gray-300 rounded-md"><input type="password" name="password" placeholder="New Password (optional)" class="px-3 py-2 border border-gray-300 rounded-md"><select name="role" id="edit-role-select" required class="px-3 py-2 border border-gray-300 rounded-md"><option value="">Select a role</option><?php foreach ($creatable_roles as $role_option): ?><option value="<?php echo $role_option; ?>"><?php echo ucfirst(str_replace('_', ' ', $role_option)); ?></option><?php endforeach; ?></select><select name="reports_to" id="edit-reports-to-select" class="px-3 py-2 border border-gray-300 rounded-md"><option value="">Select a manager</option><?php foreach ($potential_managers as $manager): ?><option value="<?php echo $manager['id']; ?>"><?php echo htmlspecialchars($manager['full_name']); ?></option><?php endforeach; ?></select></div><div class="pt-4 border-t"><label class="block text-sm font-medium text-gray-900 mb-2">Update Category Access</label><div id="edit-category-container" class="grid grid-cols-2 sm:grid-cols-3 gap-4"><?php foreach ($manager_accessible_categories as $category): ?><div class="flex items-center"><input id="edit_cat_<?php echo $category['id']; ?>" name="category_ids[]" type="checkbox" value="<?php echo $category['id']; ?>" class="h-4 w-4 text-indigo-600 border-gray-300 rounded"><label for="edit_cat_<?php echo $category['id']; ?>" class="ml-3 block text-sm text-gray-700"><?php echo htmlspecialchars($category['name']); ?></label></div><?php endforeach; ?></div></div><div class="flex justify-end pt-4"><button type="submit" name="update_user" class="py-2 px-6 border rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Save Changes</button></div></form></div></div>
<div id="delete-user-modal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-75 z-50 flex items-center justify-center p-4"><div class="relative bg-white rounded-lg shadow-xl p-6 sm:p-8 w-full max-w-md mx-auto text-center"><h3 class="text-xl font-bold text-gray-900">Confirm Deletion</h3><p class="my-4 text-gray-600">Are you sure you want to delete user <strong id="delete-user-name"></strong>? This action cannot be undone.</p><form action="users_manage.php" method="POST" class="flex justify-center gap-4"><input type="hidden" name="user_id" id="delete-user-id"><button type="button" id="delete-modal-cancel-btn" class="py-2 px-6 border rounded-md">Cancel</button><button type="submit" name="delete_user" class="py-2 px-6 border rounded-md text-white bg-red-600 hover:bg-red-700">Yes, Delete</button></form></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('edit-user-modal');
    const deleteModal = document.getElementById('delete-user-modal');

    function toggleReportsTo(roleSelect, reportsToSelect) {
        if (roleSelect.value === 'owner') {
            reportsToSelect.disabled = true;
            reportsToSelect.value = '';
            reportsToSelect.classList.add('bg-gray-200');
        } else {
            reportsToSelect.disabled = false;
            reportsToSelect.classList.remove('bg-gray-200');
        }
    }
    
    const addRoleSelect = document.getElementById('add-role-select');
    const addReportsToSelect = document.getElementById('add-reports-to-select');
    addRoleSelect.addEventListener('change', () => toggleReportsTo(addRoleSelect, addReportsToSelect));

    const editRoleSelect = document.getElementById('edit-role-select');
    const editReportsToSelect = document.getElementById('edit-reports-to-select');
    editRoleSelect.addEventListener('change', () => toggleReportsTo(editRoleSelect, editReportsToSelect));

    document.body.addEventListener('click', function(e) {
        if (e.target.classList.contains('edit-user-btn')) {
            const btn = e.target;
            document.getElementById('edit-user-id').value = btn.dataset.userId;
            document.getElementById('edit-full-name').value = btn.dataset.fullName;
            document.getElementById('edit-username').value = btn.dataset.username;
            editRoleSelect.value = btn.dataset.role;
            editReportsToSelect.value = btn.dataset.reportsTo;
            toggleReportsTo(editRoleSelect, editReportsToSelect);
            
            const assignedCategoryIds = btn.dataset.categoryIds ? btn.dataset.categoryIds.split(',') : [];
            const categoryCheckboxes = editModal.querySelectorAll('input[name="category_ids[]"]');
            categoryCheckboxes.forEach(cb => { cb.checked = assignedCategoryIds.includes(cb.value); });
            editModal.classList.remove('hidden');
        }
        if (e.target.classList.contains('delete-user-btn')) {
            const btn = e.target;
            document.getElementById('delete-user-id').value = btn.dataset.userId;
            document.getElementById('delete-user-name').textContent = btn.dataset.fullName;
            deleteModal.classList.remove('hidden');
        }
    });
    document.getElementById('edit-modal-close-btn').addEventListener('click', () => editModal.classList.add('hidden'));
    document.getElementById('delete-modal-cancel-btn').addEventListener('click', () => deleteModal.classList.add('hidden'));
});
</script>

<?php
require_once 'footer.php';
?>