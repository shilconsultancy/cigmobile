<?php
// invoice_view.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'db.php';

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($invoice_id === 0) {
    die("No invoice specified.");
}

try {
    // Fetch all order lines for this invoice
    $stmt = $pdo->prepare(
        "SELECT 
            o.order_date, o.payment_status, o.due_date,
            c.id as customer_id, c.name as customer_name, c.phone as customer_phone,
            u.id as user_id, u.full_name as salesperson_name,
            p.name as product_name,
            o.total_pcs,
            (o.total_amount / o.total_pcs) as price_per_packet,
            o.total_amount
         FROM orders o
         JOIN customers c ON o.customer_id = c.id
         JOIN users u ON o.user_id = u.id
         JOIN products p ON o.product_id = p.id
         WHERE o.invoice_id = ?"
    );
    $stmt->execute([$invoice_id]);
    $order_lines = $stmt->fetchAll();

    if (empty($order_lines)) {
        die("Invoice not found.");
    }
    
    // Extract common details from the first line
    $invoice_details = $order_lines[0];
    $grand_total = array_sum(array_column($order_lines, 'total_amount'));

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$page_title = 'View Invoice #' . $invoice_id;
require_once 'header.php';
?>

<div class="bg-white p-4 sm:p-6 lg:p-8 rounded-xl shadow-lg max-w-4xl mx-auto space-y-6">
    <div class="text-center">
        <h1 class="text-3xl font-bold text-gray-800">INVOICE</h1>
        <p class="text-gray-500">Invoice Number: <span class="font-medium text-gray-800">#<?php echo htmlspecialchars($invoice_id); ?></span></p>
    </div>

    <div class="grid grid-cols-2 gap-6 border-t border-b py-4">
        <div>
            <h3 class="text-sm font-semibold text-gray-500 uppercase">BILLED TO</h3>
            <a href="customer_profile.php?id=<?php echo $invoice_details['customer_id']; ?>" class="text-lg font-bold text-indigo-600 hover:underline"><?php echo htmlspecialchars($invoice_details['customer_name']); ?></a>
            <p class="text-gray-600"><?php echo htmlspecialchars($invoice_details['customer_phone'] ?? ''); ?></p>
        </div>
        <div class="text-right">
            <h3 class="text-sm font-semibold text-gray-500 uppercase">INVOICE DETAILS</h3>
            <p class="text-gray-600"><strong>Date:</strong> <?php echo date('M d, Y', strtotime($invoice_details['order_date'])); ?></p>
            <p class="text-gray-600"><strong>Handled By:</strong> <a href="user_profile.php?id=<?php echo $invoice_details['user_id']; ?>" class="text-indigo-600 hover:underline"><?php echo htmlspecialchars($invoice_details['salesperson_name']); ?></a></p>
        </div>
    </div>

    <div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Particulars</h3>
        <div class="overflow-x-auto border rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Quantity (Packets)</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Price/Packet</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Sub-total</th></tr></thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($order_lines as $line): ?>
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($line['product_name']); ?></td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 text-right"><?php echo number_format($line['total_pcs']); ?></td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 text-right">৳<?php echo number_format($line['price_per_packet'], 2); ?></td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 text-right font-semibold">৳<?php echo number_format($line['total_amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="flex justify-between items-start pt-4 border-t">
        <div>
            <h3 class="text-sm font-semibold text-gray-500 uppercase">PAYMENT STATUS</h3>
            <?php $status_color = $invoice_details['payment_status'] === 'Paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>
            <p class="px-3 py-1 mt-1 inline-flex text-base leading-5 font-bold rounded-full <?php echo $status_color; ?>">
                <?php echo htmlspecialchars($invoice_details['payment_status']); ?>
            </p>
            <?php if ($invoice_details['payment_status'] === 'Due'): ?>
            <p class="text-xs text-red-600 mt-1">Due Date: <?php echo date('M d, Y', strtotime($invoice_details['due_date'])); ?></p>
            <?php endif; ?>
        </div>
        <div class="text-right">
            <h3 class="text-sm font-semibold text-gray-500 uppercase">GRAND TOTAL</h3>
            <p class="text-3xl font-bold text-green-700">৳<?php echo number_format($grand_total, 2); ?></p>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>