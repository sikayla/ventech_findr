<?php
// Include the PDO database connection
include('db_connection.php');

// Get the venue ID from the URL
$venue_id = $_GET['id'] ?? null;

if (!$venue_id) {
    die("No venue ID provided.");
}

// Fetch venue data
$stmt = $pdo->prepare("SELECT * FROM venue WHERE id = ?");
$stmt->execute([$venue_id]);
$venue = $stmt->fetch();

if (!$venue) {
    die("Venue not found.");
}

// Display the success message
if (isset($_GET['message'])) {
    echo "<p class='text-green-500 text-center'>" . htmlspecialchars($_GET['message']) . "</p>";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate form inputs
    $amenities = trim($_POST['amenities']);
    $reviews = filter_var($_POST['reviews'], FILTER_VALIDATE_INT);
    $additional_info = trim($_POST['additional_info']);
    $unavailable_dates = $_POST['unavailable_dates'] ?? [];

    // Check for valid reviews
    if ($reviews === false || $reviews < 0) {
        die("Invalid reviews count.");
    }

    // Handle image upload
    if (isset($_FILES['venue_image']) && $_FILES['venue_image']['error'] === 0) {
        $image_tmp = $_FILES['venue_image']['tmp_name'];
        $image_name = time() . '_' . basename($_FILES['venue_image']['name']);
        $upload_dir = __DIR__ . '/../uploads/';
        
        // Check if upload directory exists, if not create it
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $image_path = $upload_dir . $image_name;

        // Validate file type
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $image_extension = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));

        if (!in_array($image_extension, $allowed_extensions)) {
            die("Invalid image format. Only JPG, JPEG, PNG, and GIF are allowed.");
        }

        // Validate file size (e.g., max 5MB)
        if ($_FILES['venue_image']['size'] > 5 * 1024 * 1024) {
            die("File size exceeds the 5MB limit.");
        }

        // Move the uploaded image to the designated directory
        if (!move_uploaded_file($image_tmp, $image_path)) {
            die("Failed to upload image.");
        }

        // Store the relative image path for the database
        $image_path= 'uploads/' . $image_name;
    } else {
        // Keep the existing image if no new image is uploaded
        $image_path = $venue['image_path'];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Make sure venue_id is available in the form submission
        if (isset($_POST['venue_id']) && !empty($_POST['venue_id'])) {
            $venue_id = $_POST['venue_id'];  // Retrieve venue_id from POST data
        } else {
            // If venue_id is not set, redirect or show an error
            header("Location: list_venues.php"); // Redirect if no venue ID
            exit;
        }
    
        // Process the form and retrieve other form data (example)
        $amenities = $_POST['amenities']; 
        $reviews = $_POST['reviews']; 
        $additional_info = $_POST['additional_info']; 
        $image_path = $_POST['image_path'];  // Assuming this is how you're passing the image path
    
        // Update the venue details in the database
        $update = $pdo->prepare("UPDATE venue SET amenities = ?, reviews = ?, additional_info = ?, image_path = ? WHERE id = ?");
        $update_success = $update->execute([$amenities, $reviews, $additional_info, $image_path, $venue_id]);
    
        if ($update_success) {
            echo "Venue details updated successfully.";
        } else {
            echo "Failed to update venue details.";
        }
    }


    // Insert unavailable dates if any
    if (!empty($unavailable_dates)) {
        $insert = $pdo->prepare("INSERT IGNORE INTO venue_availability (venue_id, date, available) VALUES (?, ?, 0)");
        
        foreach ($unavailable_dates as $date) {
            $insert->execute([$venue_id, $date]);
        }
    }

    // Redirect after successful update
    header("Location: client_dashboard.php?id=$venue_id&message=Venue details updated successfully.");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venue Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/multidatespicker@1.6.6/jquery-ui.multidatespicker.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
</head>
<body class="bg-white text-gray-700">
    <div class="max-w-5xl mx-auto p-4">
        <h1 class="text-center text-4xl font-light text-yellow-600 mb-8">Edit Venue Details</h1>

        <div class="mb-8">
            <h2 class="text-2xl text-yellow-600 mb-4"><?= htmlspecialchars($venue['title']) ?></h2>
            <p class="text-gray-600 mb-2">Price: ₱ <?= number_format($venue['price'], 2) ?>/Night</p>
            <img src="<?= htmlspecialchars($venue['image_path']) ?>" alt="Venue Image" class="w-full h-48 object-cover mb-4" />
        </div>

        <!-- Venue details form -->
        <form method="POST" action="venue_details.php?id=<?= $venue['id'] ?>" enctype="multipart/form-data">
            <input type="hidden" name="venue_id" value="<?= $venue['id'] ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <label for="amenities" class="block text-sm font-medium text-gray-700">Amenities</label>
                    <input type="text" id="amenities" name="amenities" class="border p-2 w-full" value="<?= htmlspecialchars($venue['amenities']) ?>" placeholder="E.g., Free Wi-Fi, Free Parking" required>
                </div>
                <div>
                    <label for="reviews" class="block text-sm font-medium text-gray-700">Reviews</label>
                    <input type="number" id="reviews" name="reviews" class="border p-2 w-full" value="<?= htmlspecialchars($venue['reviews']) ?>" placeholder="Number of Reviews" required>
                </div>
            </div>

            <div class="mt-4">
                <label for="additional_info" class="block text-sm font-medium text-gray-700">Additional Information</label>
                <textarea id="additional_info" name="additional_info" class="border p-2 w-full" rows="4" placeholder="Add additional details about your venue" required><?= htmlspecialchars($venue['additional_info']) ?></textarea>
            </div>

            <div class="mt-4">
                <label for="venue_image" class="block text-sm font-medium text-gray-700">Upload New Image</label>
                <input type="file" id="venue_image" name="venue_image" class="border p-2 w-full" accept="image/*">
            </div>

            <div class="mt-4">
                <label for="unavailable_dates" class="block text-sm font-medium text-gray-700">Select Unavailable Dates</label>
                <input type="text" id="unavailable_dates" name="unavailable_dates[]" class="border p-2 w-full" placeholder="Select unavailable dates" readonly>
            </div>

            <div class="mt-8 text-center">
                <button type="submit" class="bg-yellow-600 text-white py-2 px-4 rounded">Save Details</button>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script>
    $(function() {
        let selectedDates = [];

        $('#unavailable_dates').multiDatesPicker({
            dateFormat: 'yy-mm-dd',
            onSelect: function(dateText) {
                selectedDates = $('#unavailable_dates').multiDatesPicker('getDates');
                updateHiddenFields(selectedDates);
            }
        });

        function updateHiddenFields(dates) {
            // Remove existing hidden inputs
            $('.unavailable-date-input').remove();

            // Add hidden fields to form
            dates.forEach(date => {
                const input = $('<input>')
                    .attr('type', 'hidden')
                    .attr('name', 'unavailable_dates[]')
                    .attr('class', 'unavailable-date-input')
                    .val(date);
                $('form').append(input);
            });
        }
    });
</script>
</body>
</html>