<?php
// reports_team.php (Final CRM Version)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'db.php';

$allowed_roles = ['sales_head', 'supervisor', 'manager', 'owner'];
$current_user_role = $_SESSION['user_role'];
$current_user_id = $_SESSION['user_id'];
if (!in_array($current_user_role, $allowed_roles)) {
    die("Access Denied.");
}

$view_user_id = isset($_GET['view_id']) ? (int)$_GET['view_id'] : $current_user_id;

function getAllSubordinateIds($pdo, $manager_id) {
    $ids_to_check = [$manager_id];
    $all_subordinate_ids = [];
    while (!empty($ids_to_check)) {
        $placeholders = rtrim(str_repeat('?,', count($ids_to_check)), ',');
        $stmt = $pdo->prepare("SELECT id FROM users WHERE reports_to IN ($placeholders)");
        $stmt->execute($ids_to_check);
        $subordinates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($subordinates)) {
            $all_subordinate_ids = array_merge($all_subordinate_ids, $subordinates);
            $ids_to_check = $subordinates;
        } else { $ids_to_check = []; }
    }
    return $all_subordinate_ids;
}

try {
    $view_user_stmt = $pdo->prepare("SELECT u.id, u.full_name, u.role, u.reports_to, p.full_name as parent_name FROM users u LEFT JOIN users p ON u.reports_to = p.id WHERE u.id = ?");
    $view_user_stmt->execute([$view_user_id]);
    $view_user = $view_user_stmt->fetch();
    if (!$view_user) { die("User not found."); }

    $breadcrumbs = [];
    $current_breadcrumb_id = $view_user_id;
    while ($current_breadcrumb_id != null && $current_breadcrumb_id != $current_user_id) {
        $crumb_stmt = $pdo->prepare("SELECT id, full_name, reports_to FROM users WHERE id = ?");
        $crumb_stmt->execute([$current_breadcrumb_id]);
        $crumb = $crumb_stmt->fetch();
        if ($crumb) {
            array_unshift($breadcrumbs, $crumb);
            $current_breadcrumb_id = $crumb['reports_to'];
        } else { $current_breadcrumb_id = null; }
    }

    $display_mode = 'team';
    $page_data = [];

    if ($view_user['role'] === 'sales') {
        $display_mode = 'invoices';
        // UPDATED QUERY: Joins with customers, gets payment status
        $invoice_stmt = $pdo->prepare(
            "SELECT 
                c.name as customer_name, 
                DATE(o.order_date) as invoice_date, 
                SUM(o.total_amount) as invoice_total,
                MIN(o.payment_status) as overall_payment_status, -- 'Due' comes before 'Paid' alphabetically, so MIN works perfectly
                GROUP_CONCAT(CONCAT_WS('||', p.name, o.total_pcs, o.total_amount) SEPARATOR ';;') as product_details
             FROM orders o 
             JOIN products p ON o.product_id = p.id
             JOIN customers c ON o.customer_id = c.id
             WHERE o.user_id = ?
             GROUP BY c.name, DATE(o.order_date) 
             ORDER BY invoice_date DESC"
        );
        $invoice_stmt->execute([$view_user_id]);
        $page_data = $invoice_stmt->fetchAll();
    } else {
        $subordinates_stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE reports_to = ? ORDER BY full_name ASC");
        $subordinates_stmt->execute([$view_user_id]);
        $subordinates = $subordinates_stmt->fetchAll();

        foreach ($subordinates as $sub) {
            $team_ids = array_merge([$sub['id']], getAllSubordinateIds($pdo, $sub['id']));
            if (empty($team_ids)) {
                $stats = ['total_sales' => 0, 'total_profit' => 0];
            } else {
                $placeholders = rtrim(str_repeat('?,', count($team_ids)), ',');
                $stats_stmt = $pdo->prepare("SELECT SUM(total_amount) as total_sales, SUM(total_amount - (total_pcs * cost_per_pc_at_sale)) as total_profit FROM orders WHERE user_id IN ($placeholders)");
                $stats_stmt->execute($team_ids);
                $stats = $stats_stmt->fetch();
            }
            $page_data[] = ['id' => $sub['id'], 'full_name' => $sub['full_name'], 'role' => $sub['role'], 'total_sales' => $stats['total_sales'] ?? 0, 'total_profit' => $stats['total_profit'] ?? 0];
        }
    }
} catch (PDOException $e) {
    die("A database error occurred: " . $e->getMessage());
}

$page_title = 'Team Reports';
require_once 'header.php';
?>

<div class="bg-white p-4 sm:p-6 lg:p-8 rounded-xl shadow-lg max-w-7xl mx-auto space-y-6">
    <nav class="flex" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li><a href="reports_team.php" class="text-sm font-medium text-gray-700 hover:text-indigo-600">Team Reports</a></li>
            <?php foreach ($breadcrumbs as $crumb): ?>
                <li><div class="flex items-center"><span class="mx-2 text-gray-400">/</span><a href="reports_team.php?view_id=<?php echo $crumb['id']; ?>" class="text-sm font-medium text-gray-700 hover:text-indigo-600"><?php echo htmlspecialchars($crumb['full_name']); ?></a></div></li>
            <?php endforeach; ?>
        </ol>
    </nav>
    <div class="border-b pb-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                 <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Viewing Team of: <span class="text-indigo-600"><?php echo htmlspecialchars($view_user['full_name']); ?></span></h1>
                <p class="text-sm text-gray-500">Role: <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($view_user['role']))); ?></p>
            </div>
            <?php if ($view_user_id == $current_user_id): ?>
                <a href="dashboard.php" class="mt-2 sm:mt-0 inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-indigo-600 bg-gray-100 hover:bg-gray-200 py-2 px-4 rounded-md transition"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3" /></svg>Back to Dashboard</a>
            <?php elseif (!empty($view_user['reports_to'])): ?>
                <a href="reports_team.php?view_id=<?php echo $view_user['reports_to']; ?>" class="mt-2 sm:mt-0 inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-indigo-600 bg-gray-100 hover:bg-gray-200 py-2 px-4 rounded-md transition"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>Back to <?php echo htmlspecialchars($view_user['parent_name'] ?? 'Previous Team'); ?>'s Team</a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($display_mode === 'team'): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Team Member</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Sales</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Profit</th></tr></thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($page_data)): ?><tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">This user has no subordinates.</td></tr>
                    <?php else: ?><?php foreach ($page_data as $member): ?><tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-600"><a href="reports_team.php?view_id=<?php echo $member['id']; ?>" class="hover:underline"><?php echo htmlspecialchars($member['full_name']); ?></a></td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($member['role']))); ?></td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-semibold">৳<?php echo number_format($member['total_sales'], 2); ?></td><td class="px-6 py-4 whitespace-nowrap text-sm text-green-700 text-right font-bold">৳<?php echo number_format($member['total_profit'], 2); ?></td></tr><?php endforeach; ?><?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($display_mode === 'invoices'): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice Date</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Invoice Total</th><th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Payment Status</th><th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th></tr></thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($page_data)): ?><tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">This salesperson has not created any invoices.</td></tr>
                    <?php else: ?><?php foreach ($page_data as $invoice): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars(date('M d, Y', strtotime($invoice['invoice_date']))); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 font-medium"><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-700 text-right font-bold">৳<?php echo number_format($invoice['invoice_total'], 2); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php $status_color = $invoice['overall_payment_status'] === 'Paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_color; ?>"><?php echo htmlspecialchars($invoice['overall_payment_status']); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center"><button type="button" class="view-details-btn text-indigo-600 hover:text-indigo-900 font-medium" data-customer="<?php echo htmlspecialchars($invoice['customer_name']); ?>" data-date="<?php echo htmlspecialchars(date('M d, Y', strtotime($invoice['invoice_date']))); ?>" data-total="৳<?php echo number_format($invoice['invoice_total'], 2); ?>" data-products="<?php echo htmlspecialchars($invoice['product_details']); ?>">View Details</button></td>
                        </tr>
                    <?php endforeach; ?><?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<div id="invoice-modal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-75 z-50 flex items-center justify-center p-4"><div class="relative bg-white rounded-lg shadow-xl p-6 sm:p-8 w-full max-w-2xl mx-auto"><button id="modal-close-btn" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">&times;</button><h3 class="text-xl leading-6 font-bold text-gray-900 mb-2">Invoice Details</h3><div class="text-sm text-gray-600 mb-4 border-b pb-2 space-y-1"><p><strong>Customer:</strong> <span id="modal-customer"></span></p><p><strong>Date:</strong> <span id="modal-date"></span></p></div><div class="overflow-y-auto max-h-80"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Quantity (Packets)</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th></tr></thead><tbody id="modal-products-body" class="bg-white divide-y divide-gray-200"></tbody></table></div><div class="mt-4 pt-4 border-t flex justify-end items-center"><span class="text-lg font-bold text-gray-800">Total: <span id="modal-total" class="text-green-700"></span></span></div></div></div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('invoice-modal'); const closeBtn = document.getElementById('modal-close-btn');
    document.body.addEventListener('click', function(e) {
        if (e.target.classList.contains('view-details-btn')) {
            const button = e.target;
            document.getElementById('modal-customer').textContent = button.dataset.customer;
            document.getElementById('modal-date').textContent = button.dataset.date;
            document.getElementById('modal-total').textContent = button.dataset.total;
            const modalProductsBody = document.getElementById('modal-products-body'); modalProductsBody.innerHTML = '';
            const productsString = button.dataset.products;
            if (productsString) {
                productsString.split(';;').forEach(item => {
                    const [productName, quantity, amount] = item.split('||');
                    const row = modalProductsBody.insertRow();
                    row.innerHTML = `<td class="px-4 py-3 text-sm text-gray-800">${productName}</td><td class="px-4 py-3 text-sm text-gray-600 text-right">${parseInt(quantity).toLocaleString()}</td><td class="px-4 py-3 text-sm text-right font-semibold">৳${parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>`;
                });
            }
            modal.classList.remove('hidden');
        }
    });
    const closeModal = () => modal.classList.add('hidden');
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
});
</script>
<?php
require_once 'footer.php';
?>