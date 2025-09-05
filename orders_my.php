<?php
// orders_my.php (Final Interlinked Version)

ini_set('display_errors', 1); error_reporting(E_ALL);

require_once 'auth.php';
require_once 'db.php';

$invoices = [];
try {
    // UPDATED: Fetches customer_id for linking
    $stmt = $pdo->prepare(
        "SELECT 
            o.invoice_id, 
            c.id as customer_id, c.name AS customer_name,
            MIN(o.order_date) as order_date, 
            MIN(o.payment_status) as payment_status, 
            MIN(o.due_date) as due_date,
            SUM(o.total_amount) as total_invoice_amount
         FROM orders o
         JOIN customers c ON o.customer_id = c.id
         WHERE o.user_id = ?
         GROUP BY o.invoice_id, c.id, c.name
         ORDER BY order_date DESC"
    );
    $stmt->execute([$_SESSION['user_id']]);
    $invoices = $stmt->fetchAll();
} catch (PDOException $e) {
    die("A database error occurred.");
}

$page_title = 'My Invoices';
require_once 'header.php';
?>
<div class="bg-white p-4 sm:p-6 lg:p-8 rounded-xl shadow-lg max-w-7xl mx-auto">
    <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-6 border-b pb-4">My Invoice History</h1>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th><th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th></tr></thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($invoices)): ?><tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">You have not created any invoices yet.</td></tr>
                <?php else: ?><?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-600"><a href="invoice_view.php?id=<?php echo $invoice['invoice_id']; ?>" class="hover:underline">#<?php echo htmlspecialchars($invoice['invoice_id']); ?></a></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 font-medium"><a href="customer_profile.php?id=<?php echo $invoice['customer_id']; ?>" class="text-indigo-600 hover:underline"><?php echo htmlspecialchars($invoice['customer_name']); ?></a></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-700 text-right font-bold">৳<?php echo number_format($invoice['total_invoice_amount'], 2); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <?php $status_color = $invoice['payment_status'] === 'Paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>
                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_color; ?>"><?php echo htmlspecialchars($invoice['payment_status']); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'footer.php'; ?>