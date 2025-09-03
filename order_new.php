<?php
// order_new.php (Multi-Product Version)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'db.php';

$error_message = '';
$success_message = '';
$show_success_modal = false;
$products_with_inventory = [];

try {
    $stmt = $pdo->query(
        "SELECT p.id, p.name, p.price_per_pc, COALESCE(i.quantity_in_pcs, 0) as quantity_in_pcs
         FROM products p
         LEFT JOIN inventory i ON p.id = i.product_id
         ORDER BY p.name ASC"
    );
    $products_with_inventory = $stmt->fetchAll();

    if (empty($products_with_inventory)) {
        die("FATAL ERROR: No products found in the database.");
    }

} catch (PDOException $e) {
    error_log("Order Page Load Error: " . $e->getMessage());
    die("A database error occurred while loading the page.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name']);
    $customer_phone = trim($_POST['customer_phone']);
    $order_products = $_POST['products'] ?? [];

    if (empty($customer_name)) {
        $error_message = 'Customer name is required.';
    } elseif (empty($order_products)) {
        $error_message = 'You must add at least one product to the order.';
    } else {
        $pdo->beginTransaction();
        try {
            $grand_total_pcs = 0;
            $grand_total_amount = 0;
            
            // First, validate all products and calculate totals
            foreach ($order_products as $item) {
                $product_id = (int)$item['id'];
                $crates = (int)($item['crates'] ?? 0);
                $cartons = (int)($item['cartons'] ?? 0);
                $pcs = (int)($item['pcs'] ?? 0);
                $total_pcs = ($crates * 500) + ($cartons * 10) + $pcs;

                if ($total_pcs <= 0) continue; // Skip items with no quantity

                // Find product details
                $product_details = null;
                foreach($products_with_inventory as $p) {
                    if ($p['id'] == $product_id) {
                        $product_details = $p;
                        break;
                    }
                }

                if (!$product_details) {
                    throw new Exception("Invalid product ID {$product_id} submitted.");
                }

                if ($total_pcs > $product_details['quantity_in_pcs']) {
                     throw new Exception("Insufficient stock for " . htmlspecialchars($product_details['name']) . ". Requested: {$total_pcs}, Available: {$product_details['quantity_in_pcs']}.");
                }
                
                $total_amount = $total_pcs * $product_details['price_per_pc'];
                $grand_total_pcs += $total_pcs;
                $grand_total_amount += $total_amount;
            }

            // If we are here, all validations passed. Now process the order.
            foreach ($order_products as $item) {
                $product_id = (int)$item['id'];
                $crates = (int)($item['crates'] ?? 0);
                $cartons = (int)($item['cartons'] ?? 0);
                $pcs = (int)($item['pcs'] ?? 0);
                $total_pcs = ($crates * 500) + ($cartons * 10) + $pcs;
                
                if ($total_pcs <= 0) continue;

                $product_details = null;
                 foreach($products_with_inventory as $p) {
                    if ($p['id'] == $product_id) {
                        $product_details = $p;
                        break;
                    }
                }
                $total_amount = $total_pcs * $product_details['price_per_pc'];

                // 1. Insert into orders table
                $order_stmt = $pdo->prepare(
                    "INSERT INTO orders (user_id, product_id, customer_name, customer_phone, total_pcs, total_amount, status)
                     VALUES (?, ?, ?, ?, ?, ?, 'Completed')"
                );
                $order_stmt->execute([$_SESSION['user_id'], $product_id, $customer_name, $customer_phone, $total_pcs, $total_amount]);

                // 2. Update inventory
                $update_inv_stmt = $pdo->prepare(
                    "UPDATE inventory SET quantity_in_pcs = quantity_in_pcs - ? WHERE product_id = ?"
                );
                $update_inv_stmt->execute([$total_pcs, $product_id]);
            }
            
            $pdo->commit();
            $success_message = "Order successfully created with {$grand_total_pcs} total pieces!";
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

<div class="bg-white p-4 sm:p-6 lg:p-8 rounded-xl shadow-lg max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-8 border-b pb-4">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Create New Order</h1>
        <a href="dashboard.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to Dashboard</a>
    </div>

    <?php if (!empty($error_message)): ?>
    <div class="mb-6 flex items-center gap-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <div><p class="font-bold">Error</p><p><?php echo htmlspecialchars($error_message); ?></p></div>
    </div>
    <?php endif; ?>

    <form action="order_new.php" method="POST" id="order-form" class="space-y-8">
        <!-- Customer Details -->
        <div class="border p-5 rounded-lg">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Customer Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="customer_name" class="block text-sm font-medium text-gray-700">Customer Name</label>
                    <input type="text" id="customer_name" name="customer_name" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="John Doe">
                </div>
                <div>
                    <label for="customer_phone" class="block text-sm font-medium text-gray-700">Customer Phone (Optional)</label>
                    <input type="tel" id="customer_phone" name="customer_phone" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="01712345678">
                </div>
            </div>
        </div>

        <!-- Products Section -->
        <div class="border p-5 rounded-lg">
             <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Products</h2>
                <button type="button" id="add-product-btn" class="flex items-center gap-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 py-2 px-4 rounded-md shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" /></svg>
                    Add Product
                </button>
            </div>
            <div id="product-rows-container" class="space-y-4">
                <!-- Product rows will be dynamically inserted here -->
            </div>
        </div>

        <!-- Grand Totals & Submit -->
        <div class="pt-8 border-t">
            <div class="bg-slate-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Grand Total Summary</h3>
                <div class="flex justify-between items-center mb-2"><span class="text-gray-600">Total Pieces:</span><span id="grand-total-pcs" class="font-bold text-xl text-gray-900">0</span></div>
                <div class="flex justify-between items-center border-t pt-2 mt-2"><span class="text-gray-600 font-semibold">Total Amount:</span><span id="grand-total-amount" class="font-bold text-2xl text-green-600">$0.00</span></div>
            </div>
            <div class="mt-6">
                <button type="submit" class="w-full flex justify-center items-center gap-2 py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Submit Final Order
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Success Modal -->
<div id="success-modal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-75 z-50 flex items-center justify-center p-4">
    <div class="relative bg-white rounded-lg shadow-xl p-6 sm:p-8 text-center max-w-sm mx-auto">
        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4"><svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg></div>
        <h3 class="text-xl leading-6 font-medium text-gray-900">Order Processed!</h3>
        <div class="mt-2 py-3"><p class="text-sm text-gray-600" id="modal-success-message">Your order has been created successfully.</p></div>
        <div class="mt-4 space-y-3 sm:space-y-0 sm:flex sm:space-x-4">
            <button id="modal-new-order-btn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700">Create New Order</button>
            <button id="modal-dashboard-btn" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50">Go to Dashboard</button>
        </div>
    </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        const productsData = <?php echo json_encode($products_with_inventory); ?>;
        const container = document.getElementById('product-rows-container');
        const addProductBtn = document.getElementById('add-product-btn');
        let productRowIndex = 0;

        function createProductRow() {
            const rowIndex = productRowIndex++;
            const div = document.createElement('div');
            div.className = 'p-4 border rounded-md bg-gray-50 product-row';
            div.setAttribute('data-index', rowIndex);

            let optionsHtml = '<option value="">-- Select Product --</option>';
            productsData.forEach(p => {
                optionsHtml += `<option value="${p.id}">${p.name}</option>`;
            });

            div.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Product</label>
                        <select name="products[${rowIndex}][id]" class="product-select mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            ${optionsHtml}
                        </select>
                        <div class="stock-display text-xs text-gray-500 mt-1" style="display: none;"></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Crates</label>
                        <input type="number" name="products[${rowIndex}][crates]" min="0" placeholder="0" class="quantity-input mt-1 block w-full text-center py-2 px-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Cartons</label>
                        <input type="number" name="products[${rowIndex}][cartons]" min="0" placeholder="0" class="quantity-input mt-1 block w-full text-center py-2 px-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Pcs</label>
                        <input type="number" name="products[${rowIndex}][pcs]" min="0" placeholder="0" class="quantity-input mt-1 block w-full text-center py-2 px-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="text-right">
                        <button type="button" class="remove-product-btn text-red-500 hover:text-red-700 font-medium">Remove</button>
                    </div>
                </div>
            `;
            container.appendChild(div);
        }

        addProductBtn.addEventListener('click', createProductRow);
        
        container.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-product-btn')) {
                e.target.closest('.product-row').remove();
                updateGrandTotals();
            }
        });

        container.addEventListener('change', function(e) {
            if (e.target.classList.contains('product-select')) {
                const row = e.target.closest('.product-row');
                const stockDisplay = row.querySelector('.stock-display');
                const selectedProductId = parseInt(e.target.value);
                const selectedProduct = productsData.find(p => p.id === selectedProductId);

                if (selectedProduct) {
                    stockDisplay.textContent = `Stock: ${parseInt(selectedProduct.quantity_in_pcs).toLocaleString()} pcs`;
                    stockDisplay.style.display = 'block';
                } else {
                    stockDisplay.style.display = 'none';
                }
                updateGrandTotals();
            }
        });

        container.addEventListener('input', function(e) {
            if (e.target.classList.contains('quantity-input')) {
                updateGrandTotals();
            }
        });

        function updateGrandTotals() {
            let grandTotalPcs = 0;
            let grandTotalAmount = 0;

            document.querySelectorAll('.product-row').forEach(row => {
                const productId = parseInt(row.querySelector('.product-select').value);
                if (!productId) return;

                const product = productsData.find(p => p.id === productId);
                if (!product) return;

                const crates = parseInt(row.querySelector('input[name*="[crates]"]').value) || 0;
                const cartons = parseInt(row.querySelector('input[name*="[cartons]"]').value) || 0;
                const pcs = parseInt(row.querySelector('input[name*="[pcs]"]').value) || 0;
                
                const totalPcs = (crates * 500) + (cartons * 10) + pcs;
                const totalAmount = totalPcs * parseFloat(product.price_per_pc);
                
                grandTotalPcs += totalPcs;
                grandTotalAmount += totalAmount;
            });
            
            document.getElementById('grand-total-pcs').textContent = grandTotalPcs.toLocaleString();
            document.getElementById('grand-total-amount').textContent = '$' + grandTotalAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Add the first row automatically on page load
        createProductRow();
    });
</script>


<?php if ($show_success_modal): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('success-modal');
        const modalMessage = document.getElementById('modal-success-message');
        const newOrderBtn = document.getElementById('modal-new-order-btn');
        const dashboardBtn = document.getElementById('modal-dashboard-btn');
        
        modalMessage.textContent = "<?php echo addslashes($success_message); ?>";
        modal.classList.remove('hidden');

        newOrderBtn.addEventListener('click', function() {
            window.location.href = 'order_new.php';
        });

        dashboardBtn.addEventListener('click', function() {
            window.location.href = 'dashboard.php';
        });
    });
</script>
<?php endif; ?>

<?php
require_once 'footer.php';
?>
