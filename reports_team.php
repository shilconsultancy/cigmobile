<?php
// reports_team.php (FINAL - Comprehensive Hierarchical Performance Report)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'db.php';

// --- Authorization Check ---
$allowed_roles = ['sales_head', 'supervisor', 'manager', 'owner'];
$current_user_role = $_SESSION['user_role'];
$current_user_id = $_SESSION['user_id'];
if (!in_array($current_user_role, $allowed_roles)) {
    die("Access Denied.");
}

// --- CORE LOGIC: Determine whose team we are viewing ---
$view_user_id = isset($_GET['view_id']) ? (int)$_GET['view_id'] : $current_user_id;

// --- Helper function to get all subordinate IDs recursively ---
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
        } else {
            $ids_to_check = [];
        }
    }
    return $all_subordinate_ids;
}


try {
    // 1. Fetch details of the user being viewed AND their manager's name for the back button.
    $view_user_stmt = $pdo->prepare(
        "SELECT u.id, u.full_name, u.role, u.reports_to, p.full_name as parent_name
         FROM users u
         LEFT JOIN users p ON u.reports_to = p.id
         WHERE u.id = ?"
    );
    $view_user_stmt->execute([$view_user_id]);
    $view_user = $view_user_stmt->fetch();
    if (!$view_user) { die("User not found."); }

    // 2. Build the breadcrumb trail
    $breadcrumbs = [];
    $current_breadcrumb_id = $view_user_id;
    while ($current_breadcrumb_id != null && $current_breadcrumb_id != $current_user_id) {
        $crumb_stmt = $pdo->prepare("SELECT id, full_name, reports_to FROM users WHERE id = ?");
        $crumb_stmt->execute([$current_breadcrumb_id]);
        $crumb = $crumb_stmt->fetch();
        if ($crumb) {
            array_unshift($breadcrumbs, $crumb);
            $current_breadcrumb_id = $crumb['reports_to'];
        } else {
            $current_breadcrumb_id = null;
        }
    }

    // 3. Determine what to display based on the role of the user we are viewing
    $display_mode = 'team';
    $page_data = [];

    if ($view_user['role'] === 'sales') {
        $display_mode = 'invoices';
        $invoice_stmt = $pdo->prepare(
            "SELECT 
                o.invoice_id, c.id as customer_id, c.name as customer_name, MIN(o.order_date) as invoice_date, 
                SUM(o.total_amount) as invoice_total, MIN(o.payment_status) as overall_payment_status
             FROM orders o 
             JOIN customers c ON o.customer_id = c.id
             WHERE o.user_id = ? 
             GROUP BY o.invoice_id, c.id, c.name 
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
                $stats_stmt = $pdo->prepare(
                    "SELECT SUM(total_amount) as total_sales,
                     SUM(total_amount - (total_pcs * cost_per_pc_at_sale)) as total_profit
                     FROM orders WHERE user_id IN ($placeholders)"
                );
                $stats_stmt->execute($team_ids);
                $stats = $stats_stmt->fetch();
            }
            
            $page_data[] = [
                'id' => $sub['id'], 'full_name' => $sub['full_name'], 'role' => $sub['role'],
                'total_sales' => $stats['total_sales'] ?? 0, 'total_profit' => $stats['total_profit'] ?? 0
            ];
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
                 <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">
                    Viewing Team of: <span class="text-indigo-600"><?php echo htmlspecialchars($view_user['full_name']); ?></span>
                </h1>
                <p class="text-sm text-gray-500">Role: <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($view_user['role']))); ?></p>
            </div>
            <?php if ($view_user_id == $current_user_id): ?>
                <a href="dashboard.php" class="mt-2 sm:mt-0 inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-indigo-600 bg-gray-100 hover:bg-gray-200 py-2 px-4 rounded-md transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3" /></svg>
                    Back to Dashboard
                </a>
            <?php elseif (!empty($view_user['reports_to'])): ?>
                <a href="reports_team.php?view_id=<?php echo $view_user['reports_to']; ?>" class="mt-2 sm:mt-0 inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-indigo-600 bg-gray-100 hover:bg-gray-200 py-2 px-4 rounded-md transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    Back to <?php echo htmlspecialchars($view_user['parent_name'] ?? 'Previous Team'); ?>'s Team
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($display_mode === 'team'): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Team Member</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Sales</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Profit</th></tr></thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($page_data)): ?>
                        <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">This user has no subordinates.</td></tr>
                    <?php else: ?>
                        <?php foreach ($page_data as $member): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-600"><a href="reports_team.php?view_id=<?php echo $member['id']; ?>" class="hover:underline"><?php echo htmlspecialchars($member['full_name']); ?></a></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($member['role']))); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-semibold">৳<?php echo number_format($member['total_sales'], 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-700 text-right font-bold">৳<?php echo number_format($member['total_profit'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($display_mode === 'invoices'): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th><th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th></tr></thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($page_data)): ?>
                        <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">This salesperson has not created any invoices.</td></tr>
                    <?php else: ?>
                        <?php foreach ($page_data as $invoice): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-600"><a href="invoice_view.php?id=<?php echo $invoice['invoice_id']; ?>" class="hover:underline">#<?php echo htmlspecialchars($invoice['invoice_id']); ?></a></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 font-medium"><a href="customer_profile.php?id=<?php echo $invoice['customer_id']; ?>" class="text-indigo-600 hover:underline"><?php echo htmlspecialchars($invoice['customer_name']); ?></a></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-700 text-right font-bold">৳<?php echo number_format($invoice['invoice_total'], 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                     <?php $status_color = $invoice['overall_payment_status'] === 'Paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>
                                     <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_color; ?>"><?php echo htmlspecialchars($invoice['overall_payment_status']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>