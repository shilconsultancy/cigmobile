<?php
// reports_due.php (FINAL - Comprehensive Hierarchical Due Payments Report with Personal Drill-Down)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'db.php';

$current_user_role = $_SESSION['user_role'];
$current_user_id = $_SESSION['user_id'];

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_invoice_paid'])) {
    $invoice_id_to_update = (int)$_POST['invoice_id'];
    if (!empty($invoice_id_to_update)) {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'Paid', due_date = NULL WHERE invoice_id = ?");
            $stmt->execute([$invoice_id_to_update]);
            $success_message = "Invoice #" . $invoice_id_to_update . " has been marked as Paid.";
        } catch (PDOException $e) {
            $error_message = "Database error: Could not update invoice status.";
        }
    }
}

$view_user_id = isset($_GET['view_user_id']) ? (int)$_GET['view_user_id'] : $current_user_id;
$view_customer_id = isset($_GET['view_customer_id']) ? (int)$_GET['view_customer_id'] : null;
$view_personal_dues = isset($_GET['personal_dues']);

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

    $display_mode = 'team'; 
    $page_data = [];
    $personal_dues = 0;
    $customer_name = '';

    $personal_due_stmt = $pdo->prepare("SELECT SUM(total_amount) as total FROM orders WHERE user_id = ? AND payment_status = 'Due'");
    $personal_due_stmt->execute([$view_user_id]);
    $personal_dues = $personal_due_stmt->fetchColumn() ?: 0;
    
    if ($view_customer_id) {
        $display_mode = 'customer_details';
        $cust_stmt = $pdo->prepare("SELECT name FROM customers WHERE id = ?"); $cust_stmt->execute([$view_customer_id]);
        $customer_name = $cust_stmt->fetchColumn();
        $details_stmt = $pdo->prepare(
            "SELECT invoice_id, MIN(order_date) as invoice_date, SUM(total_amount) as invoice_total
             FROM orders WHERE user_id = ? AND customer_id = ? AND payment_status = 'Due'
             GROUP BY invoice_id ORDER BY invoice_date ASC"
        );
        $details_stmt->execute([$view_user_id, $view_customer_id]);
        $page_data = $details_stmt->fetchAll();

    } elseif ($view_personal_dues) {
        $display_mode = 'personal_customers';
        $personal_cust_stmt = $pdo->prepare("SELECT c.id, c.name, SUM(o.total_amount) as total_due FROM orders o JOIN customers c ON o.customer_id = c.id WHERE o.user_id = ? AND o.payment_status = 'Due' GROUP BY c.id, c.name ORDER BY total_due DESC");
        $personal_cust_stmt->execute([$view_user_id]);
        $page_data = $personal_cust_stmt->fetchAll();

    } elseif ($view_user['role'] === 'sales') {
        $display_mode = 'salesperson_customers';
        $sales_cust_stmt = $pdo->prepare("SELECT c.id, c.name, SUM(o.total_amount) as total_due FROM orders o JOIN customers c ON o.customer_id = c.id WHERE o.user_id = ? AND o.payment_status = 'Due' GROUP BY c.id, c.name ORDER BY total_due DESC");
        $sales_cust_stmt->execute([$view_user_id]);
        $page_data = $sales_cust_stmt->fetchAll();

    } else {
        $display_mode = 'team';
        $subordinates_stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE reports_to = ? ORDER BY full_name ASC");
        $subordinates_stmt->execute([$view_user_id]);
        $subordinates = $subordinates_stmt->fetchAll();
        foreach ($subordinates as $sub) {
            $team_ids = array_merge([$sub['id']], getAllSubordinateIds($pdo, $sub['id']));
            if(empty($team_ids)) continue;
            $placeholders = rtrim(str_repeat('?,', count($team_ids)), ',');
            $stats_stmt = $pdo->prepare("SELECT SUM(total_amount) as total_due FROM orders WHERE user_id IN ($placeholders) AND payment_status = 'Due'");
            $stats_stmt->execute($team_ids);
            $stats = $stats_stmt->fetch();
            $page_data[] = ['id' => $sub['id'], 'full_name' => $sub['full_name'], 'role' => $sub['role'], 'total_due' => $stats['total_due'] ?? 0];
        }
    }
} catch (PDOException $e) {
    die("A database error occurred: " . $e->getMessage());
}

$page_title = 'Due Payments Report';
require_once 'header.php';
?>

<div class="bg-white p-4 sm:p-6 lg:p-8 rounded-xl shadow-lg max-w-7xl mx-auto space-y-6">
    <div class="border-b pb-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div><h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Due Payments Report</h1><p class="text-sm text-gray-500">Drill down to view outstanding payments.</p></div>
            <?php if ($view_user_id == $current_user_id && !$view_personal_dues): ?>
                <a href="dashboard.php" class="mt-2 sm:mt-0 inline-flex items-center gap-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 py-2 px-4 rounded-md">Back to Dashboard</a>
            <?php elseif ($view_personal_dues): ?>
                 <a href="reports_due.php?view_user_id=<?php echo $view_user_id; ?>" class="mt-2 sm:mt-0 inline-flex items-center gap-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 py-2 px-4 rounded-md">Back to Main Report for <?php echo htmlspecialchars($view_user['full_name']); ?></a>
            <?php elseif (!empty($view_user['reports_to'])): ?>
                <a href="reports_due.php?view_user_id=<?php echo $view_user['reports_to']; ?>" class="mt-2 sm:mt-0 inline-flex items-center gap-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 py-2 px-4 rounded-md">Back to <?php echo htmlspecialchars($view_user['parent_name'] ?? 'Previous Level'); ?>'s Team</a>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($success_message)): ?>
    <div class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md" role="alert"><p class="font-bold">Success</p><p><?php echo htmlspecialchars($success_message); ?></p></div>
    <?php endif; ?>

    <?php if ($display_mode === 'team'): ?>
        <h2 class="text-xl font-semibold text-gray-800">Viewing Report for: <span class="text-indigo-600"><?php echo htmlspecialchars($view_user['full_name']); ?></span></h2>
        <a href="reports_due.php?view_user_id=<?php echo $view_user_id; ?>&personal_dues=1" class="block border-l-4 border-yellow-400 bg-yellow-50 p-4 rounded-md hover:bg-yellow-100 transition">
            <div class="flex items-center justify-between"><p class="font-semibold text-yellow-800">Personal Dues (Sales made by <?php echo htmlspecialchars($view_user['full_name']); ?>)</p><p class="text-xl font-bold text-red-700">৳<?php echo number_format($personal_dues, 2); ?></p></div>
        </a>
        <h3 class="text-lg font-semibold text-gray-700 pt-4">Subordinate Team Due Summary</h3>
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Team Member / Subordinate</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Their Team's Total Due</th></tr></thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($page_data)): ?><tr><td colspan="2" class="px-6 py-12 text-center text-gray-500">No subordinates with due payments found.</td></tr>
                <?php else: ?><?php foreach ($page_data as $member): ?><tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-600"><a href="reports_due.php?view_user_id=<?php echo $member['id']; ?>" class="hover:underline"><?php echo htmlspecialchars($member['full_name']); ?></a></td><td class="px-6 py-4 whitespace-nowrap text-sm text-red-700 text-right font-bold">৳<?php echo number_format($member['total_due'], 2); ?></td></tr><?php endforeach; ?><?php endif; ?>
            </tbody>
        </table></div>

    <?php elseif ($display_mode === 'salesperson_customers' || $display_mode === 'personal_customers'): ?>
        <h2 class="text-xl font-semibold text-gray-800">Due Customers for: <span class="text-indigo-600"><?php echo htmlspecialchars($view_user['full_name']); ?></span></h2>
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Due</th></tr></thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($page_data)): ?><tr><td colspan="2" class="px-6 py-12 text-center text-gray-500">No customers with due payments found.</td></tr>
                <?php else: ?><?php foreach ($page_data as $customer): ?><tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-600"><a href="reports_due.php?view_user_id=<?php echo $view_user_id; ?>&view_customer_id=<?php echo $customer['id']; ?>" class="hover:underline"><?php echo htmlspecialchars($customer['name']); ?></a></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-red-700 text-right font-bold">৳<?php echo number_format($customer['total_due'], 2); ?></td>
                </tr><?php endforeach; ?><?php endif; ?>
            </tbody>
        </table></div>

    <?php elseif ($display_mode === 'customer_details'): ?>
        <h2 class="text-xl font-semibold text-gray-800">Due Invoices for <span class="text-indigo-600"><?php echo htmlspecialchars($customer_name); ?></span></h2><p class="text-sm text-gray-500">Handled by: <?php echo htmlspecialchars($view_user['full_name']); ?></p>
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice Date</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount Due</th><th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Action</th></tr></thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($page_data)): ?><tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">No due invoices found.</td></tr>
                <?php else: ?><?php foreach ($page_data as $invoice): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-600"><a href="invoice_view.php?id=<?php echo $invoice['invoice_id']; ?>" class="hover:underline">#<?php echo htmlspecialchars($invoice['invoice_id']); ?></a></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars(date('M d, Y', strtotime($invoice['invoice_date']))); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-700 text-right font-bold">৳<?php echo number_format($invoice['invoice_total'], 2); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                            <form action="reports_due.php?view_user_id=<?php echo $view_user_id; ?>&view_customer_id=<?php echo $view_customer_id; ?>" method="POST">
                                <input type="hidden" name="invoice_id" value="<?php echo $invoice['invoice_id']; ?>">
                                <button type="submit" name="mark_invoice_paid" class="font-medium text-green-600 hover:text-green-900">Mark as Paid</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?><?php endif; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>

<?php
require_once 'footer.php';
?>