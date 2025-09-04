<?php
// reports_due.php (Hierarchical Due Payments Report)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'db.php';

$current_user_role = $_SESSION['user_role'];
$current_user_id = $_SESSION['user_id'];

$error_message = '';
$success_message = '';

// --- Handle "Mark as Paid" form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_paid'])) {
    $order_id_to_update = (int)$_POST['order_id'];
    if (!empty($order_id_to_update)) {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'Paid', due_date = NULL WHERE id = ?");
            $stmt->execute([$order_id_to_update]);
            $success_message = "Order #" . $order_id_to_update . " has been marked as Paid.";
        } catch (PDOException $e) {
            $error_message = "Database error: Could not update order status.";
        }
    }
}

// --- CORE LOGIC: Determine the view based on URL parameters ---
$view_user_id = isset($_GET['view_user_id']) ? (int)$_GET['view_user_id'] : $current_user_id;
$view_customer_id = isset($_GET['view_customer_id']) ? (int)$_GET['view_customer_id'] : null;

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
    // 1. Fetch details of the user whose dues we are currently viewing
    $view_user_stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE id = ?");
    $view_user_stmt->execute([$view_user_id]);
    $view_user = $view_user_stmt->fetch();
    if (!$view_user) { die("User not found."); }

    // Initialize display variables
    $display_mode = 'team'; // Default is to show team members
    $page_data = [];
    $customer_name = '';

    if ($view_customer_id) {
        // --- Detail View: Show specific due orders for one customer from one salesperson ---
        $display_mode = 'customer_details';
        $cust_stmt = $pdo->prepare("SELECT name FROM customers WHERE id = ?");
        $cust_stmt->execute([$view_customer_id]);
        $customer_name = $cust_stmt->fetchColumn();

        $details_stmt = $pdo->prepare(
            "SELECT o.id, o.order_date, p.name as product_name, o.total_amount
             FROM orders o
             JOIN products p ON o.product_id = p.id
             WHERE o.user_id = ? AND o.customer_id = ? AND o.payment_status = 'Due'
             ORDER BY o.order_date ASC"
        );
        $details_stmt->execute([$view_user_id, $view_customer_id]);
        $page_data = $details_stmt->fetchAll();

    } elseif ($view_user['role'] === 'sales') {
        // --- Salesperson View: Show all customers with dues under this salesperson ---
        $display_mode = 'salesperson_customers';
        $sales_cust_stmt = $pdo->prepare(
            "SELECT c.id, c.name, SUM(o.total_amount) as total_due
             FROM orders o
             JOIN customers c ON o.customer_id = c.id
             WHERE o.user_id = ? AND o.payment_status = 'Due'
             GROUP BY c.id, c.name
             ORDER BY total_due DESC"
        );
        $sales_cust_stmt->execute([$view_user_id]);
        $page_data = $sales_cust_stmt->fetchAll();

    } else {
        // --- Manager/Owner View: Show subordinates and their total dues ---
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
            
            $page_data[] = [
                'id' => $sub['id'], 'full_name' => $sub['full_name'], 'role' => $sub['role'],
                'total_due' => $stats['total_due'] ?? 0
            ];
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
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Due Payments Report</h1>
        <p class="text-sm text-gray-500">Drill down to view outstanding payments by team, salesperson, and customer.</p>
    </div>
     <?php if (!empty($success_message)): ?>
    <div class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md" role="alert"><p class="font-bold">Success</p><p><?php echo htmlspecialchars($success_message); ?></p></div>
    <?php endif; ?>

    <!-- Main Content Area -->
    <?php if ($display_mode === 'team'): ?>
        <h2 class="text-xl font-semibold text-gray-800">Due Summary for Team of: <span class="text-indigo-600"><?php echo htmlspecialchars($view_user['full_name']); ?></span></h2>
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Team Member</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Due</th></tr></thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($page_data)): ?><tr><td colspan="2" class="px-6 py-12 text-center text-gray-500">No subordinates with due payments found.</td></tr>
                <?php else: ?><?php foreach ($page_data as $member): ?><tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-600"><a href="reports_due.php?view_user_id=<?php echo $member['id']; ?>" class="hover:underline"><?php echo htmlspecialchars($member['full_name']); ?></a></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-red-700 text-right font-bold">৳<?php echo number_format($member['total_due'], 2); ?></td>
                </tr><?php endforeach; ?><?php endif; ?>
            </tbody>
        </table></div>
    <?php elseif ($display_mode === 'salesperson_customers'): ?>
        <h2 class="text-xl font-semibold text-gray-800">Due Customers for: <span class="text-indigo-600"><?php echo htmlspecialchars($view_user['full_name']); ?></span></h2>
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Due</th></tr></thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($page_data)): ?><tr><td colspan="2" class="px-6 py-12 text-center text-gray-500">This salesperson has no customers with due payments.</td></tr>
                <?php else: ?><?php foreach ($page_data as $customer): ?><tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-600"><a href="reports_due.php?view_user_id=<?php echo $view_user_id; ?>&view_customer_id=<?php echo $customer['id']; ?>" class="hover:underline"><?php echo htmlspecialchars($customer['name']); ?></a></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-red-700 text-right font-bold">৳<?php echo number_format($customer['total_due'], 2); ?></td>
                </tr><?php endforeach; ?><?php endif; ?>
            </tbody>
        </table></div>
    <?php elseif ($display_mode === 'customer_details'): ?>
        <h2 class="text-xl font-semibold text-gray-800">Due Orders for <span class="text-indigo-600"><?php echo htmlspecialchars($customer_name); ?></span></h2>
        <p class="text-sm text-gray-500">Handled by: <?php echo htmlspecialchars($view_user['full_name']); ?></p>
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order Date</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount Due</th><th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Action</th></tr></thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($page_data)): ?><tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">No due orders found.</td></tr>
                <?php else: ?><?php foreach ($page_data as $order): ?><tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars(date('M d, Y', strtotime($order['order_date']))); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($order['product_name']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-red-700 text-right font-bold">৳<?php echo number_format($order['total_amount'], 2); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                        <form action="reports_due.php?view_user_id=<?php echo $view_user_id; ?>&view_customer_id=<?php echo $view_customer_id; ?>" method="POST">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <button type="submit" name="mark_as_paid" class="font-medium text-green-600 hover:text-green-900">Mark as Paid</button>
                        </form>
                    </td>
                </tr><?php endforeach; ?><?php endif; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>

<?php
require_once 'footer.php';
?>