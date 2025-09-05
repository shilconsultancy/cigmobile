<?php
// customers_manage.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'db.php';

$error_message = '';
$success_message = '';

// Handle form submission for adding a new customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $latitude = !empty($_POST['latitude']) ? trim($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? trim($_POST['longitude']) : null;
    $picture_path = null;

    if (empty($name)) {
        $error_message = "Customer name is required.";
    } else {
        // Handle file upload
        if (isset($_FILES['picture']) && $_FILES['picture']['error'] == 0) {
            $target_dir = "uploads/customers/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            $image_name = time() . '_' . basename($_FILES["picture"]["name"]);
            $target_file = $target_dir . $image_name;
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            $check = getimagesize($_FILES["picture"]["tmp_name"]);
            if ($check === false) {
                $error_message = "File is not a valid image.";
            } elseif ($_FILES["picture"]["size"] > 2000000) {
                $error_message = "Sorry, your file is too large.";
            } elseif (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                $error_message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            } else {
                if (move_uploaded_file($_FILES["picture"]["tmp_name"], $target_file)) {
                    $picture_path = $target_file;
                } else {
                    $error_message = "Sorry, there was an error uploading your file.";
                }
            }
        }

        if (empty($error_message)) {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO customers (name, phone, picture_path, latitude, longitude, created_by_user_id) 
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$name, $phone, $picture_path, $latitude, $longitude, $_SESSION['user_id']]);
                $success_message = "Customer '".htmlspecialchars($name)."' added successfully!";
            } catch (PDOException $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch all customers for the table
$customers = $pdo->query("SELECT * FROM customers ORDER BY name ASC")->fetchAll();

$page_title = 'Manage Customers';
require_once 'header.php';
?>

<div class="bg-white p-4 sm:p-6 lg:p-8 rounded-xl shadow-lg max-w-7xl mx-auto space-y-8">
    <div class="flex items-center justify-between border-b pb-4">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Customer Management</h1>
        <a href="dashboard.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to Dashboard</a>
    </div>

    <?php if (!empty($error_message)): ?>
    <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert"><p class="font-bold">Error</p><p><?php echo htmlspecialchars($error_message); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($success_message)): ?>
    <div class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md" role="alert"><p class="font-bold">Success</p><p><?php echo htmlspecialchars($success_message); ?></p></div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Add Customer Form -->
        <div class="lg:col-span-1 border p-5 rounded-lg bg-gray-50 h-fit">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Customer</h2>
            <form action="customers_manage.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="text" name="name" required class="block w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Customer Name">
                <input type="tel" name="phone" class="block w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Phone Number">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Shop Picture (Optional)</label>
                    <input type="file" name="picture" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                </div>
                <div>
                    <button type="button" id="get-location-btn" class="w-full text-sm font-medium text-indigo-700 bg-indigo-100 hover:bg-indigo-200 py-2 px-4 rounded-md">Detect Current Location</button>
                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">
                    <p id="location-status" class="text-xs text-center mt-2 text-gray-500"></p>
                </div>
                <button type="submit" name="add_customer" class="w-full justify-center py-2 px-4 border rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Add Customer</button>
            </form>
        </div>

        <!-- Customer List -->
        <div class="lg:col-span-2">
             <h2 class="text-xl font-semibold text-gray-800 mb-4">Existing Customers</h2>
             <div class="overflow-x-auto border rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th></tr></thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach($customers as $customer): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 flex items-center gap-3">
                                <img src="<?php echo htmlspecialchars($customer['picture_path'] ?? 'https://placehold.co/40x40/EFEFEF/AAAAAA&text=No+Img'); ?>" alt="Shop Image" class="h-10 w-10 rounded-full object-cover">
                                <a href="customer_profile.php?id=<?php echo $customer['id']; ?>" class="text-indigo-600 hover:underline"><?php echo htmlspecialchars($customer['name']); ?></a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?php if($customer['latitude'] && $customer['longitude']): ?>
                                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $customer['latitude']; ?>,<?php echo $customer['longitude']; ?>" target="_blank" class="text-indigo-600 hover:underline">View on Map</a>
                                <?php else: echo 'N/A'; endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
             </div>
        </div>
    </div>
</div>
<script>
document.getElementById('get-location-btn').addEventListener('click', function() {
    const statusEl = document.getElementById('location-status');
    if (!navigator.geolocation) {
        statusEl.textContent = 'Geolocation is not supported.';
    } else {
        statusEl.textContent = 'Detecting…';
        navigator.geolocation.getCurrentPosition(function(position) {
            document.getElementById('latitude').value = position.coords.latitude;
            document.getElementById('longitude').value = position.coords.longitude;
            statusEl.textContent = 'Location captured!';
            statusEl.style.color = 'green';
        }, function() {
            statusEl.textContent = 'Unable to retrieve location.';
            statusEl.style.color = 'red';
        });
    }
});
</script>
<?php
require_once 'footer.php';
?>