<?php
// inventory_manage.php

// --- PHP ERROR REPORTING (for debugging) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// -------------------------------------------

require_once 'auth.php';
require_once 'db.php';

// --- Authorization Check ---
if ($_SESSION['user_role'] !== 'owner') {
    die("Access Denied. You do not have permission to view this page.");
}

// Initialize variables
$error_message = '';
$success_message = '';
$categories = [];
$products_with_stock = [];

// --- Helper Function ---
function convertPcsToUnits($total_pcs) {
    if ($total_pcs === null) return ['crates' => 0, 'cartons' => 0, 'pcs' => 0];
    $crates = floor($total_pcs / 500);
    $remaining_pcs = $total_pcs % 500;
    $cartons = floor($remaining_pcs / 10);
    $pcs = $remaining_pcs % 10;
    return ['crates' => $crates, 'cartons' => $cartons, 'pcs' => $pcs];
}

// --- FORM HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_new_product'])) {
        $new_product_name = trim($_POST['new_product_name']);
        $category_id = (int)$_POST['category_id'];
        $price_per_pc = filter_var($_POST['price_per_pc'], FILTER_VALIDATE_FLOAT);
        $default_cost_per_pc = filter_var($_POST['default_cost_per_pc'], FILTER_VALIDATE_FLOAT);
        $purchase_date = trim($_POST['purchase_date']);
        $batch_cost_per_pc = filter_var($_POST['batch_cost_per_pc'], FILTER_VALIDATE_FLOAT);
        $crates = (int)($_POST['crates'] ?? 0);
        $cartons = (int)($_POST['cartons'] ?? 0);
        $pcs = (int)($_POST['pcs'] ?? 0);
        if (empty($new_product_name) || empty($category_id) || $price_per_pc === false || $price_per_pc <= 0) {
            $error_message = 'New product name, category, and a valid selling price are required.';
        } else {
            $total_initial_stock = ($crates * 500) + ($cartons * 10) + $pcs;
            if ($total_initial_stock > 0 && (empty($purchase_date) || $batch_cost_per_pc === false || $batch_cost_per_pc <= 0)) {
                 $error_message = 'If adding initial stock, a valid purchase date and batch cost are required.';
            } else {
                $pdo->beginTransaction();
                try {
                    $check_stmt = $pdo->prepare("SELECT id FROM products WHERE LOWER(name) = LOWER(?)");
                    $check_stmt->execute([$new_product_name]);
                    if ($check_stmt->fetch()) { throw new Exception("A product with the name '".htmlspecialchars($new_product_name)."' already exists."); }
                    $product_stmt = $pdo->prepare("INSERT INTO products (name, category_id, price_per_pc, default_cost_per_pc) VALUES (?, ?, ?, ?)");
                    $product_stmt->execute([$new_product_name, $category_id, $price_per_pc, $default_cost_per_pc]);
                    $new_product_id = $pdo->lastInsertId();
                    if ($total_initial_stock > 0) {
                        $inv_stmt = $pdo->prepare("INSERT INTO inventory_batches (product_id, quantity_pcs, cost_per_pc, purchase_date) VALUES (?, ?, ?, ?)");
                        $inv_stmt->execute([$new_product_id, $total_initial_stock, $batch_cost_per_pc, $purchase_date]);
                    }
                    $pdo->commit();
                    $success_message = "Successfully created new product '".htmlspecialchars($new_product_name)."'";
                    if ($total_initial_stock > 0) { $success_message .= " with an initial stock of ".number_format($total_initial_stock)." pieces."; }
                } catch (Exception $e) { $pdo->rollBack(); $error_message = "Error: " . $e->getMessage(); }
            }
        }
    }
    elseif (isset($_POST['add_existing_stock'])) {
        $product_id = (int)$_POST['product_id'];
        $purchase_date = trim($_POST['purchase_date']);
        $batch_cost_per_pc = filter_var($_POST['batch_cost_per_pc'], FILTER_VALIDATE_FLOAT);
        $crates = (int)($_POST['crates'] ?? 0);
        $cartons = (int)($_POST['cartons'] ?? 0);
        $pcs = (int)($_POST['pcs'] ?? 0);
        if (empty($product_id) || empty($purchase_date) || $batch_cost_per_pc === false || $batch_cost_per_pc <= 0) {
            $error_message = 'Product, purchase date, and a valid cost per piece are required.';
        } else {
            $total_pcs_to_add = ($crates * 500) + ($cartons * 10) + $pcs;
            if ($total_pcs_to_add <= 0) {
                $error_message = 'Total quantity to add must be greater than zero.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO inventory_batches (product_id, quantity_pcs, cost_per_pc, purchase_date) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$product_id, $total_pcs_to_add, $batch_cost_per_pc, $purchase_date]);
                    $success_message = "Successfully added a new batch of " . number_format($total_pcs_to_add) . " pieces.";
                } catch (PDOException $e) { $error_message = 'A database error occurred while adding the new stock batch.'; }
            }
        }
    }
}

// --- Fetch data for display ---
try {
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
    $products_with_stock = $pdo->query(
        "SELECT p.id, p.name, p.price_per_pc, c.name as category_name,
            COALESCE(SUM(ib.quantity_pcs), 0) as total_stock_pcs,
            (p.price_per_pc * COALESCE(SUM(ib.quantity_pcs), 0)) as total_selling_value,
            COALESCE(SUM(ib.quantity_pcs * ib.cost_per_pc), 0) as total_purchase_value
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         LEFT JOIN inventory_batches ib ON p.id = ib.product_id
         GROUP BY p.id, p.name, p.price_per_pc, c.name
         ORDER BY p.name ASC"
    )->fetchAll();
} catch (PDOException $e) {
    die("A database error occurred while loading page data.");
}

$page_title = 'Product & Inventory';
require_once 'header.php';
?>

<div class="bg-white p-4 sm:p-6 lg:p-8 rounded-xl shadow-lg max-w-7xl mx-auto space-y-8">
    <div class="flex items-center justify-between border-b pb-4">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Product & Inventory Management</h1>
        <a href="dashboard.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to Dashboard</a>
    </div>

    <?php if (!empty($error_message)): ?>
    <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert"><p class="font-bold">Error</p><p><?php echo htmlspecialchars($error_message); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($success_message)): ?>
    <div class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md" role="alert"><p class="font-bold">Success</p><p><?php echo htmlspecialchars($success_message); ?></p></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="border p-5 rounded-lg bg-gray-50">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Product</h2>
            <form action="inventory_manage.php" method="POST" class="space-y-4">
                <input type="text" name="new_product_name" required class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" placeholder="New Product Name">
                <select name="category_id" required class="block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm"><option value="">Select Category</option><?php foreach ($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select>
                <div class="grid grid-cols-2 gap-4">
                    <input type="number" name="price_per_pc" step="0.01" min="0.01" required class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" placeholder="Selling Price/Pc (৳)">
                    <input type="number" name="default_cost_per_pc" step="0.01" min="0" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" placeholder="Default Cost/Pc (৳)">
                </div>
                <p class="text-sm font-medium text-gray-700 pt-2 border-t">Initial Stock Batch (Optional)</p>
                <div class="grid grid-cols-2 gap-4">
                    <input type="date" name="purchase_date" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                    <input type="number" name="batch_cost_per_pc" step="0.01" min="0.01" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" placeholder="Batch Cost/Pc (৳)">
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <input type="number" name="crates" min="0" placeholder="Crates" class="block w-full text-center px-2 py-2 border border-gray-300 rounded-md">
                    <input type="number" name="cartons" min="0" placeholder="Cartons" class="block w-full text-center px-2 py-2 border border-gray-300 rounded-md">
                    <input type="number" name="pcs" min="0" placeholder="Pcs" class="block w-full text-center px-2 py-2 border border-gray-300 rounded-md">
                </div>
                <button type="submit" name="add_new_product" class="w-full justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">Create Product</button>
            </form>
        </div>
        <div class="border p-5 rounded-lg bg-gray-50">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Stock Batch</h2>
            <form action="inventory_manage.php" method="POST" class="space-y-4">
                <select name="product_id" required class="block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm"><option value="">Select Existing Product</option><?php foreach ($products_with_stock as $prod): ?><option value="<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['name']); ?></option><?php endforeach; ?></select>
                 <div class="grid grid-cols-2 gap-4">
                    <input type="date" name="purchase_date" required class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                    <input type="number" name="batch_cost_per_pc" step="0.01" min="0.01" required class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" placeholder="Batch Cost/Pc (৳)">
                </div>
                <p class="text-sm font-medium text-gray-700">Stock Quantity to Add</p>
                <div class="grid grid-cols-3 gap-4">
                    <input type="number" name="crates" min="0" placeholder="Crates" class="block w-full text-center px-2 py-2 border border-gray-300 rounded-md">
                    <input type="number" name="cartons" min="0" placeholder="Cartons" class="block w-full text-center px-2 py-2 border border-gray-300 rounded-md">
                    <input type="number" name="pcs" min="0" placeholder="Pcs" class="block w-full text-center px-2 py-2 border border-gray-300 rounded-md">
                </div>
                <button type="submit" name="add_existing_stock" class="w-full justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">Add Stock Batch</button>
            </form>
        </div>
    </div>

    <div class="pt-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-800">Inventory Summary</h2>
            <div class="mt-2 sm:mt-0 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" /></svg></div>
                <input type="text" id="product-search-input" placeholder="Search products..." class="block w-full sm:w-64 pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
        </div>
        <div class="overflow-x-auto border rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Purchase Value</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Selling Value</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Stock (Pcs)</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="inventory-table-body">
                    <?php if (empty($products_with_stock)): ?>
                         <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">No products found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products_with_stock as $product): ?>
                            <tr class="product-row-searchable">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 product-name"><?php echo htmlspecialchars($product['name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-red-700 text-right font-semibold">৳<?php echo number_format($product['total_purchase_value'], 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-700 text-right font-semibold">৳<?php echo number_format($product['total_selling_value'], 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-semibold"><?php echo number_format($product['total_stock_pcs']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('product-search-input');
    const tableBody = document.getElementById('inventory-table-body');
    const tableRows = tableBody.getElementsByClassName('product-row-searchable');
    let debounceTimer;
    function debounce(func, delay) {
        return function(...args) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => { func.apply(this, args); }, delay);
        };
    }
    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        for (let i = 0; i < tableRows.length; i++) {
            const row = tableRows[i];
            const productName = row.querySelector('.product-name').textContent.toLowerCase();
            if (productName.includes(searchTerm)) {
                row.classList.remove('hidden-row');
            } else {
                row.classList.add('hidden-row');
            }
        }
    }
    const debouncedFilter = debounce(filterTable, 300);
    searchInput.addEventListener('input', debouncedFilter);
});
</script>

<?php
require_once 'footer.php';
?>