<?php
// customers_manage.php (with Back Camera Capture)

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
        // --- NEW: Handle Base64 Image Data from Camera ---
        if (isset($_POST['picture_base64']) && !empty($_POST['picture_base64'])) {
            $data = $_POST['picture_base64'];
            // remove the part that we don't need from the provided image data
            list($type, $data) = explode(';', $data);
            list(, $data)      = explode(',', $data);
            $data = base64_decode($data);

            $target_dir = "uploads/customers/";
            if (!is_dir($target_dir)) {
                // Attempt to create the directory if it doesn't exist
                mkdir($target_dir, 0777, true);
            }
            $image_name = time() . '_' . uniqid() . '.png';
            $target_file = $target_dir . $image_name;
            
            // Save the file to the server
            if (file_put_contents($target_file, $data)) {
                $picture_path = $target_file;
            } else {
                $error_message = "Sorry, there was an error saving the captured photo. Please check folder permissions.";
            }
        }

        if (empty($error_message)) {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO customers (name, phone, picture_path, latitude, longitude, created_by_user_id) 
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$name, $phone, $picture_path, $latitude, $longitude, $_SESSION['user_id']]);
                // Redirect to prevent form resubmission
                header("Location: customers_manage.php?success=" . urlencode("Customer '".htmlspecialchars($name)."' added successfully!"));
                exit;
            } catch (PDOException $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Check for success message from redirect
if(isset($_GET['success'])) {
    $success_message = $_GET['success'];
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
            <form action="customers_manage.php" method="POST" class="space-y-4">
                <input type="text" name="name" required class="block w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Customer Name">
                <input type="tel" name="phone" class="block w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Phone Number">
                
                <!-- NEW: Camera Capture Section -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Shop Picture</label>
                    <div class="flex items-center gap-4">
                        <img id="picture-preview" src="https://placehold.co/128x96/EFEFEF/AAAAAA&text=No+Image" class="h-24 w-32 rounded-md object-cover bg-gray-200">
                        <button type="button" id="take-picture-btn" class="font-medium text-indigo-700 bg-indigo-100 hover:bg-indigo-200 py-2 px-4 rounded-md">Capture Image</button>
                    </div>
                    <input type="hidden" name="picture_base64" id="picture_base64">
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

<!-- NEW: Camera Modal -->
<div id="camera-modal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-75 z-50 flex items-center justify-center p-4">
    <div class="relative bg-white rounded-lg shadow-xl p-4 sm:p-6 w-full max-w-lg mx-auto">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Capture Shop Image</h3>
        <div id="camera-container" class="bg-black rounded-md overflow-hidden aspect-video">
            <video id="video-feed" class="w-full h-full object-cover" autoplay playsinline></video>
            <canvas id="canvas" class="hidden"></canvas>
            <img id="photo-preview" class="hidden w-full h-full object-cover">
        </div>
        <div id="camera-error" class="hidden text-red-600 text-sm mt-2"></div>
        <div class="mt-4 flex flex-col sm:flex-row gap-2 justify-center" id="camera-controls">
            <button type="button" id="capture-btn" class="w-full justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Capture</button>
            <button type="button" id="retake-btn" class="w-full justify-center py-2 px-4 rounded-md text-gray-700 bg-gray-200 hover:bg-gray-300 hidden">Retake</button>
            <button type="button" id="confirm-btn" class="w-full justify-center py-2 px-4 rounded-md text-white bg-green-600 hover:bg-green-700 hidden">Confirm & Use Picture</button>
        </div>
        <button type="button" id="close-camera-btn" class="absolute top-2 right-2 text-gray-500 bg-white rounded-full p-1 leading-none hover:bg-gray-100">&times;</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Geolocation Logic (remains the same)
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

    // Camera Logic with Back Camera Priority
    const cameraModal = document.getElementById('camera-modal');
    const openCameraBtn = document.getElementById('take-picture-btn');
    const closeCameraBtn = document.getElementById('close-camera-btn');
    const video = document.getElementById('video-feed');
    const canvas = document.getElementById('canvas');
    const photoPreview = document.getElementById('photo-preview');
    const captureBtn = document.getElementById('capture-btn');
    const retakeBtn = document.getElementById('retake-btn');
    const confirmBtn = document.getElementById('confirm-btn');
    const pictureBase64Input = document.getElementById('picture_base64');
    const formPicturePreview = document.getElementById('picture-preview');
    const cameraError = document.getElementById('camera-error');
    let stream;

    async function startCamera() {
        try {
            if (stream) { stream.getTracks().forEach(track => track.stop()); }
            // Prefer the back camera ('environment'), which is ideal for a shop image
            const constraints = { video: { facingMode: { ideal: 'environment' } } };
            stream = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject = stream;
            cameraError.classList.add('hidden');
            resetToLiveView();
            cameraModal.classList.remove('hidden');
        } catch (err) {
            console.error("Back camera error:", err);
            // Fallback to front camera if the back one fails or isn't available
            try {
                const fallbackConstraints = { video: { facingMode: 'user' } };
                stream = await navigator.mediaDevices.getUserMedia(fallbackConstraints);
                video.srcObject = stream;
                cameraError.classList.add('hidden');
                resetToLiveView();
                cameraModal.classList.remove('hidden');
            } catch (fallbackErr) {
                 cameraError.textContent = "Could not access any camera. Please check device permissions.";
                 cameraError.classList.remove('hidden');
                 console.error("Fallback camera error:", fallbackErr);
            }
        }
    }

    function stopCamera() {
        if (stream) { stream.getTracks().forEach(track => track.stop()); }
        cameraModal.classList.add('hidden');
    }

    function resetToLiveView() {
        video.classList.remove('hidden'); photoPreview.classList.add('hidden');
        captureBtn.classList.remove('hidden'); retakeBtn.classList.add('hidden'); confirmBtn.classList.add('hidden');
    }
    
    function showPreview() {
        video.classList.add('hidden'); photoPreview.classList.remove('hidden');
        captureBtn.classList.add('hidden'); retakeBtn.classList.remove('hidden'); confirmBtn.classList.remove('hidden');
    }

    openCameraBtn.addEventListener('click', startCamera);
    closeCameraBtn.addEventListener('click', stopCamera);

    captureBtn.addEventListener('click', () => {
        const context = canvas.getContext('2d');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        const dataUrl = canvas.toDataURL('image/png');
        photoPreview.src = dataUrl;
        showPreview();
    });

    retakeBtn.addEventListener('click', resetToLiveView);

    confirmBtn.addEventListener('click', () => {
        const dataUrl = photoPreview.src;
        pictureBase64Input.value = dataUrl;
        formPicturePreview.src = dataUrl;
        stopCamera();
    });
});
</script>
<?php
require_once 'footer.php';
?>