<?php
// orders_my.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'db.php';

$orders = [];
$logged_in_user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare(
        "SELECT o.id, o.order_date, o.customer_name, p.name AS product_name,
            o.total_pcs, o.total_amount, o.status
         FROM orders o
         JOIN products p ON o.product_id = p.id
         WHERE o.user_id = ?
         ORDER BY o.order_date DESC"
    );
    $stmt->execute([$logged_in_user_id]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    die("A database error occurred. Please try again later.");
}

$page_title = 'My Orders';
require_once 'header.php';
?>

<div class="bg-white p-4 sm:p-6 lg:p-8 rounded-xl shadow-lg max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6 border-b pb-4">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">My Order History</h1>
        <a href="dashboard.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to Dashboard</a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Quantity (Packets)</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($orders)): ?>
                    <tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">You have not created any orders yet.<a href="order_new.php" class="mt-2 inline-block text-white bg-green-500 hover:bg-green-600 font-medium rounded-lg text-sm px-5 py-2.5">Create Your First Order</a></td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#<?php echo htmlspecialchars($order['id']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars(date('M d, Y, g:i A', strtotime($order['order_date']))); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($order['product_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-semibold"><?php echo number_format($order['total_pcs']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-700 text-right font-bold">৳<?php echo number_format($order['total_amount'], 2); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php
                                    $status_color = 'bg-gray-100 text-gray-800';
                                    if ($order['status'] === 'Completed') $status_color = 'bg-green-100 text-green-800';
                                    elseif ($order['status'] === 'Cancelled') $status_color = 'bg-red-100 text-red-800';
                                ?>
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_color; ?>"><?php echo htmlspecialchars($order['status']); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
require_once 'footer.php';
?>