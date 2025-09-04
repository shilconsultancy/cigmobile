<?php
// order_new.php (with Owner Access Bypass)

ini_set('display_errors', 1); error_reporting(E_ALL);

require_once 'auth.php';
require_once 'db.php';

$error_message = '';
$success_message = '';
$show_success_modal = false;
$products_for_sale = [];
$customers = [];

try {
    // --- NEW: Dynamic Product Query Based on Role ---
    $allowed_categories = $_SESSION['allowed_categories'] ?? [];
    
    if ($_SESSION['user_role'] === 'owner') {
        // Owner sees all products, regardless of personal category assignments.
        $sql = "SELECT p.id, p.name, p.price_per_pc, COALESCE(SUM(ib.quantity_pcs), 0) as total_stock_pcs
                FROM products p
                LEFT JOIN inventory_batches ib ON p.id = ib.product_id
                GROUP BY p.id
                ORDER BY p.name ASC";
        $product_stmt = $pdo->query($sql);
    } else {
        // Other users see products ONLY from their allowed categories.
        if (empty($allowed_categories)) {
            $products_for_sale = []; // If user has no categories, they can't sell anything.
        } else {
            $placeholders = rtrim(str_repeat('?,', count($allowed_categories)), ',');
            $sql = "SELECT p.id, p.name, p.price_per_pc, COALESCE(SUM(ib.quantity_pcs), 0) as total_stock_pcs
                    FROM products p
                    LEFT JOIN inventory_batches ib ON p.id = ib.product_id
                    WHERE p.category_id IN ($placeholders)
                    GROUP BY p.id
                    ORDER BY p.name ASC";
            $product_stmt = $pdo->prepare($sql);
            $product_stmt->execute($allowed_categories);
        }
    }

    if (isset($product_stmt)) {
        $products_for_sale = $product_stmt->fetchAll();
    }
    // ----------------------------------------------------
    
    // Fetch all customers (no change here)
    $customers = $pdo->query("SELECT id, name FROM customers ORDER BY name ASC")->fetchAll();

} catch (PDOException $e) {
    die("A database error occurred while loading page data: " . $e->getMessage());
}

// The entire form submission logic from the previous version remains the same.
// It is already secure and works with the FIFO system.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = (int)$_POST['customer_id'];
    $payment_status = $_POST['payment_status'] ?? 'Paid';
    $order_products = $_POST['products'] ?? [];

    if (empty($customer_id)) {
        $error_message = 'A customer must be selected.';
    } elseif (empty($order_products)) {
        $error_message = 'You must add at least one product.';
    } else {
        $pdo->beginTransaction();
        try {
            $due_date = ($payment_status === 'Due') ? date('Y-m-d', strtotime('+1 day')) : null;
            
            foreach ($order_products as $item) {
                if (empty($item['id'])) continue;
                $product_id = (int)$item['id'];
                $quantity = (int)($item['quantity'] ?? 0);
                $unit = $item['unit'] ?? 'pcs';
                $selling_price_per_pc = filter_var($item['price'], FILTER_VALIDATE_FLOAT);
                $multiplier = 1;
                if ($unit === 'cartons') $multiplier = 10;
                if ($unit === 'crates') $multiplier = 500;
                $total_pcs_ordered = $quantity * $multiplier;
                
                if ($total_pcs_ordered <= 0) continue;
                if ($selling_price_per_pc === false || $selling_price_per_pc <= 0) { throw new Exception("A valid selling price is required for all items."); }
                
                // FIFO Logic
                $batch_stmt = $pdo->prepare("SELECT id, quantity_pcs, cost_per_pc FROM inventory_batches WHERE product_id = ? AND quantity_pcs > 0 ORDER BY purchase_date ASC, created_at ASC");
                $batch_stmt->execute([$product_id]);
                $available_batches = $batch_stmt->fetchAll();
                $total_stock_available = array_sum(array_column($available_batches, 'quantity_pcs'));

                if ($total_pcs_ordered > $total_stock_available) { throw new Exception("Insufficient stock for a product in your order."); }

                $pcs_remaining_to_fulfill = $total_pcs_ordered;
                $total_cost_for_this_order_item = 0;

                foreach ($available_batches as $batch) {
                    if ($pcs_remaining_to_fulfill <= 0) break;
                    $pcs_to_take_from_batch = min($pcs_remaining_to_fulfill, $batch['quantity_pcs']);
                    if ($pcs_to_take_from_batch == $batch['quantity_pcs']) {
                        $update_stmt = $pdo->prepare("DELETE FROM inventory_batches WHERE id = ?"); $update_stmt->execute([$batch['id']]);
                    } else {
                        $update_stmt = $pdo->prepare("UPDATE inventory_batches SET quantity_pcs = quantity_pcs - ? WHERE id = ?"); $update_stmt->execute([$pcs_to_take_from_batch, $batch['id']]);
                    }
                    $total_cost_for_this_order_item += $pcs_to_take_from_batch * $batch['cost_per_pc'];
                    $pcs_remaining_to_fulfill -= $pcs_to_take_from_batch;
                }
                
                $total_amount_for_item = $total_pcs_ordered * $selling_price_per_pc;
                $avg_cost_at_sale = $total_cost_for_this_order_item / $total_pcs_ordered;

                $order_stmt = $pdo->prepare("INSERT INTO orders (user_id, product_id, customer_id, payment_status, due_date, total_pcs, total_amount, cost_per_pc_at_sale) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $order_stmt->execute([$_SESSION['user_id'], $product_id, $customer_id, $payment_status, $due_date, $total_pcs_ordered, $total_amount_for_item, $avg_cost_at_sale]);
            }
            
            $pdo->commit();
            $success_message = "Order successfully created!";
            $show_success_modal = true;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = $e->getMessage();
        }
    }
}

$page_title = 'Create New Order';
require_once 'header.php';
?>

<div class="bg-white p-4 sm:p-6 lg:p-8 rounded-xl shadow-lg max-w-6xl mx-auto">
    <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-6 border-b pb-4">Create New Order</h1>
    <?php if (!empty($error_message)): ?>
    <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert"><p><?php echo htmlspecialchars($error_message); ?></p></div>
    <?php endif; ?>

    <form action="order_new.php" method="POST" id="order-form" class="space-y-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border p-5 rounded-lg">
            <div><label for="customer_id" class="block text-sm font-medium text-gray-700">Select Customer</label><select id="customer_id" name="customer_id" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm"><option value="">-- Select or Add a Customer --</option><?php foreach($customers as $customer): ?><option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['name']); ?></option><?php endforeach; ?></select><a href="customers_manage.php" target="_blank" class="text-xs text-indigo-600 hover:underline">Or add a new customer here</a></div>
            <div><label class="block text-sm font-medium text-gray-700">Payment Status</label><div class="mt-2 space-x-4"><label class="inline-flex items-center"><input type="radio" name="payment_status" value="Paid" checked class="form-radio text-indigo-600"> <span class="ml-2">Paid</span></label><label class="inline-flex items-center"><input type="radio" name="payment_status" value="Due" class="form-radio text-indigo-600"> <span class="ml-2">Due (Next Day)</span></label></div></div>
        </div>
        <div class="border p-5 rounded-lg">
             <div class="flex items-center justify-between mb-4"><h2 class="text-lg font-semibold text-gray-800">Products</h2><button type="button" id="add-product-btn" class="flex items-center gap-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 py-2 px-4 rounded-md shadow-sm">Add Product</button></div>
             <?php if(empty($products_for_sale)): ?>
                <p class="text-center text-red-600 bg-red-50 p-4 rounded-md">You do not have access to any product categories. Please contact your manager.</p>
             <?php endif; ?>
            <div id="product-rows-container" class="space-y-4"></div>
        </div>
        <div class="pt-8 border-t">
            <div class="bg-slate-50 p-4 rounded-lg"><h3 class="text-lg font-semibold text-gray-900 mb-4">Grand Total Summary</h3><div class="flex justify-between items-center border-t pt-2 mt-2"><span class="text-gray-600 font-semibold">Total Amount:</span><span id="grand-total-amount" class="font-bold text-2xl text-green-600">৳0.00</span></div></div>
            <div class="mt-6"><button type="submit" class="w-full flex justify-center items-center gap-2 py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-green-600 hover:bg-green-700">Submit Final Order</button></div>
        </div>
    </form>
</div>
<div id="success-modal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-75 z-50 flex items-center justify-center p-4"><div class="relative bg-white rounded-lg shadow-xl p-6 sm:p-8 text-center max-w-sm mx-auto"><h3 class="text-xl font-medium text-gray-900">Order Processed!</h3><div class="mt-2 py-3"><p class="text-sm text-gray-600" id="modal-success-message">Your order has been created successfully.</p></div><div class="mt-4 flex space-x-4"><a href="order_new.php" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700">Create New Order</a><a href="dashboard.php" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50">Go to Dashboard</a></div></div></div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const productsData = <?php echo json_encode($products_for_sale); ?>;
        const container = document.getElementById('product-rows-container');
        const addProductBtn = document.getElementById('add-product-btn');
        let productRowIndex = 0;
        function createProductRow() {
            const rowIndex = productRowIndex++;
            const div = document.createElement('div');
            div.className = 'p-4 border rounded-md bg-gray-50 product-row';
            let optionsHtml = '<option value="">-- Select Product --</option>';
            productsData.forEach(p => { optionsHtml += `<option value="${p.id}">${p.name}</option>`; });
            div.innerHTML = `<div class="grid grid-cols-1 md:grid-cols-12 gap-x-4 gap-y-2 items-end"><div class="md:col-span-4"><label class="block text-sm font-medium text-gray-700">Product</label><select name="products[${rowIndex}][id]" class="product-select mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm">${optionsHtml}</select><div class="stock-display text-xs text-gray-500 mt-1" style="display: none;"></div></div><div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700">Quantity</label><input type="number" name="products[${rowIndex}][quantity]" min="0" placeholder="0" class="recalc-input mt-1 block w-full text-center py-2 px-2 border border-gray-300 rounded-md"></div><div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700">Unit</label><select name="products[${rowIndex}][unit]" class="recalc-input mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md"><option value="pcs">Packets</option><option value="cartons">Cartons</option><option value="crates">Crates</option></select></div><div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700">Price/Packet</label><input type="number" step="0.01" min="0.01" name="products[${rowIndex}][price]" class="recalc-input selling-price-input mt-1 block w-full text-center py-2 px-2 border border-gray-300 rounded-md"></div><div class="md:col-span-2 text-right"><button type="button" class="remove-product-btn text-red-500 hover:text-red-700 font-medium">Remove</button></div></div>`;
            container.appendChild(div);
        }
        addProductBtn.addEventListener('click', createProductRow);
        container.addEventListener('click', function(e) { if (e.target.classList.contains('remove-product-btn')) { e.target.closest('.product-row').remove(); updateGrandTotals(); } });
        container.addEventListener('change', function(e) {
            if (e.target.classList.contains('product-select')) {
                const row = e.target.closest('.product-row');
                const stockDisplay = row.querySelector('.stock-display');
                const priceInput = row.querySelector('.selling-price-input');
                const selectedProductId = parseInt(e.target.value);
                const selectedProduct = productsData.find(p => p.id === selectedProductId);
                if (selectedProduct) {
                    stockDisplay.textContent = `Stock: ${parseInt(selectedProduct.total_stock_pcs).toLocaleString()} Packets`;
                    stockDisplay.style.display = 'block';
                    priceInput.value = parseFloat(selectedProduct.price_per_pc).toFixed(2);
                } else {
                    stockDisplay.style.display = 'none'; priceInput.value = '';
                }
                updateGrandTotals();
            }
        });
        const recalcInputsHandler = function(e) { if (e.target.classList.contains('recalc-input')) { updateGrandTotals(); } };
        container.addEventListener('input', recalcInputsHandler);
        container.addEventListener('change', recalcInputsHandler);
        function updateGrandTotals() {
            let grandTotalAmount = 0;
            document.querySelectorAll('.product-row').forEach(row => {
                const quantity = parseInt(row.querySelector('input[name*="[quantity]"]').value) || 0;
                const unit = row.querySelector('select[name*="[unit]"]').value;
                const customPrice = parseFloat(row.querySelector('.selling-price-input').value) || 0;
                let multiplier = 1;
                if (unit === 'cartons') multiplier = 10;
                if (unit === 'crates') multiplier = 500;
                const totalPcs = quantity * multiplier;
                grandTotalAmount += totalPcs * customPrice;
            });
            document.getElementById('grand-total-amount').textContent = '৳' + grandTotalAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        createProductRow();
    });
</script>
<?php if ($show_success_modal): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('success-modal');
        modal.classList.remove('hidden');
        document.getElementById('modal-success-message').textContent = "<?php echo addslashes($success_message); ?>";
        document.getElementById('modal-new-order-btn').addEventListener('click', () => window.location.href = 'order_new.php');
        document.getElementById('modal-dashboard-btn').addEventListener('click', () => window.location.href = 'dashboard.php');
    });
</script>
<?php endif; ?>
<?php
require_once 'footer.php';
?>