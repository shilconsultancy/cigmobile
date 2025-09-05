<?php
// customer_profile.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'db.php';

$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($customer_id === 0) {
    die("No customer specified.");
}

try {
    // Fetch customer details
    $cust_stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $cust_stmt->execute([$customer_id]);
    $customer = $cust_stmt->fetch();
    if (!$customer) {
        die("Customer not found.");
    }

    // Fetch all invoices for this customer, grouped by invoice_id
    $invoice_stmt = $pdo->prepare(
        "SELECT 
            o.invoice_id, 
            MIN(o.order_date) as invoice_date,
            SUM(o.total_amount) as invoice_total,
            MIN(o.payment_status) as payment_status,
            u.full_name as salesperson_name
         FROM orders o
         JOIN users u ON o.user_id = u.id
         WHERE o.customer_id = ?
         GROUP BY o.invoice_id
         ORDER BY invoice_date DESC"
    );
    $invoice_stmt->execute([$customer_id]);
    $invoices = $invoice_stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$page_title = 'Customer Profile: ' . htmlspecialchars($customer['name']);
require_once 'header.php';
?>

<div class="bg-white p-4 sm:p-6 lg:p-8 rounded-xl shadow-lg max-w-7xl mx-auto space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Customer Profile</h1>
        <a href="customers_manage.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to Customer List</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="md:col-span-1">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Customer Details</h2>
            <div class="space-y-4">
                <?php if (!empty($customer['picture_path'])): ?>
                    <img id="shop-image" src="<?php echo htmlspecialchars($customer['picture_path']); ?>" alt="Shop Image" class="rounded-lg w-full object-cover cursor-pointer hover:opacity-90 transition">
                <?php else: ?>
                    <div class="rounded-lg w-full aspect-[4/3] bg-gray-100 flex items-center justify-center">
                        <span class="text-gray-400">No Shop Image</span>
                    </div>
                <?php endif; ?>
                <div class="text-gray-700 space-y-2">
                    <p><strong>Name:</strong><br><span class="text-lg"><?php echo htmlspecialchars($customer['name']); ?></span></p>
                    <p><strong>Phone:</strong><br><span class="text-lg"><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></span></p>
                    <?php if($customer['latitude'] && $customer['longitude']): ?>
                        <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $customer['latitude']; ?>,<?php echo $customer['longitude']; ?>" target="_blank" class="inline-block text-indigo-600 hover:underline">View Location on Google Maps</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="md:col-span-2">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Invoice History</h2>
            <div class="overflow-x-auto border rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th><th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th></tr></thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($invoices)): ?>
                            <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">No invoices found for this customer.</td></tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-indigo-600"><a href="invoice_view.php?id=<?php echo $invoice['invoice_id']; ?>" class="hover:underline">#<?php echo htmlspecialchars($invoice['invoice_id']); ?></a></td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?php echo date('M d, Y', strtotime($invoice['invoice_date'])); ?></td>
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

<!-- Image Viewer Modal -->
<div id="image-modal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-75 z-50 flex items-center justify-center p-4">
    <div class="relative bg-white rounded-lg shadow-xl p-4 max-w-4xl w-full">
        <button id="image-modal-close-btn" class="absolute -top-3 -right-3 text-white bg-gray-800 rounded-full p-1 leading-none text-2xl">&times;</button>
        <img id="modal-image-src" src="" alt="Shop Image" class="w-full h-auto object-contain max-h-[80vh]">
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const imageModal = document.getElementById('image-modal');
    const shopImage = document.getElementById('shop-image');
    if (shopImage && !shopImage.src.includes('placehold.co')) {
        shopImage.addEventListener('click', function() {
            document.getElementById('modal-image-src').src = this.src;
            imageModal.classList.remove('hidden');
        });
    }
    document.getElementById('image-modal-close-btn').addEventListener('click', () => imageModal.classList.add('hidden'));
});
</script>

<?php require_once 'footer.php'; ?>