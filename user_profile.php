<?php
// user_profile.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'db.php';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id === 0) {
    die("No user specified.");
}

try {
    // Fetch user details and their manager's name
    $user_stmt = $pdo->prepare(
        "SELECT u.id, u.full_name, u.username, u.role, p.full_name as manager_name
         FROM users u
         LEFT JOIN users p ON u.reports_to = p.id
         WHERE u.id = ?"
    );
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    if (!$user) {
        die("User not found.");
    }

    // Fetch performance stats
    $stats_stmt = $pdo->prepare(
        "SELECT SUM(total_amount) as total_sales,
         SUM(total_amount - (total_pcs * cost_per_pc_at_sale)) as total_profit
         FROM orders WHERE user_id = ?"
    );
    $stats_stmt->execute([$user_id]);
    $stats = $stats_stmt->fetch();

    // Fetch all invoices for this user
    $invoice_stmt = $pdo->prepare(
        "SELECT 
            o.invoice_id, 
            MIN(o.order_date) as invoice_date,
            c.id as customer_id,
            c.name as customer_name,
            SUM(o.total_amount) as invoice_total,
            MIN(o.payment_status) as payment_status
         FROM orders o
         JOIN customers c ON o.customer_id = c.id
         WHERE o.user_id = ?
         GROUP BY o.invoice_id, c.id, c.name
         ORDER BY invoice_date DESC"
    );
    $invoice_stmt->execute([$user_id]);
    $invoices = $invoice_stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$page_title = 'User Profile: ' . htmlspecialchars($user['full_name']);
require_once 'header.php';
?>

<div class="bg-white p-4 sm:p-6 lg:p-8 rounded-xl shadow-lg max-w-7xl mx-auto space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">User Profile</h1>
        <a href="users_manage.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to User List</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="md:col-span-1">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Employee Details</h2>
            <div class="space-y-2 text-gray-700">
                <p><strong>Name:</strong><br><span class="text-lg"><?php echo htmlspecialchars($user['full_name']); ?></span></p>
                <p><strong>Username:</strong><br><span class="text-lg"><?php echo htmlspecialchars($user['username']); ?></span></p>
                <p><strong>Role:</strong><br><span class="text-lg"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($user['role']))); ?></span></p>
                <p><strong>Reports To:</strong><br><span class="text-lg"><?php echo htmlspecialchars($user['manager_name'] ?? 'N/A'); ?></span></p>
            </div>
            <h2 class="text-xl font-semibold text-gray-800 mt-6 mb-4">Lifetime Performance</h2>
            <div class="space-y-2 text-gray-700">
                <p><strong>Total Sales:</strong> <span class="font-bold text-green-700 text-lg">৳<?php echo number_format($stats['total_sales'] ?? 0, 2); ?></span></p>
                <p><strong>Total Profit:</strong> <span class="font-bold text-blue-700 text-lg">৳<?php echo number_format($stats['total_profit'] ?? 0, 2); ?></span></p>
            </div>
        </div>

        <div class="md:col-span-2">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Invoice History</h2>
            <div class="overflow-x-auto border rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Customer</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th><th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th></tr></thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($invoices)): ?>
                            <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">No invoices found for this user.</td></tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-indigo-600"><a href="invoice_view.php?id=<?php echo $invoice['invoice_id']; ?>" class="hover:underline">#<?php echo htmlspecialchars($invoice['invoice_id']); ?></a></td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800"><a href="customer_profile.php?id=<?php echo $invoice['customer_id']; ?>" class="text-indigo-600 hover:underline"><?php echo htmlspecialchars($invoice['customer_name']); ?></a></td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 text-right font-semibold">৳<?php echo number_format($invoice['invoice_total'], 2); ?></td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <?php $status_color = $invoice['payment_status'] === 'Paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_color; ?>"><?php echo htmlspecialchars($invoice['payment_status']); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>