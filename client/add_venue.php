<?php
// --- PHP Code ---

// **1. Start Session & Auth Check**
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: client_login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

// **2. Include Database Connection**
// Use require_once for critical includes like DB connection
// Ensure the path is correct relative to add_venue.php
require_once('../includes/db_connection.php'); // Make sure $pdo is created in this file

// **3. Initialize Variables**
$errors = [];
$success = "";
// Variables to retain form input values on error
$title_val = '';
$description_val = '';
$price_val = '';
$status_val = 'open'; // Default status

// **4. Handle Form Submission**
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retain input values
    $title_val = trim($_POST['title'] ?? '');
    $description_val = trim($_POST['description'] ?? '');
    $price_val = trim($_POST['price'] ?? '');
    $status_val = $_POST['status'] ?? 'open'; // Retain status

    // Sanitize and validate input data
    $title = htmlspecialchars($title_val, ENT_QUOTES, 'UTF-8');
    $price = filter_var($price_val, FILTER_VALIDATE_FLOAT); // Use float filter
    $description = htmlspecialchars($description_val, ENT_QUOTES, 'UTF-8');
    $status = in_array($status_val, ['open', 'closed']) ? $status_val : 'closed'; // Validate status

    // Basic Presence Checks
    if (empty($title)) $errors[] = "Venue title is required.";
    if (empty($description)) $errors[] = "Venue description is required.";
    if ($price === false) $errors[] = "Price must be a valid number.";
    elseif ($price <= 0) $errors[] = "Price must be greater than zero.";

    // --- Image Upload Handling ---
    $image_path = ""; // Will store the relative path for the DB
    // Use absolute path for PHP file operations (__DIR__ refers to current file's dir)
    $upload_dir = __DIR__ . "/../uploads/";
    // Relative path for DB/HTML src attribute (adjust if your file structure differs)
    $relative_upload_dir = "../uploads/";

    if (isset($_FILES["image"]) && $_FILES["image"]["error"] === UPLOAD_ERR_OK) {
        $image_tmp_name = $_FILES["image"]["tmp_name"];
        $image_size = $_FILES["image"]["size"];

        // Validate file size (e.g., max 5MB)
        $max_file_size = 5 * 1024 * 1024; // 5MB
        if ($image_size > $max_file_size) {
            $errors[] = "Image file is too large (Max: 5MB).";
        }

        // Validate MIME type and extension
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $image_tmp_name);
        finfo_close($finfo);
        $file_ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));

        if (!in_array($mime_type, $allowed_mimes) || !in_array($file_ext, $allowed_exts)) {
            $errors[] = "Invalid file type. Only JPG, PNG, GIF allowed.";
        }

        if (empty($errors)) { // Proceed only if validation passes so far
            // Create unique filename to prevent overwrites
            $image_name = "venue_" . uniqid('', true) . "." . $file_ext;
            $full_destination_path = $upload_dir . $image_name;
            $relative_path_for_db = $relative_upload_dir . $image_name; // Store this relative path

            // Create upload directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                // Use 0775 for permissions (owner/group write, others read/execute)
                if (!mkdir($upload_dir, 0775, true)) {
                    $errors[] = "Failed to create upload directory. Check server permissions.";
                    error_log("Failed to create directory: " . $upload_dir);
                }
            } elseif (!is_writable($upload_dir)) {
                 $errors[] = "Upload directory is not writable. Check server permissions.";
                 error_log("Upload directory not writable: " . $upload_dir);
            }

            // Move the uploaded file if directory is okay and writable
            if (empty($errors)) {
                if (move_uploaded_file($image_tmp_name, $full_destination_path)) {
                    $image_path = $relative_path_for_db; // Set the path for DB insert
                } else {
                    $errors[] = "Sorry, there was an error uploading your image. Check permissions or server logs.";
                    error_log("move_uploaded_file failed for: " . $image_tmp_name . " to " . $full_destination_path);
                }
            }
        }
    } elseif (isset($_FILES["image"]) && $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE) {
        // Handle other specific upload errors (permissions, partial upload, etc.)
        $errors[] = "File upload error code: " . $_FILES["image"]["error"] . ". Please try again or contact support.";
    } else {
        // Image is required
        $errors[] = "Venue image is required.";
    }

    // --- Database Insertion ---
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO venue (user_id, title, price, image_path, description, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())" // Add created_at automatically
               );
            $stmt->execute([$user_id, $title, $price, $image_path, $description, $status]);

            $new_venue_id = $pdo->lastInsertId();

            // Redirect to dashboard with a success flag and the new venue ID
            // Adjust the redirect path as needed
            header("Location: /ventech_locator/client_dashboard.php?new_venue=true&id=" . $new_venue_id);
            exit();

        } catch (PDOException $e) {
            error_log("Database Insert Error: " . $e->getMessage());
            $errors[] = "A database error occurred while adding the venue. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Venue</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Keep custom focus styles for a specific look if Tailwind's default isn't preferred */
        input:focus, textarea:focus, select:focus {
            border-color: #f59e0b; /* Amber 500 */
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.4); /* Amber focus ring, slightly adjusted alpha */
            outline: none;
        }
        /* Style file input label as button */
        .file-input-button {
            cursor: pointer;
            background-color: #4f46e5; /* Indigo 600 */
            color: white;
            padding: 0.6rem 1rem;
            border-radius: 9999px; /* Increased radius to pill shape */
            transition: background-color 0.2s;
            display: inline-flex; /* Use inline-flex */
            align-items: center;
            font-weight: 500; /* medium */
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* shadow-sm */
        }
        .file-input-button:hover {
            background-color: #4338ca; /* Indigo 700 */
        }
        .file-input-button i {
            margin-right: 0.5rem; /* mr-2 */
        }
        /* Visually hide the actual file input */
        .sr-only {
            position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
            overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border-width: 0;
        }

        /* --- Loading Overlay Styles --- */
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8); /* White with transparency */
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999; /* Ensure it's on top of everything */
            transition: opacity 0.3s ease-in-out;
            opacity: 0; /* Start hidden */
            visibility: hidden; /* Start hidden */
        }

        #loading-overlay.visible {
            opacity: 1;
            visibility: visible;
        }

        /* Loading Animation Styles */
        .loader-container {
            text-align: center;
        }

        .loader-pin {
            color: #ff6347; /* Orange color for the pin */
            font-size: 3rem; /* Adjust size as needed */
            margin-bottom: 10px;
        }

        .loader-bar {
            width: 200px; /* Width of the loading bar */
            height: 4px;
            background-color: #e0e0e0; /* Light gray track */
            border-radius: 2px;
            position: relative;
            margin: 0 auto; /* Center the bar */
        }

        .loader-indicator {
            width: 10px; /* Size of the moving dot */
            height: 10px;
            background-color: #ff6347; /* Orange dot */
            border-radius: 50%;
            position: absolute;
            top: -3px; /* Center vertically on the bar */
            left: 0;
            animation: moveIndicator 2s infinite ease-in-out; /* Animation */
        }

        /* Keyframes for the animation */
        @keyframes moveIndicator {
            0% { left: 0; }
            50% { left: calc(100% - 10px); } /* Move to the end of the bar */
            100% { left: 0; }
        }
        /* --- End Loading Overlay Styles --- */
    </style>
</head>
<body class="bg-gray-100">

    <div id="loading-overlay">
        <div class="loader-container">
            <i class="fas fa-map-marker-alt loader-pin"></i>
            <div class="loader-bar">
                <div class="loader-indicator"></div>
            </div>
        </div>
    </div>
    <header class="bg-white shadow-sm">
        <div class="max-w-5xl mx-auto py-4 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
            <h1 class="text-2xl font-semibold text-gray-800">Add New Venue</h1>
             <a href="/ventech_locator/client_dashboard.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 transition duration-150 ease-in-out">
                 <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
             </a>
        </div>
    </header>

    <div class="container mx-auto py-8 max-w-3xl px-4">

        <?php if ($success && $_SERVER['REQUEST_METHOD'] !== 'POST'): // Show success only on initial load after redirect ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-800 p-4 rounded mb-6 shadow-md flex items-start" role="alert">
                 <i class="fas fa-check-circle mr-3 mt-1 text-green-600"></i>
                 <div>
                     <p class="font-bold">Success!</p>
                     <p class="text-sm"><?= htmlspecialchars($success); ?></p>
                 </div>
            </div>
       <?php endif; ?>

       <?php if (!empty($errors)): ?>
           <div class="bg-red-100 border-l-4 border-red-500 text-red-800 p-4 rounded mb-6 shadow-md flex items-start" role="alert">
                <i class="fas fa-exclamation-triangle mr-3 mt-1 text-red-600"></i>
                <div>
                    <p class="font-bold mb-2">Please fix the following errors:</p>
                    <ul class="list-disc list-inside text-sm">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
           </div>
       <?php endif; ?>

        <div class="bg-white p-6 md:p-8 rounded-lg shadow-md">
            <form id="addVenueForm" method="POST" action="add_venue.php" enctype="multipart/form-data">

                <div class="mb-5">
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Venue Title</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($title_val); ?>" placeholder="e.g., The Grand Ballroom" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none transition duration-150 ease-in-out text-sm">
                </div>

                <div class="mb-5">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="description" name="description" placeholder="Describe the venue, its features, capacity, and suitability for events..." required
                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none transition duration-150 ease-in-out text-sm min-h-[100px]"><?= htmlspecialchars($description_val); ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-5">
                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700 mb-1">Price per Hour</label>
                        <div class="relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">₱</span>
                            </div>
                            <input type="number" id="price" name="price" value="<?= htmlspecialchars($price_val); ?>" placeholder="e.g., 5000.00" min="0.01" step="0.01" required
                                   class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none transition duration-150 ease-in-out text-sm">
                        </div>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Initial Status</label>
                        <select id="status" name="status" required
                                class="w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none transition duration-150 ease-in-out text-sm">
                            <option value="open" <?= ($status_val == 'open') ? 'selected' : ''; ?>>Open (Available for Booking)</option>
                            <option value="closed" <?= ($status_val == 'closed') ? 'selected' : ''; ?>>Closed (Not Available)</option>
                        </select>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Venue Image</label>
                    <div class="flex items-center">
                        <label class="file-input-button" for="image">
                             <i class="fas fa-upload"></i> Choose Image...
                        </label>
                        <input type="file" id="image" name="image" class="sr-only" accept="image/jpeg,image/png,image/gif" required>
                        <span id="fileName" class="ml-3 text-sm text-gray-600 truncate">No file chosen</span>
                     </div>
                    <div class="mt-3">
                        <img id="imagePreview" src="#" alt="Image Preview" class="hidden w-full max-w-sm h-auto object-contain rounded border bg-gray-50 p-1"/>
                    </div>
                     <p class="text-xs text-gray-500 mt-1">Required. Max 5MB. JPG, PNG, or GIF format.</p>
                </div>

                <div class="mt-8 pt-5 border-t border-gray-200">
                    <button type="submit"
                            class="block mx-auto max-w-xs flex justify-center items-center
                                   bg-orange-600 hover:bg-orange-700 text-white font-bold
                                   py-2.5 px-4 rounded-full shadow-md
                                   focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500
                                   transition duration-150 ease-in-out">
                        <i class="fas fa-plus-circle mr-2"></i> Add Venue
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // JavaScript to show the loading overlay on form submission and hide on page load

        document.addEventListener("DOMContentLoaded", function() {
            // Existing image preview logic
            const imageInput = document.getElementById('image');
            const imagePreview = document.getElementById('imagePreview');
            const fileNameSpan = document.getElementById('fileName'); // Get the span for the filename

            if (imageInput && imagePreview && fileNameSpan) {
                imageInput.addEventListener('change', function(event) {
                    const file = event.target.files[0];

                    if (file) {
                        // Display filename
                        fileNameSpan.textContent = file.name;

                        // Check if it's an image before creating preview
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();

                            reader.onload = function(e) {
                                imagePreview.src = e.target.result;
                                imagePreview.classList.remove('hidden'); // Show preview
                            }
                            reader.readAsDataURL(file);
                        } else {
                            // If not an image file, clear preview and potentially show an error
                            imagePreview.src = '#';
                            imagePreview.classList.add('hidden'); // Hide preview
                            // Optional: Maybe add a message indicating it's not a valid image type
                            // fileNameSpan.textContent = 'Invalid file type'; // Or keep the name
                        }
                    } else {
                        // No file selected
                        imagePreview.src = '#';
                        imagePreview.classList.add('hidden'); // Hide preview
                        fileNameSpan.textContent = 'No file chosen'; // Reset filename display
                    }
                });
            }

            // --- Loading Overlay JavaScript ---
            const addVenueForm = document.getElementById('addVenueForm'); // Get the form by its new ID
            const loadingOverlay = document.getElementById('loading-overlay'); // Get the loading overlay

            if (addVenueForm && loadingOverlay) {
                // Show loading overlay when the form is submitted
                addVenueForm.addEventListener('submit', function() {
                    loadingOverlay.classList.add('visible');
                });
            }
            // --- End Loading Overlay JavaScript ---
        });

        // Hide loading overlay when the page has fully loaded (including after form submission/redirect)
        window.addEventListener('load', function() {
            const loadingOverlay = document.getElementById('loading-overlay');
            if (loadingOverlay) {
                loadingOverlay.classList.remove('visible');
                // Optional: Remove the element from the DOM after transition
                loadingOverlay.addEventListener('transitionend', function() {
                     // Check if the overlay is actually hidden before removing
                    if (!loadingOverlay.classList.contains('visible')) {
                         loadingOverlay.remove();
                    }
                });
            }
        });
    </script>

</body>
</html>