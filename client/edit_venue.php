<?php
// --- PHP Code for Edit Venue ---

// **1. Start Session & Auth Check**
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: client_login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

// **2. Include Database Connection**
require_once('../includes/db_connection.php'); // Adjust path relative to edit_venue.php

// **3. Initialize Variables**
$errors = [];
// $success_message is no longer directly used for display on this page, as success redirects.
$venue_data = null; // Will store existing venue data

// Variables to retain form input values (pre-fill from DB or retain on error)
$venue_id = $_GET['id'] ?? null; // Get venue ID from URL
$title_val = '';
$description_val = '';
$price_val = '';
$status_val = 'open';
$current_image_path = ''; // To store the existing image path

// **4. Fetch Existing Venue Data (if ID is provided)**
if ($venue_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, user_id, title, description, price, status, image_path FROM venue WHERE id = ? AND user_id = ?");
        $stmt->execute([$venue_id, $user_id]);
        $venue_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$venue_data) {
            // Venue not found or not owned by the logged-in user
            $_SESSION['message'] = "Venue not found or you do not have permission to edit it.";
            $_SESSION['message_type'] = 'error';
            header("Location: /ventech_locator/client_dashboard.php"); // Redirect to dashboard
            exit;
        }

        // Populate form variables with existing data
        $title_val = $venue_data['title'];
        $description_val = $venue_data['description'];
        $price_val = $venue_data['price'];
        $status_val = $venue_data['status'];
        $current_image_path = $venue_data['image_path'];

    } catch (PDOException $e) {
        error_log("Database Fetch Error (edit_venue.php): " . $e->getMessage());
        $_SESSION['message'] = "A database error occurred while loading venue details.";
        $_SESSION['message_type'] = 'error';
        header("Location: /ventech_locator/client_dashboard.php");
        exit;
    }
} else {
    // No venue ID provided in URL
    $_SESSION['message'] = "No venue ID provided for editing.";
    $_SESSION['message_type'] = 'error';
    header("Location: /ventech_locator/client_dashboard.php");
    exit;
}


// **5. Handle Form Submission for Updates**
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['venue_id_hidden'])) {
    // Re-validate venue_id from hidden field
    $venue_id = $_POST['venue_id_hidden'];

    // Retain input values from POST
    $title_val = trim($_POST['title'] ?? '');
    $description_val = trim($_POST['description'] ?? '');
    $price_val = trim($_POST['price'] ?? '');
    $status_val = $_POST['status'] ?? 'open'; // Retain status
    $current_image_path = $_POST['current_image_path'] ?? ''; // Get current image path from hidden field

    // Sanitize and validate input data
    $title = htmlspecialchars($title_val, ENT_QUOTES, 'UTF-8');
    $price = filter_var($price_val, FILTER_VALIDATE_FLOAT);
    $description = htmlspecialchars($description_val, ENT_QUOTES, 'UTF-8');
    $status = in_array($status_val, ['open', 'closed']) ? $status_val : 'closed';

    // Basic Presence Checks
    if (empty($title)) $errors[] = "Venue title is required.";
    if (empty($description)) $errors[] = "Venue description is required.";
    if ($price === false) $errors[] = "Price must be a valid number.";
    elseif ($price <= 0) $errors[] = "Price must be greater than zero.";

    // --- Image Upload Handling for Update ---
    $new_image_path = $current_image_path; // Assume existing image unless new one is uploaded
    $upload_dir = __DIR__ . "/../uploads/";
    $relative_upload_dir = "../uploads/";

    if (isset($_FILES["image"]) && $_FILES["image"]["error"] === UPLOAD_ERR_OK) {
        $image_tmp_name = $_FILES["image"]["tmp_name"];
        $image_size = $_FILES["image"]["size"];

        $max_file_size = 5 * 1024 * 1024; // 5MB
        if ($image_size > $max_file_size) {
            $errors[] = "New image file is too large (Max: 5MB).";
        }

        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $image_tmp_name);
        finfo_close($finfo);
        $file_ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));

        if (!in_array($mime_type, $allowed_mimes) || !in_array($file_ext, $allowed_exts)) {
            $errors[] = "Invalid file type for new image. Only JPG, PNG, GIF allowed.";
        }

        if (empty($errors)) {
            $image_name = "venue_" . uniqid('', true) . "." . $file_ext;
            $full_destination_path = $upload_dir . $image_name;
            $new_image_path = $relative_upload_dir . $image_name;

            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0775, true)) {
                    $errors[] = "Failed to create upload directory. Check server permissions.";
                    error_log("Failed to create directory: " . $upload_dir);
                }
            } elseif (!is_writable($upload_dir)) {
                 $errors[] = "Upload directory is not writable. Check server permissions.";
                 error_log("Upload directory not writable: " . $upload_dir);
            }

            if (empty($errors)) {
                if (move_uploaded_file($image_tmp_name, $full_destination_path)) {
                    // New image uploaded successfully, delete old one if it exists and is not the placeholder
                    if (!empty($current_image_path) && file_exists(__DIR__ . "/../" . $current_image_path) && strpos($current_image_path, 'placeholder') === false) {
                        unlink(__DIR__ . "/../" . $current_image_path); // Delete old image
                    }
                } else {
                    $errors[] = "Sorry, there was an error uploading your new image.";
                    error_log("move_uploaded_file failed for new image: " . $image_tmp_name);
                }
            }
        }
    } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] === 'true') {
        // User explicitly chose to remove the image
        if (!empty($current_image_path) && file_exists(__DIR__ . "/../" . $current_image_path) && strpos($current_image_path, 'placeholder') === false) {
            unlink(__DIR__ . "/../" . $current_image_path); // Delete old image
        }
        $new_image_path = ''; // Set image path to empty in DB
    }


    // --- Database Update ---
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE venue
                 SET title = ?, price = ?, image_path = ?, description = ?, status = ?, updated_at = NOW()
                 WHERE id = ? AND user_id = ?"
            );
            $stmt->execute([$title, $price, $new_image_path, $description, $status, $venue_id, $user_id]);

            // Set session message for client_dashboard.php to display
            $_SESSION['message'] = "Venue details updated successfully!";
            $_SESSION['message_type'] = 'success';
            // Redirect back to dashboard with a success flag
            header("Location: /ventech_locator/client_dashboard.php?venue_updated=true");
            exit();

        } catch (PDOException $e) {
            error_log("Database Update Error (edit_venue.php): " . $e->getMessage());
            $errors[] = "A database error occurred while updating the venue. Please try again later.";
        }
    }
}

// Path for venue_display.php and client_dashboard.php
$venueDisplayPath = '/ventech_locator/venue_display.php';
$clientDashboardPath = '/ventech_locator/client_dashboard.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Venue - <?= htmlspecialchars($title_val ?: 'Venue') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        input:focus, textarea:focus, select:focus {
            border-color: #f59e0b; /* Amber 500 */
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.4); /* Amber focus ring */
            outline: none;
        }
        .file-input-button {
            cursor: pointer;
            background-color: #4f46e5; /* Indigo 600 */
            color: white;
            padding: 0.6rem 1rem;
            border-radius: 0.375rem;
            transition: background-color 0.2s;
            display: inline-flex;
            align-items: center;
            font-weight: 500;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .file-input-button:hover {
            background-color: #4338ca; /* Indigo 700 */
        }
        .file-input-button i {
            margin-right: 0.5rem;
        }
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
            <h1 class="text-2xl font-semibold text-gray-800">Edit Venue: <?= htmlspecialchars($title_val); ?></h1>
            <a href="<?= htmlspecialchars($clientDashboardPath); ?>" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 transition duration-150 ease-in-out">
                <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
            </a>
        </div>
    </header>

    <div class="container mx-auto py-8 max-w-3xl px-4">

        <?php
        // This block will now only display messages that are explicitly set before a redirect
        // or if there are validation errors on the current page.
        // Success messages will be handled by client_dashboard.php after redirect.
        if (isset($_SESSION['message'])) {
            $message = $_SESSION['message'];
            $message_type = $_SESSION['message_type'] ?? 'info';
            // IMPORTANT: Unset the session message *after* displaying it to prevent it from reappearing
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);

            $alert_class = '';
            $icon_class = '';
            $strong_text = '';

            switch ($message_type) {
                case 'success':
                    // This case should ideally not be hit if success messages are handled by redirect target
                    $alert_class = 'bg-green-100 border-green-500 text-green-800';
                    $icon_class = 'fas fa-check-circle text-green-600';
                    $strong_text = 'Success!';
                    break;
                case 'error':
                    $alert_class = 'bg-red-100 border-red-500 text-red-800';
                    $icon_class = 'fas fa-exclamation-triangle text-red-600';
                    $strong_text = 'Error:';
                    break;
                case 'warning':
                    $alert_class = 'bg-yellow-100 border-yellow-500 text-yellow-800';
                    $icon_class = 'fas fa-exclamation-circle text-yellow-600';
                    $strong_text = 'Warning:';
                    break;
                case 'info':
                default:
                    $alert_class = 'bg-blue-100 border-blue-500 text-blue-800';
                    $icon_class = 'fas fa-info-circle text-blue-600';
                    $strong_text = 'Information:';
                    break;
            }
            echo "<div class='{$alert_class} p-4 rounded mb-6 shadow-md flex items-start' role='alert'>";
            echo "<i class='{$icon_class} mr-3 mt-1'></i>";
            echo "<div><p class='font-bold'>{$strong_text}</p><p class='text-sm'>{$message}</p></div>";
            echo "</div>";
        }
        ?>

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
            <form id="editVenueForm" method="POST" action="edit_venue.php?id=<?= htmlspecialchars($venue_id) ?>" enctype="multipart/form-data">
                <input type="hidden" name="venue_id_hidden" value="<?= htmlspecialchars($venue_id) ?>">
                <input type="hidden" name="current_image_path" value="<?= htmlspecialchars($current_image_path) ?>">

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
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" required
                                class="w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none transition duration-150 ease-in-out text-sm">
                            <option value="open" <?= ($status_val == 'open') ? 'selected' : ''; ?>>Open (Available for Booking)</option>
                            <option value="closed" <?= ($status_val == 'closed') ? 'selected' : ''; ?>>Closed (Not Available)</option>
                        </select>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Venue Image</label>
                    <?php
                        $display_image_src = 'https://placehold.co/400x250/fbbf24/ffffff?text=No+Image';
                        if (!empty($current_image_path)) {
                            // Adjust path for display in HTML (relative to web root)
                            $display_image_src = '/ventech_locator/' . ltrim(htmlspecialchars($current_image_path), '/');
                        }
                    ?>
                    <div class="mb-3">
                        <img id="imagePreview" src="<?= $display_image_src ?>" alt="Current Image Preview" class="w-full max-w-sm h-auto object-contain rounded border bg-gray-50 p-1 <?= empty($current_image_path) ? 'hidden' : '' ?>"/>
                    </div>

                    <div class="flex items-center space-x-3">
                        <label class="file-input-button" for="image">
                             <i class="fas fa-upload"></i> Choose New Image...
                        </label>
                        <input type="file" id="image" name="image" class="sr-only" accept="image/jpeg,image/png,image/gif">
                        <span id="fileName" class="text-sm text-gray-600 truncate"><?= !empty($current_image_path) ? basename($current_image_path) : 'No file chosen' ?></span>
                        <?php if (!empty($current_image_path)): ?>
                            <button type="button" id="removeImageBtn" class="text-red-600 hover:text-red-800 text-sm font-medium">
                                <i class="fas fa-trash-alt mr-1"></i> Remove Current
                            </button>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="remove_image" id="removeImageHidden" value="false">
                    <p class="text-xs text-gray-500 mt-1">Optional. Max 5MB. JPG, PNG, or GIF format. Uploading a new image will replace the current one.</p>
                </div>

                <div class="mt-8 pt-5 border-t border-gray-200">
                    <button type="submit"
                            class="w-full flex justify-center items-center bg-blue-500 hover:bg-blue-600 text-white font-bold py-2.5 px-4 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                        <i class="fas fa-save mr-2"></i> Update Venue
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const imageInput = document.getElementById('image');
            const imagePreview = document.getElementById('imagePreview');
            const fileNameSpan = document.getElementById('fileName');
            const removeImageBtn = document.getElementById('removeImageBtn');
            const removeImageHidden = document.getElementById('removeImageHidden');

            function updateImagePreview(file) {
                if (file) {
                    fileNameSpan.textContent = file.name;
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            imagePreview.src = e.target.result;
                            imagePreview.classList.remove('hidden');
                        }
                        reader.readAsDataURL(file);
                    } else {
                        imagePreview.src = '#';
                        imagePreview.classList.add('hidden');
                    }
                } else {
                    imagePreview.src = '#';
                    imagePreview.classList.add('hidden');
                    fileNameSpan.textContent = 'No file chosen';
                }
            }

            if (imageInput) {
                imageInput.addEventListener('change', function(event) {
                    updateImagePreview(event.target.files[0]);
                    if (removeImageHidden) {
                        removeImageHidden.value = 'false'; // If new image is selected, don't remove old one
                    }
                });
            }

            if (removeImageBtn && removeImageHidden) {
                removeImageBtn.addEventListener('click', function() {
                    // Removed the `confirm` dialog as per instructions to avoid `confirm()`
                    imageInput.value = ''; // Clear the file input
                    updateImagePreview(null); // Clear preview
                    removeImageHidden.value = 'true'; // Set flag to remove image in PHP
                    this.style.display = 'none'; // Hide remove button
                });
            }

            // --- Loading Overlay JavaScript ---
            const editVenueForm = document.getElementById('editVenueForm');
            const loadingOverlay = document.getElementById('loading-overlay');

            if (editVenueForm && loadingOverlay) {
                editVenueForm.addEventListener('submit', function() {
                    loadingOverlay.classList.add('visible');
                });
            }

            window.addEventListener('load', function() {
                const loadingOverlay = document.getElementById('loading-overlay');
                if (loadingOverlay) {
                    loadingOverlay.classList.remove('visible');
                    loadingOverlay.addEventListener('transitionend', function() {
                        if (!loadingOverlay.classList.contains('visible')) {
                            loadingOverlay.remove();
                        }
                    });
                }
            });
        });
    </script>

</body>
</html>
