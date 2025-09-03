<?php
// categories_manage.php

// --- PHP ERROR REPORTING (for debugging) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// -------------------------------------------

// This is a protected page.
require_once 'auth.php';
require_once 'db.php';

// --- Authorization Check ---
// CRITICAL: Only the owner can manage categories.
if ($_SESSION['user_role'] !== 'owner') {
    die("Access Denied. You do not have permission to view this page.");
}

// Initialize variables
$error_message = '';
$success_message = '';
$categories = [];

// Handle the "Add New Category" form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_category_name = trim($_POST['category_name']);

    if (empty($new_category_name)) {
        $error_message = 'Category name cannot be empty.';
    } else {
        try {
            // Check if the category already exists (case-insensitive)
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?)");
            $stmt->execute([$new_category_name]);
            
            if ($stmt->fetch()) {
                $error_message = "A category with the name '".htmlspecialchars($new_category_name)."' already exists.";
            } else {
                // Insert the new category
                $insert_stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
                $insert_stmt->execute([$new_category_name]);
                $success_message = "Category '".htmlspecialchars($new_category_name)."' was created successfully.";
            }
        } catch (PDOException $e) {
            error_log("Category Management Error: " . $e->getMessage());
            $error_message = 'A database error occurred. Please try again.';
        }
    }
}

// --- Fetch all existing categories for display ---
try {
    $stmt = $pdo->query("SELECT id, name, created_at FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Category Fetch Error: " . $e->getMessage());
    die("A database error occurred while fetching categories.");
}

$page_title = 'Manage Categories';
require_once 'header.php';
?>

<div class="bg-white p-4 sm:p-6 lg:p-8 rounded-xl shadow-lg max-w-4xl mx-auto space-y-8">
    <div class="flex items-center justify-between border-b pb-4">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Manage Categories</h1>
        <a href="dashboard.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to Dashboard</a>
    </div>

    <?php if (!empty($error_message)): ?>
    <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert">
        <p class="font-bold">Error</p>
        <p><?php echo htmlspecialchars($error_message); ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
    <div class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md" role="alert">
        <p class="font-bold">Success</p>
        <p><?php echo htmlspecialchars($success_message); ?></p>
    </div>
    <?php endif; ?>

    <!-- Two-column layout: Add Form and Category List -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        
        <!-- Add Category Form -->
        <div class="md:col-span-1 border p-5 rounded-lg bg-gray-50 h-fit">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Category</h2>
            <form action="categories_manage.php" method="POST" class="space-y-4">
                <div>
                    <label for="category_name" class="block text-sm font-medium text-gray-700">Category Name</label>
                    <input type="text" name="category_name" id="category_name" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <button type="submit" class="w-full justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Create Category
                    </button>
                </div>
            </form>
        </div>

        <!-- Category List -->
        <div class="md:col-span-2">
             <h2 class="text-xl font-semibold text-gray-800 mb-4">Existing Categories</h2>
            <div class="overflow-x-auto border rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date Created</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-12 text-center text-gray-500">No categories have been created yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#<?php echo htmlspecialchars($category['id']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars(date('M d, Y', strtotime($category['created_at']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>