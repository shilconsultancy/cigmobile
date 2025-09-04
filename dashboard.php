<?php
// dashboard.php

// This is a protected page.
require_once 'auth.php';
require_once 'db.php';

$page_title = 'Dashboard';
require_once 'header.php';

// Use the null coalescing operator (??) to prevent deprecation warnings.
$user_role = $_SESSION['user_role'] ?? 'guest';
$user_name = $_SESSION['user_full_name'] ?? 'User';
?>

<div class="bg-white p-4 sm:p-6 lg:p-8 rounded-xl shadow-lg">
    <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Dashboard</h1>
    <p class="text-base sm:text-lg mt-2 mb-6">
        Welcome, <?php echo htmlspecialchars($user_name); ?>!
        Your role is: <span class="font-semibold text-indigo-600 capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $user_role)); ?></span>
    </p>
    
    <div class="border-t pt-6">
        <h2 class="text-lg sm:text-xl font-bold mb-4">Your Actions</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

            <?php // --- Action for ALL logged-in users --- ?>
            <a href="order_new.php" class="bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded-lg text-center transition duration-300">Create New Order</a>

            <?php // --- THIS IS THE LINK YOU ARE ASKING ABOUT --- ?>
            <?php if ($user_role === 'sales'): ?>
                <a href="orders_my.php" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-4 rounded-lg text-center transition duration-300">View My Orders</a>
            <?php endif; ?>

            <?php // --- Actions for Managers and above --- ?>
            <?php if (in_array($user_role, ['sales_head', 'supervisor', 'manager', 'owner'])): ?>
                <a href="orders_team.php" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-4 rounded-lg text-center transition duration-300">View Team Orders</a>
                <a href="users_manage.php" class="bg-purple-500 hover:bg-purple-600 text-white font-bold py-3 px-4 rounded-lg text-center transition duration-300">Manage Users</a>
            <?php endif; ?>

            <?php // --- Actions for Owner only --- ?>
            <?php if ($user_role === 'owner'): ?>
                <a href="categories_manage.php" class="bg-teal-500 hover:bg-teal-600 text-white font-bold py-3 px-4 rounded-lg text-center transition duration-300">Manage Categories</a>
                <a href="inventory_manage.php" class="bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-3 px-4 rounded-lg text-center transition duration-300">Manage Products & Inventory</a>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>