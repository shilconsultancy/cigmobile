<?php
// inventory_manage.php

// --- PHP ERROR REPORTING (for debugging) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// -------------------------------------------

// This is a protected page.
require_once 'auth.php';
require_once 'db.php';

// --- Authorization Check ---
// CRITICAL: Only the owner can manage inventory and products.
if ($_SESSION['user_role'] !== 'owner') {
    die("Access Denied. You do not have permission to view this page.");
}

// Initialize variables
$error_message = '';
$success_message = '';
$products_with_inventory = [];

// --- Helper Function ---
function convertPcsToUnits($total_pcs) {
    $crates = floor($total_pcs / 500);
    $remaining_pcs = $total_pcs % 500;
    $cartons = floor($remaining_pcs / 10);
    $pcs = $remaining_pcs % 10;
    return ['crates' => $crates, 'cartons' => $cartons, 'pcs' => $pcs];
}

// --- FORM HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check which form was submitted using the button's name attribute
    
    // --- Logic for Adding a NEW PRODUCT ---
    if (isset($_POST['add_new_product'])) {
        $new_product_name = trim($_POST['new_product_name']);
        $price_per_pc = filter_var($_POST['price_per_pc'], FILTER_VALIDATE_FLOAT);
        $crates = (int)($_POST['crates'] ?? 0);
        $cartons = (int)($_POST['cartons'] ?? 0);
        $pcs = (int)($_POST['pcs'] ?? 0);

        if (empty($new_product_name) || $price_per_pc === false || $price_per_pc <= 0) {
            $error_message = 'New product name and a valid, positive price are required.';
        } else {
            $total_initial_stock = ($crates * 500) + ($cartons * 10) + $pcs;
            
            $pdo->beginTransaction();
            try {
                // Check if product name already exists
                $check_stmt = $pdo->prepare("SELECT id FROM products WHERE name = ?");
                $check_stmt->execute([$new_product_name]);
                if ($check_stmt->fetch()) {
                    throw new Exception("A product with the name '{$new_product_name}' already exists.");
                }

                // 1. Insert into products table
                $product_stmt = $pdo->prepare("INSERT INTO products (name, price_per_pc) VALUES (?, ?)");
                $product_stmt->execute([$new_product_name, $price_per_pc]);
                $new_product_id = $pdo->lastInsertId();

                // 2. Insert into inventory table
                $inv_stmt = $pdo->prepare("INSERT INTO inventory (product_id, quantity_in_pcs) VALUES (?, ?)");
                $inv_stmt->execute([$new_product_id, $total_initial_stock]);
                
                $pdo->commit();
                $success_message = "Successfully created new product '".htmlspecialchars($new_product_name)."' with an initial stock of ".number_format($total_initial_stock)." pieces.";

            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = "Error: " . $e->getMessage();
            }
        }
    }
    
    // --- Logic for Adding Stock to an EXISTING PRODUCT ---
    elseif (isset($_POST['add_existing_stock'])) {
        $product_id = (int)$_POST['product_id'];
        $crates = (int)($_POST['crates'] ?? 0);
        $cartons = (int)($_POST['cartons'] ?? 0);
        $pcs = (int)($_POST['pcs'] ?? 0);

        if (empty($product_id)) {
            $error_message = 'You must select a product.';
        } else {
            $total_pcs_to_add = ($crates * 500) + ($cartons * 10) + $pcs;
            if ($total_pcs_to_add <= 0) {
                $error_message = 'Total quantity to add must be greater than zero.';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE inventory SET quantity_in_pcs = quantity_in_pcs + ? WHERE product_id = ?");
                    $stmt->execute([$total_pcs_to_add, $product_id]);
                    $success_message = "Successfully added " . number_format($total_pcs_to_add) . " pieces to the inventory.";
                } catch (PDOException $e) {
                    error_log("Inventory Update Error: " . $e->getMessage());
                    $error_message = 'A database error occurred while updating the inventory.';
                }
            }
        }
    }
}

// --- Fetch all products and their inventory for display (runs after any updates) ---
try {
    $stmt = $pdo->query(
        "SELECT p.id, p.name, p.price_per_pc, COALESCE(i.quantity_in_pcs, 0) as quantity_in_pcs
         FROM products p
         LEFT JOIN inventory i ON p.id = i.product_id
         ORDER BY p.name ASC"
    );
    $products_with_inventory = $stmt->fetchAll();
} catch (PDOException $e) {
    die("A database error occurred while loading inventory data.");
}

$page_title = 'Product & Inventory Management';
require_once 'header.php';
?>

<div class="bg-white p-4 sm:p-6 lg:p-8 rounded-xl shadow-lg max-w-7xl mx-auto space-y-8">
    <div class="flex items-center justify-between border-b pb-4">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Product & Inventory</h1>
        <a href="dashboard.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to Dashboard</a>
    </div>

    <?php if (!empty($error_message)): ?>
    <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert">
        <p class="font-bold">Error</p>
        <p><?php echo htmlspecialchars($error_message); ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
    <div class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md" role="alert">
        <p class="font-bold">Success</p>
        <p><?php echo htmlspecialchars($success_message); ?></p>
    </div>
    <?php endif; ?>

    <!-- Two-column layout for forms -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        <!-- Form 1: Add New Product -->
        <div class="border p-5 rounded-lg bg-gray-50">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Product</h2>
            <form action="inventory_manage.php" method="POST" class="space-y-4">
                <div>
                    <label for="new_product_name" class="block text-sm font-medium text-gray-700">New Product Name</label>
                    <input type="text" name="new_product_name" id="new_product_name" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                </div>
                <div>
                    <label for="price_per_pc" class="block text-sm font-medium text-gray-700">Price Per Piece ($)</label>
                    <input type="number" name="price_per_pc" id="price_per_pc" step="0.01" min="0.01" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                </div>
                <div>
                    <p class="block text-sm font-medium text-gray-700">Initial Stock Quantity</p>
                    <div class="mt-2 grid grid-cols-3 gap-4">
                        <input type="number" name="crates" min="0" placeholder="Crates" class="block w-full text-center px-2 py-2 border border-gray-300 rounded-md">
                        <input type="number" name="cartons" min="0" placeholder="Cartons" class="block w-full text-center px-2 py-2 border border-gray-300 rounded-md">
                        <input type="number" name="pcs" min="0" placeholder="Pcs" class="block w-full text-center px-2 py-2 border border-gray-300 rounded-md">
                    </div>
                </div>
                <div class="pt-2">
                    <button type="submit" name="add_new_product" class="w-full justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                        Create Product
                    </button>
                </div>
            </form>
        </div>

        <!-- Form 2: Add Stock to Existing Product -->
        <div class="border p-5 rounded-lg bg-gray-50">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Add Stock to Existing Product</h2>
            <form action="inventory_manage.php" method="POST" class="space-y-4">
                <div>
                    <label for="product_id" class="block text-sm font-medium text-gray-700">Select Product</label>
                    <select name="product_id" id="product_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm">
                        <option value="">-- Select a product --</option>
                        <?php foreach ($products_with_inventory as $product): ?>
                            <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                     <p class="block text-sm font-medium text-gray-700">Stock Quantity to Add</p>
                    <div class="mt-2 grid grid-cols-3 gap-4">
                        <input type="number" name="crates" min="0" placeholder="Crates" class="block w-full text-center px-2 py-2 border border-gray-300 rounded-md">
                        <input type="number" name="cartons" min="0" placeholder="Cartons" class="block w-full text-center px-2 py-2 border border-gray-300 rounded-md">
                        <input type="number" name="pcs" min="0" placeholder="Pcs" class="block w-full text-center px-2 py-2 border border-gray-300 rounded-md">
                    </div>
                </div>
                <div class="pt-2">
                    <button type="submit" name="add_existing_stock" class="w-full justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        Add Stock
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Current Inventory List -->
    <div class="pt-8">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Current Stock Levels</h2>
        <div class="overflow-x-auto border rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product (ID)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price/Pc</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total (Pcs)</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">In Stock (Units)</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($products_with_inventory as $product): ?>
                        <?php $units = convertPcsToUnits($product['quantity_in_pcs']); ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($product['name']); ?>
                                <span class="text-xs text-gray-500">(#<?php echo $product['id']; ?>)</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-700 font-semibold">$<?php echo number_format($product['price_per_pc'], 2); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-semibold"><?php echo number_format($product['quantity_in_pcs']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 text-right">
                                <?php echo "{$units['crates']} Crates, {$units['cartons']} Cartons, {$units['pcs']} Pcs"; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>