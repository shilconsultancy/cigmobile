<?php
// orders_team.php (FINAL CORRECTED Version)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'db.php';

// --- Authorization Check ---
$allowed_roles = ['sales_head', 'supervisor', 'manager', 'owner'];
if (!in_array($_SESSION['user_role'], $allowed_roles)) {
    die("Access Denied. You do not have permission to view this page.");
}

// Initialize variables
$teams_data = [];
// --- THIS IS THE FIX ---
// The variable is now consistently named $current_user_id throughout the script.
$current_user_id = $_SESSION['user_id'];
// ----------------------
$current_user_role = $_SESSION['user_role'] ?? 'guest';
$current_user_is_leader = in_array($current_user_role, ['manager', 'supervisor', 'sales_head', 'owner']);

try {
    // --- Step 1: Identify all team leaders who report to the current user ---
    $stmt = $pdo->prepare(
        "SELECT id, full_name, role FROM users 
         WHERE reports_to = ? AND role IN ('manager', 'supervisor', 'sales_head')
         ORDER BY full_name ASC"
    );
    $stmt->execute([$current_user_id]);
    $team_leaders = $stmt->fetchAll();

    // The owner is the top-level team leader.
    // Other managers/supervisors are also leaders of their own direct team.
    if ($current_user_is_leader) {
        array_unshift($team_leaders, ['id' => $current_user_id, 'full_name' => $_SESSION['user_full_name'], 'role' => $_SESSION['user_role']]);
    }
    
    // --- Step 2: For each leader, find their team and fetch their invoices ---
    foreach ($team_leaders as $leader) {
        // Find all subordinates under this leader (recursively)
        $ids_to_check = [$leader['id']];
        $team_member_ids = [];
        if ($leader['role'] !== 'owner') {
             $team_member_ids[] = $leader['id'];
        }
       
        while (!empty($ids_to_check)) {
            $placeholders = rtrim(str_repeat('?,', count($ids_to_check)), ',');
            $sub_stmt = $pdo->prepare("SELECT id FROM users WHERE reports_to IN ($placeholders)");
            $sub_stmt->execute($ids_to_check);
            $subordinates = $sub_stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($subordinates)) {
                $team_member_ids = array_merge($team_member_ids, $subordinates);
                $ids_to_check = $subordinates;
            } else {
                $ids_to_check = [];
            }
        }

        // For the owner, we get all users as there is no one above them to fetch.
        if ($current_user_role === 'owner' && $leader['id'] === $current_user_id) {
             $team_member_ids = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
        }

        // --- Step 3: Fetch INVOICES for this team using a corrected and more stable query ---
        if (!empty($team_member_ids)) {
            $placeholders = rtrim(str_repeat('?,', count($team_member_ids)), ',');
            
            $invoice_stmt = $pdo->prepare(
                "SELECT
                    o.customer_name,
                    DATE(o.order_date) as invoice_date,
                    SUM(o.total_amount) as invoice_total,
                    SUM(o.total_pcs) as invoice_total_packets,
                    GROUP_CONCAT(DISTINCT u.full_name SEPARATOR ', ') as salesperson_names,
                    GROUP_CONCAT(CONCAT_WS('||', p.name, o.total_pcs, o.total_amount) SEPARATOR ';;') as product_details
                 FROM orders o
                 JOIN products p ON o.product_id = p.id
                 JOIN users u ON o.user_id = u.id
                 WHERE o.user_id IN ($placeholders)
                 GROUP BY o.customer_name, DATE(o.order_date)
                 ORDER BY invoice_date DESC"
            );
            $invoice_stmt->execute($team_member_ids);
            $invoices = $invoice_stmt->fetchAll();
            
            $teams_data[$leader['full_name']] = $invoices;
        }
    }

} catch (PDOException $e) {
    error_log("Team Orders Page Error: " . $e->getMessage());
    die("A database error occurred. Please check the logs for details.");
}

$page_title = 'Team Invoices';
require_once 'header.php';
?>

<div class="bg-white p-4 sm:p-6 lg:p-8 rounded-xl shadow-lg max-w-7xl mx-auto space-y-8">
    <div class="flex items-center justify-between mb-6 border-b pb-4">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Team Invoice History</h1>
        <a href="dashboard.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to Dashboard</a>
    </div>

    <?php if (empty($teams_data)): ?>
        <div class="text-center py-12 text-gray-500"><p>No teams or team orders found reporting to you.</p></div>
    <?php else: ?>
        <?php foreach ($teams_data as $team_leader_name => $invoices): ?>
            <div class="border rounded-lg p-4 mb-6">
                <h2 class="text-xl font-bold text-indigo-700 mb-4">Team: <?php echo htmlspecialchars($team_leader_name); ?></h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Salespersons</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Invoice Total</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($invoices)): ?>
                                <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">No invoices found for this team.</td></tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars(date('M d, Y', strtotime($invoice['invoice_date']))); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 font-medium"><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($invoice['salesperson_names']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-700 text-right font-bold">৳<?php echo number_format($invoice['invoice_total'], 2); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <button type="button" class="view-details-btn text-indigo-600 hover:text-indigo-900 font-medium"
                                                    data-customer="<?php echo htmlspecialchars($invoice['customer_name']); ?>"
                                                    data-date="<?php echo htmlspecialchars(date('M d, Y', strtotime($invoice['invoice_date']))); ?>"
                                                    data-salespersons="<?php echo htmlspecialchars($invoice['salesperson_names']); ?>"
                                                    data-total="৳<?php echo number_format($invoice['invoice_total'], 2); ?>"
                                                    data-products="<?php echo htmlspecialchars($invoice['product_details']); ?>">
                                                View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Invoice Details Modal -->
<div id="invoice-modal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-75 z-50 flex items-center justify-center p-4">
    <div class="relative bg-white rounded-lg shadow-xl p-6 sm:p-8 w-full max-w-2xl mx-auto">
        <button id="modal-close-btn" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
        <h3 class="text-xl leading-6 font-bold text-gray-900 mb-2">Invoice Details</h3>
        <div class="text-sm text-gray-600 mb-4 border-b pb-2 space-y-1">
            <p><strong>Customer:</strong> <span id="modal-customer"></span></p>
            <p><strong>Date:</strong> <span id="modal-date"></span></p>
            <p><strong>Handled By:</strong> <span id="modal-salespersons"></span></p>
        </div>
        <div class="overflow-y-auto max-h-80">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Quantity (Packets)</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th></tr></thead>
                <tbody id="modal-products-body" class="bg-white divide-y divide-gray-200"></tbody>
            </table>
        </div>
        <div class="mt-4 pt-4 border-t flex justify-end items-center">
            <span class="text-lg font-bold text-gray-800">Total: <span id="modal-total" class="text-green-700"></span></span>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('invoice-modal');
    const closeBtn = document.getElementById('modal-close-btn');
    const modalCustomer = document.getElementById('modal-customer');
    const modalDate = document.getElementById('modal-date');
    const modalSalespersons = document.getElementById('modal-salespersons');
    const modalTotal = document.getElementById('modal-total');
    const modalProductsBody = document.getElementById('modal-products-body');
    
    document.body.addEventListener('click', function(e) {
        if (e.target.classList.contains('view-details-btn')) {
            const button = e.target;
            modalCustomer.textContent = button.dataset.customer;
            modalDate.textContent = button.dataset.date;
            modalSalespersons.textContent = button.dataset.salespersons;
            modalTotal.textContent = button.dataset.total;
            modalProductsBody.innerHTML = '';
            
            const productsString = button.dataset.products;
            if (productsString) {
                const productItems = productsString.split(';;');
                productItems.forEach(item => {
                    const parts = item.split('||');
                    const productName = parts[0];
                    const quantity = parseInt(parts[1]).toLocaleString();
                    const amount = parseFloat(parts[2]).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    const row = document.createElement('tr');
                    row.innerHTML = `<td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800">${productName}</td><td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 text-right">${quantity}</td><td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 text-right font-semibold">৳${amount}</td>`;
                    modalProductsBody.appendChild(row);
                });
            }
            modal.classList.remove('hidden');
        }
    });
    
    function closeModal() { modal.classList.add('hidden'); }
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) { if (e.target === modal) { closeModal(); } });
});
</script>

<?php
require_once 'footer.php';
?>