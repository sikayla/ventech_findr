<?php
// error_reporting(E_ALL); // Uncomment for debugging
// ini_set('display_errors', 1); // Uncomment for debugging

// **1. Start Session (Conditionally)**
// Start session only if not already started and not included in another script that starts it.
if (session_status() == PHP_SESSION_NONE && !defined('IS_INCLUDED_DASHBOARD')) {
    session_start();
}

$date_format_error = "";
$past_date_error = ''; // Initialize error message variable
$errors = []; // Initialize errors array
$success_message = '';
$form_data = $_POST ?? []; // Use data from POST if available (for repopulating form on error)
$pdo = null; // Initialize PDO variable

// **2. Database Connection Parameters**
$host = 'localhost';
$db   = 'ventech_db'; // Your database name
$user_db = 'root'; // Your database username
$pass = ''; // Your database password - Consider using environment variables or config files
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
      PDO  ::ATTR_ERRMODE            =>   PDO  ::ERRMODE_EXCEPTION,
      PDO  ::ATTR_DEFAULT_FETCH_MODE =>   PDO  ::FETCH_ASSOC,
      PDO  ::ATTR_EMULATE_PREPARES   => false,
];

// **3. Get Data from Previous Page (GET Request)**
$venue_id = isset($_GET['venue_id']) ? intval($_GET['venue_id']) : 0;
$venue_name_from_get = isset($_GET['venue_name']) ? trim(htmlspecialchars($_GET['venue_name'])) : 'Selected Venue'; // Get venue name for display
$event_date_from_get = isset($_GET['event_date']) ? trim($_GET['event_date']) : '';

// **4. Validate Venue ID**
if ($venue_id <= 0 && !defined('IS_INCLUDED_DASHBOARD')) {
    // If accessed directly without a valid venue_id
    $errors['general'] = "No valid venue selected. Please go back and choose a venue.";
    error_log("Venue ID missing or invalid when accessing reservation form directly.");
    // Prevent further execution if critical info is missing when run standalone
    // Note: If included, we might rely on the including script to handle this.
    // For standalone, you might want to die() or redirect here.
}

// **5. Validate Date Format (YYYY-MM-DD) from GET**
if ($event_date_from_get && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date_from_get)) {
    error_log("Invalid date format received from GET: " . $event_date_from_get);
    $event_date_from_get = ''; // Clear invalid date
    $date_format_error = "An invalid date format was received. Please select the date again.";
}

// **6. Check if Date from GET is in the Past**
$today = date('Y-m-d');
if ($event_date_from_get && $event_date_from_get < $today) {
    error_log("Attempt to pre-fill form with past date from GET: " . $event_date_from_get);
    $past_date_error = "The date selected previously (" . htmlspecialchars($event_date_from_get) . ") is in the past. Please choose a future date.";
    $event_date_from_get = ''; // Clear the date
}

// **7. Fetch Venue Details (Price, Title, Image)**
$venue_price_per_hour = 0;
$venue_title = $venue_name_from_get; // Default to name from GET
$venue_img_src = 'https://placehold.co/150x150/e2e8f0/64748b?text=No+Venue'; // Default placeholder

if ($venue_id > 0) { // Only try to fetch if we have a valid ID
    try {
        $pdo = new   PDO  ($dsn, $user_db, $pass, $options);

        $stmt_venue = $pdo->prepare("SELECT title, price, image_path FROM venue WHERE id = :venue_id");
        $stmt_venue->bindParam(':venue_id', $venue_id,   PDO  ::PARAM_INT);
        $stmt_venue->execute();
        $venue_details = $stmt_venue->fetch(  PDO  ::FETCH_ASSOC);

        if ($venue_details) {
            $venue_price_per_hour = (  float  ) $venue_details['price'];
            $venue_title = htmlspecialchars($venue_details['title']);
            $venue_image_path = $venue_details['image_path'];

            // Construct image path
            $uploadsBaseUrl = '/ventech_locator/uploads/'; // *** ADJUST THIS PATH IF NEEDED ***
            $placeholderImg = 'https://placehold.co/150x150/e2e8f0/64748b?text=No+Image';
            $venue_img_src = $placeholderImg; // Default to placeholder

            if (!empty($venue_image_path)) {
                if (filter_var($venue_image_path, FILTER_VALIDATE_URL)) {
                    $venue_img_src = htmlspecialchars($venue_image_path); // Use URL directly
                } else {
                    // Construct full path assuming relative path from uploadsBaseUrl
                    // Ensure DOCUMENT_ROOT is set and reliable in your environment
                    $potential_file_path = ($uploadsBaseUrl . ltrim($venue_image_path, '/'));
                    // Basic check if file might exist - more robust checks might be needed
                     // Use relative path if needed, adjust based on server setup
                     $venue_img_src = htmlspecialchars($potential_file_path);

                     // Optional: Check if file exists physically (can be slow, use with caution)
                     /*
                     if (isset($_SERVER['DOCUMENT_ROOT'])) {
                          $full_physical_path = $_SERVER['DOCUMENT_ROOT'] . $potential_file_path;
                          if (file_exists($full_physical_path)) {
                              $venue_img_src = htmlspecialchars($potential_file_path);
                          } else {
                              error_log("Venue image file not found: " . $full_physical_path);
                          }
                     } else {
                          // If document root not available, assume path is correct or use placeholder
                           $venue_img_src = htmlspecialchars($potential_file_path); // Or keep placeholder
                     }
                     */
                }
            }
        } else {
            $errors['general'] = $errors['general'] ?? "Error: Venue with ID $venue_id not found."; // Append if general error already exists
            error_log("Venue ID $venue_id provided but not found in database.");
            $venue_title = "Venue Not Found";
            $venue_price_per_hour = 0;
            $venue_img_src = 'https://placehold.co/150x150/ef4444/ffffff?text=Not+Found';
        }

    } catch (  PDOException   $e) {
        error_log("Database error fetching venue details (ID: $venue_id): " . $e->getMessage());
        $errors['general'] = $errors['general'] ?? "Could not load venue details due to a database error.";
        $venue_title = "Error Loading Venue";
        $venue_price_per_hour = 0;
        $venue_img_src = 'https://placehold.co/150x150/ef4444/ffffff?text=DB+Error';
    }
    // Connection will be closed later or reused for user fetch
} else {
    // If no venue_id was provided via GET
    $venue_title = "No Venue Selected";
    $venue_price_per_hour = 0;
    if (!isset($errors['general']) && !defined('IS_INCLUDED_DASHBOARD')) {
        $errors['general'] = "No venue selected. Please go back and choose a venue.";
    }
}

// **8. Get User ID from Session and Fetch User Details for Pre-filling**
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
$user_details = null;

if ($user_id && $venue_id > 0) { // Only fetch user if logged in and venue is valid
    try {
        if (!$pdo) { // Create new connection if not already established
             $pdo = new   PDO  ($dsn, $user_db, $pass, $options);
        }

        // Fetch details needed for pre-filling form from 'users' table
        $stmt_user = $pdo->prepare("SELECT client_name, email, contact_number, client_address, location FROM users WHERE id = :user_id");
        $stmt_user->bindParam(':user_id', $user_id,   PDO  ::PARAM_INT);
        $stmt_user->execute();
        $user_details = $stmt_user->fetch(  PDO  ::FETCH_ASSOC);

    } catch (  PDOException   $e) {
        error_log("Database error fetching user details (ID: $user_id): " . $e->getMessage());
        // Don't show error to user, just proceed without pre-filling
    }
}

// **9. Close PDO Connection** (Done fetching initial data)
$pdo = null;


// **10. Form Submission Handling (Logic Resides in reservation_manage.php)**
// This script only displays the form. The form action points to reservation_manage.php.
// $errors and $form_data might be populated if reservation_manage.php redirects back here on validation failure.

// **11. Determine Default Values for Form Fields**
// Prioritize submitted data (if errors occurred), then logged-in user data, then GET data (for date), then empty
function   get_value($field_name, $default = '') {
    global $form_data, $user_details;
    if (!empty($form_data[$field_name])) {
        return htmlspecialchars($form_data[$field_name]);
    }
    // Map user details to form fields for pre-filling
    if ($user_details) {
        switch ($field_name) {
            case 'first_name': return htmlspecialchars($user_details['client_name'] ?? ''); // Use client_name for first_name
            case 'last_name': return ''; // No last name field in users table
            case 'email': return htmlspecialchars($user_details['email'] ?? '');
            case 'mobile_number': return htmlspecialchars($user_details['contact_number'] ?? '');
             case 'address': return htmlspecialchars($user_details['client_address'] ?? $user_details['location'] ?? ''); // Prefer client_address, fallback to location
            // case 'country': return htmlspecialchars($user_details['country'] ?? 'Philippines'); // Default to Philippines if not set
            // Add more mappings if needed
        }
    }
    return htmlspecialchars($default);
}

$event_date_value_for_input = get_value('event_date', $event_date_from_get);
$start_time_value = get_value('start_time');
$end_time_value = get_value('end_time');
$first_name_value = get_value('first_name');
$last_name_value = get_value('last_name');
$email_value = get_value('email');
$mobile_code_value = get_value('mobile_country_code', '+63'); // Default PH code
$mobile_num_value = get_value('mobile_number');
$address_value = get_value('address');
$country_value = get_value('country', 'Philippines'); // Default Philippines
$notes_value = get_value('notes');
$voucher_value = get_value('voucher_code');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserve: <?= htmlspecialchars($venue_title); ?> - Ventech Locator</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/ventech_locator/css/venue_reservation_form.css">
    <style>
       
    </style>
</head>
<body>

    <div class="main-container">
        <div class="content-container">
            <div class="form-and-summary-column">
                 <?php if ($venue_id > 0): ?>
                    <a href="venue_display.php?id=<?= $venue_id ?>" class="back-link">
                        <i class="fas fa-chevron-left"></i> Back to Venue Details
                    </a>
                <?php else: ?>
                     <a href="index.php" class="back-link"> <i class="fas fa-chevron-left"></i> Back to Venues List
                    </a>
                <?php endif; ?>

                <h1 class="text-lg font-semibold mb-1">
                    Reservation Request
                </h1>
                <p class="text-xs text-gray-400 mb-8 leading-tight">
                    Book your event at <span class="text-orange-400"><?= htmlspecialchars($venue_title); ?></span>.
                    <br/>
                    Fill out the details below to submit your request.
                </p>

                <?php // Display general errors, past date errors, date format errors at the top
                if (!empty($errors['general']) || $past_date_error || $date_format_error): ?>
                    <div class="bg-red-900 bg-opacity-20 border-l-4 border-red-500 text-red-300 p-4 rounded-md relative mb-6 shadow-md text-xs" role="alert">
                        <strong class="font-bold block mb-1"><i class="fas fa-exclamation-triangle mr-2"></i>Please Note:</strong>
                        <ul class="list-disc list-inside text-xs space-y-1">
                            <?php if (!empty($errors['general'])): ?>
                                <li><?= htmlspecialchars($errors['general']); ?></li>
                            <?php endif; ?>
                            <?php if ($past_date_error): ?>
                                <li><?= htmlspecialchars($past_date_error); ?></li>
                            <?php endif; ?>
                             <?php if ($date_format_error): ?>
                                <li><?= htmlspecialchars($date_format_error); ?></li>
                            <?php endif; ?>
                             <?php // Display specific field errors from $errors array if needed, though they are also shown below fields ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php // Display success message if reservation_manage.php redirects back with one
                if ($success_message): ?>
                    <div class="bg-green-900 bg-opacity-20 border-l-4 border-green-500 text-green-300 p-4 rounded-md relative mb-6 shadow-md text-xs" role="alert">
                        <strong class="font-bold block mb-1"><i class="fas fa-check-circle mr-2"></i>Success!</strong>
                        <span class="block text-xs"><?= htmlspecialchars($success_message); ?></span>
                        <?php if ($user_id): // Only show dashboard link if user is logged in ?>
                        <p class="mt-2 text-xs">You can view the status of your request on your <a href="client_dashboard.php" class="font-medium underline hover:text-green-400">dashboard</a>.</p>
                        <?php endif; ?>
                    </div>
                <?php else: // Show the form only if there's no success message and no fatal error preventing form display ?>

                    <?php if ($venue_id > 0 && $venue_price_per_hour >= 0) : // Only show form sections if venue details are valid ?>

                     <div id="reservation-summary" class="form-section mb-6 hidden">
                         <h2 class="form-section-title flex justify-between items-center">
                             <span><i class="fas fa-receipt mr-2 text-orange-500"></i>Reservation Summary</span>
                             <span class="text-sm font-normal text-gray-500">Estimated Cost</span>
                         </h2>
                         <div class="flex flex-col sm:flex-row items-start sm:items-center mb-4">
                             <img id="summary-venue-image" src="<?= htmlspecialchars($venue_img_src) ?>" alt="<?= htmlspecialchars($venue_title) ?>" class="w-16 h-16 object-cover rounded mr-4 mb-3 sm:mb-0 shadow flex-shrink-0" onerror="this.onerror=null; this.src='https://placehold.co/64x64/2a2a2a/d1d5db?text=Img';">
                             <div class="flex-grow text-xs">
                                 <h3 id="summary-venue-name" class="font-semibold text-sm text-gray-100"><?= htmlspecialchars($venue_title) ?></h3>
                                 <p class="text-xs text-gray-400">Price per hour: <span id="summary-venue-price" data-price="<?= $venue_price_per_hour ?>">₱ <?= number_format($venue_price_per_hour, 2) ?></span></p>
                             </div>
                         </div>
                         <div class="grid grid-cols-1 gap-y-2 text-xs">
                             <div><span class="font-semibold text-gray-400">Date:</span> <span id="summary-event-date" class="text-gray-100">--</span></div>
                             <div><span class="font-semibold text-gray-400">Start:</span> <span id="summary-start-time" class="text-gray-100">--</span></div>
                             <div><span class="font-semibold text-gray-400">End:</span> <span id="summary-end-time" class="text-gray-100">--</span></div>
                         </div>
                         <hr class="my-3 border-gray-700">
                         <p class="text-base font-semibold text-right">Estimated Total: <span id="summary-total-cost" class="text-orange-500">₱ 0.00</span></p>
                         <p id="summary-error" class="text-red-400 text-xs text-right mt-1"></p>
                     </div>

                     <form id="reservationForm" novalidate class="space-y-6 text-xs font-normal">
                     <input type="hidden" name="venue_id" value="<?php echo htmlspecialchars($venue_id); ?>">
                     <input type="hidden" name="venue_name" value="<?php echo htmlspecialchars($venue_title); ?>">
                         <?php if ($user_id): ?>
                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($user_id) ?>">
                         <?php endif; ?>

                        <div class="form-section">
                            <h2 class="form-section-title"><i class="fas fa-calendar-alt mr-2 text-indigo-400"></i>Event Details</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label for="event-date" class="block text-sm font-medium text-gray-400 mb-1 uppercase tracking-wide">Event Date*</label>
                                    <div class="input-icon-container">
                                        <i class="fas fa-calendar-day input-icon text-gray-500 text-xs"></i>
                                        <input type="date" id="event-date" name="event_date"
                                               min="<?= $today ?>"
                                               class="block w-full pl-10 rounded bg-[#1a1a1a] text-gray-400 text-xs border-gray-700 shadow-sm focus:border-orange-500 focus:ring focus:ring-orange-300 focus:ring-opacity-50 <?= isset($errors['event_date']) ? 'border-red-500' : '' ?>"
                                               value="<?= $event_date_value_for_input ?>" required aria-describedby="event-date-error">
                                    </div>
                                    <?php if (isset($errors['event_date'])): ?><p id="event-date-error" class="error-message"><?= htmlspecialchars($errors['event_date']); ?></p><?php endif; ?>
                                    <p id="date-availability-msg" class="text-xs text-red-400 mt-1"></p>
                                </div>
                                <div>
                                    <label for="start-time" class="block text-sm font-medium text-gray-400 mb-1 uppercase tracking-wide">Start time*</label>
                                    <div class="input-icon-container">
                                        <i class="fas fa-clock input-icon text-gray-500 text-xs"></i>
                                        <input type="time" id="start-time" name="start_time" step="1800" class="block w-full pl-10 rounded bg-[#1a1a1a] text-gray-400 text-xs border-gray-700 shadow-sm focus:border-orange-500 focus:ring focus:ring-orange-300 focus:ring-opacity-50 <?= isset($errors['start_time']) ? 'border-red-500' : '' ?>"
                                               value="<?= $start_time_value ?>" required aria-describedby="start-time-error">
                                    </div>
                                    <?php if (isset($errors['start_time'])): ?><p id="start-time-error" class="error-message"><?= htmlspecialchars($errors['start_time']); ?></p><?php endif; ?>
                                </div>
                                <div>
                                    <label for="end-time" class="block text-sm font-medium text-gray-400 mb-1 uppercase tracking-wide">End time*</label>
                                    <div class="input-icon-container">
                                        <i class="fas fa-hourglass-end input-icon text-gray-500 text-xs"></i>
                                        <input type="time" id="end-time" name="end_time" step="1800" class="block w-full pl-10 rounded bg-[#1a1a1a] text-gray-400 text-xs border-gray-700 shadow-sm focus:border-orange-500 focus:ring focus:ring-orange-300 focus:ring-opacity-50 <?= isset($errors['end_time']) ? 'border-red-500' : '' ?>"
                                               value="<?= $end_time_value ?>" required aria-describedby="end-time-error time-validation-error">
                                    </div>
                                     <?php if (isset($errors['end_time'])): ?><p id="end-time-error" class="error-message"><?= htmlspecialchars($errors['end_time']); ?></p><?php endif; ?>
                                     <p id="time-validation-error" class="error-message"></p> </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h2 class="form-section-title"><i class="fas fa-user-circle mr-2 text-indigo-400"></i>Contact Information</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="first-name" class="block text-sm font-medium text-gray-400 mb-1 uppercase tracking-wide">First name*</label>
                                    <div class="input-icon-container">
                                        <i class="fas fa-user input-icon text-gray-500 text-xs"></i>
                                        <input type="text" id="first-name" name="first_name" autocomplete="given-name"
                                               class="block w-full pl-10 rounded bg-[#1a1a1a] text-gray-400 text-xs border-gray-700 shadow-sm focus:border-orange-500 focus:ring focus:ring-orange-300 focus:ring-opacity-50 <?= isset($errors['first_name']) ? 'border-red-500' : '' ?>"
                                               value="<?= $first_name_value ?>" required aria-describedby="first-name-error">
                                    </div>
                                    <?php if (isset($errors['first_name'])): ?><p id="first-name-error" class="error-message"><?= htmlspecialchars($errors['first_name']); ?></p><?php endif; ?>
                                </div>
                                <div>
                                    <label for="last-name" class="block text-sm font-medium text-gray-400 mb-1 uppercase tracking-wide">Last name*</label>
                                    <div class="input-icon-container">
                                        <i class="fas fa-user input-icon text-gray-500 text-xs"></i>
                                        <input type="text" id="last-name" name="last_name" autocomplete="family-name"
                                               class="block w-full pl-10 rounded bg-[#1a1a1a] text-gray-400 text-xs border-gray-700 shadow-sm focus:border-orange-500 focus:ring focus:ring-orange-300 focus:ring-opacity-50 <?= isset($errors['last_name']) ? 'border-red-500' : '' ?>"
                                               value="<?= $last_name_value ?>" required aria-describedby="last-name-error">
                                    </div>
                                    <?php if (isset($errors['last_name'])): ?><p id="last-name-error" class="error-message"><?= htmlspecialchars($errors['last_name']); ?></p><?php endif; ?>
                                </div>
                                <div class="md:col-span-2">
                                    <label for="email" class="block text-sm font-medium text-gray-400 mb-1 uppercase tracking-wide">Email address*</label>
                                    <div class="input-icon-container">
                                        <i class="fas fa-envelope input-icon text-gray-500 text-xs"></i>
                                        <input type="email" id="email" name="email" autocomplete="email"
                                               class="block w-full pl-10 rounded bg-[#1a1a1a] text-gray-400 text-xs border-gray-700 shadow-sm focus:border-orange-500 focus:ring focus:ring-orange-300 focus:ring-opacity-50 <?= isset($errors['email']) ? 'border-red-500' : '' ?>"
                                               value="<?= $email_value ?>" required aria-describedby="email-error">
                                    </div>
                                     <?php if (isset($errors['email'])): ?><p id="email-error" class="error-message"><?= htmlspecialchars($errors['email']); ?></p><?php endif; ?>
                                </div>
                                <div class="md:col-span-2">
                                    <label for="mobile" class="block text-sm font-medium text-gray-400 mb-1 uppercase tracking-wide">Mobile Number</label>
                                    <div class="flex mobile-group">
                                        <span class="rounded-l bg-[#1a1a1a] text-gray-400 text-xs border border-gray-700 px-3 py-2 flex items-center gap-1">
                                             <i class="fas fa-phone-alt"></i>
                                             <select id="mobile-country-code" name="mobile_country_code" autocomplete="tel-country-code" class="bg-transparent border-none focus:ring-0 focus:border-transparent text-gray-400 p-0 m-0" aria-label="Country code">
                                                 <option value="+63" <?= ($mobile_code_value == '+63') ? 'selected' : ''; ?>>+63</option>
                                                 <option value="+1" <?= ($mobile_code_value == '+1') ? 'selected' : ''; ?>>+1</option>
                                                 <option value="+44" <?= ($mobile_code_value == '+44') ? 'selected' : ''; ?>>+44</option>
                                                 <option value="+61" <?= ($mobile_code_value == '+61') ? 'selected' : ''; ?>>+61</option>
                                                 <option value="+65" <?= ($mobile_code_value == '+65') ? 'selected' : ''; ?>>+65</option>
                                             </select>
                                        </span>
                                        <div class="input-icon-container flex-grow">
                                            <input type="tel" id="mobile" name="mobile_number" autocomplete="tel-national"
                                                   class="block w-full rounded-r bg-[#1a1a1a] text-gray-400 text-xs border border-gray-700 shadow-sm focus:border-orange-500 focus:ring focus:ring-orange-300 focus:ring-opacity-50 <?= isset($errors['mobile_number']) ? 'border-red-500' : '' ?>"
                                                   value="<?= $mobile_num_value ?>" placeholder="Enter your phone number" aria-describedby="mobile-number-error">
                                        </div>
                                    </div>
                                     <?php if (isset($errors['mobile_number'])): ?><p id="mobile-number-error" class="error-message"><?= htmlspecialchars($errors['mobile_number']); ?></p><?php endif; ?>
                                </div>
                                <div class="md:col-span-2">
                                    <label for="address" class="block text-sm font-medium text-gray-400 mb-1 uppercase tracking-wide">Address</label>
                                    <div class="input-icon-container">
                                        <i class="fas fa-map-marker-alt input-icon text-gray-500 text-xs"></i>
                                        <input type="text" id="address" name="address" autocomplete="street-address"
                                               class="block w-full pl-10 rounded bg-[#1a1a1a] text-gray-400 text-xs border-gray-700 shadow-sm focus:border-orange-500 focus:ring focus:ring-orange-300 focus:ring-opacity-50 <?= isset($errors['address']) ? 'border-red-500' : '' ?>"
                                               value="<?= $address_value ?>" aria-describedby="address-error">
                                    </div>
                                     <?php if (isset($errors['address'])): ?><p id="address-error" class="error-message"><?= htmlspecialchars($errors['address']); ?></p><?php endif; ?>
                                </div>
                                <div class="md:col-span-2">
                                    <label for="country" class="block text-sm font-medium text-gray-400 mb-1 uppercase tracking-wide">Country</label>
                                    <div class="input-icon-container">
                                        <i class="fas fa-globe-asia input-icon text-gray-500 text-xs"></i>
                                        <select id="country" name="country" autocomplete="country-name" class="block w-full pl-10 rounded bg-[#1a1a1a] text-gray-400 text-xs border-gray-700 shadow-sm focus:border-orange-500 focus:ring focus:ring-orange-300 focus:ring-opacity-50 <?= isset($errors['country']) ? 'border-red-500' : '' ?>" aria-describedby="country-error">
                                            <option value="Philippines" <?= ($country_value == 'Philippines') ? 'selected' : ''; ?>>Philippines</option>
                                            <option value="USA" <?= ($country_value == 'USA') ? 'selected' : ''; ?>>USA</option>
                                            <option value="Singapore" <?= ($country_value == 'Singapore') ? 'selected' : ''; ?>>Singapore</option>
                                            <option value="Australia" <?= ($country_value == 'Australia') ? 'selected' : ''; ?>>Australia</option>
                                            <option value="United Kingdom" <?= ($country_value == 'United Kingdom') ? 'selected' : ''; ?>>United Kingdom</option>
                                            </select>
                                    </div>
                                     <?php if (isset($errors['country'])): ?><p id="country-error" class="error-message"><?= htmlspecialchars($errors['country']); ?></p><?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h2 class="form-section-title"><i class="fas fa-info-circle mr-2 text-indigo-400"></i>Additional Information</h2>
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label for="notes" class="block text-sm font-medium text-gray-400 mb-1 uppercase tracking-wide">Notes / Special Requests</label>
                                    <div class="input-icon-container">
                                        <i class="fas fa-sticky-note input-icon text-gray-500 text-xs" style="top: 0.75rem; transform: translateY(0);"></i> <textarea id="notes" name="notes" rows="4"
                                                  class="block w-full pl-10 rounded bg-[#1a1a1a] text-gray-400 text-xs border-gray-700 shadow-sm focus:border-orange-500 focus:ring focus:ring-orange-300 focus:ring-opacity-50 <?= isset($errors['notes']) ? 'border-red-500' : '' ?>"
                                                  placeholder="Any special requirements? (e.g., setup time needed, specific equipment, dietary restrictions if applicable)" aria-describedby="notes-error"><?= $notes_value ?></textarea>
                                    </div>
                                     <?php if (isset($errors['notes'])): ?><p id="notes-error" class="error-message"><?= htmlspecialchars($errors['notes']); ?></p><?php endif; ?>
                                </div>
                                <div>
                                    <label for="voucher" class="block text-sm font-medium text-gray-400 mb-1 uppercase tracking-wide">Voucher Code (Optional)</label>
                                    <div class="input-icon-container">
                                        <i class="fas fa-tag input-icon text-gray-500 text-xs"></i>
                                        <input type="text" id="voucher" name="voucher_code"
                                               class="block w-full pl-10 rounded bg-[#1a1a1a] text-gray-400 text-xs border-gray-700 shadow-sm focus:border-orange-500 focus:ring focus:ring-orange-300 focus:ring-opacity-50 <?= isset($errors['voucher_code']) ? 'border-red-500' : '' ?>"
                                               value="<?= $voucher_value ?>" placeholder="Enter promo code if you have one" aria-describedby="voucher-error">
                                    </div>
                                    <?php if (isset($errors['voucher_code'])): ?><p id="voucher-error" class="error-message"><?= htmlspecialchars($errors['voucher_code']); ?></p><?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 text-center">
                            <button type="submit" id="submit-button" class="btn btn-primary w-full md:w-auto" <?= ($venue_id <= 0 || $venue_price_per_hour < 0) ? 'disabled' : '' ?>>
                                <i class="fas fa-paper-plane mr-2"></i> Submit Reservation Request
                            </button>
                            <?php if ($venue_id <= 0 || $venue_price_per_hour < 0): ?>
                                <p class="text-xs text-red-400 mt-2">Cannot submit: Invalid venue details or price.</p>
                            <?php endif; ?>
                        </div>

                    </form>

                    <div id="responseMessage" class="mt-2 text-green-400 font-semibold text-xs"></div>
                    <?php else: // Show message if venue details couldn't be loaded ?>
                        <div class="form-section text-center">
                            <p class="text-red-400 font-semibold text-xs">
                                 <i class="fas fa-exclamation-triangle mr-2"></i>
                                 Could not load reservation form. <?= isset($errors['general']) ? htmlspecialchars($errors['general']) : 'Please select a valid venue first.' ?>
                             </p>
                        </div>
                    <?php endif; // End check for valid venue details ?>
                <?php endif; // End of hiding form on success ?>

            </div>
            <div class="image-column">
                 <img alt="Decorative image for the reservation form" class="w-full h-full object-cover" src="/ventech_locator/images/side-photo.jpg"/> <div class="image-overlay-text">
                      <span class="font-semibold">
                       Bookings.
                      </span>
                      <br/>
                      Make your
                      <span class="text-red-400">
                       next event
                      </span>
                      unforgettable.
                 </div>
            </div>
        </div>
    </div>

    <script>
        // The AJAX submission script remains the same
        document.getElementById('reservationForm').addEventListener('submit',  function ( e ) {
            e.preventDefault(); // Prevent default form submission

             const  form = e.target;
             const  formData = new FormData(form);

            fetch('reservation_manage.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then( response   =>  response.text())
            .then( data   =>  {
                 const  trimmed = data.trim(); // Remove extra spaces or newlines

                if (trimmed === 'success') {
                    alert("Successfully submitted, waiting for the confirmation.");
                    form.reset(); // Reset the form
                    window.location.href = 'users/user_dashboard.php'; // Redirect
                } else {
                    alert("An error occurred: " + trimmed);
                }
            })
            .catch( error   =>  {
                console.error('Error:', error);
                alert('An error occurred while submitting the reservation.');
            });
        });


        // The summary calculation script remains the same
        document.addEventListener('DOMContentLoaded',  function () {
             const  eventDateInput = document.getElementById('event-date');
             const  startTimeInput = document.getElementById('start-time');
             const  endTimeInput = document.getElementById('end-time');
             const  summarySection = document.getElementById('reservation-summary');
             const  submitButton = document.getElementById('submit-button');

            // Summary elements
             const  summaryDateEl = document.getElementById('summary-event-date');
             const  summaryStartEl = document.getElementById('summary-start-time');
             const  summaryEndEl = document.getElementById('summary-end-time');
             const  summaryTotalCostEl = document.getElementById('summary-total-cost');
             const  summaryErrorEl = document.getElementById('summary-error');
             const  venuePriceEl = document.getElementById('summary-venue-price');
             const  venuePricePerHour = parseFloat(venuePriceEl?.dataset.price || 0); // Use actual price from PHP

             // Time validation error message element
              const  timeValidationErrorEl = document.getElementById('time-validation-error');

            // Function to format time (e.g., 14:30 -> 2:30 PM)
             function  formatTime( timeString ) {
                if (!timeString) return '--';
                 const  [hours, minutes] = timeString.split(':');
                 const  date = new Date();
                date.setHours(hours, minutes, 0);
                return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true });
            }

             // Function to format date (e.g., 2024-12-31 -> Dec 31, 2024)
             function  formatDate( dateString ) {
                if (!dateString) return '--';
                try {
                     const  date = new Date(dateString + 'T00:00:00'); // Avoid timezone issues by specifying time
                    return date.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
                } catch (e) {
                    console.error("Error formatting date:", e);
                    return dateString; // Fallback to original string
                }
            }


            // Function to calculate duration in hours
             function  calculateDuration( start ,  end ) {
                if (!start || !end) return 0;
                try {
                     const  startDate = new Date(`1970-01-01T${start}:00`);
                         const  endDate = new Date(`1970-01-01T${end}:00`);
                    if (isNaN(startDate) || isNaN(endDate) || endDate <= startDate) {
                        return 0; // Invalid or end time not after start time
                    }
                     const  diffMillis = endDate - startDate;
                    return diffMillis / (1000 * 60 * 60); // Convert milliseconds to hours
                } catch (e) {
                     console.error("Error calculating duration:", e);
                    return 0;
                }
            }

            // Function to update summary and total cost
             function  updateSummary() {
                  const  eventDate = eventDateInput.value;
                 const  startTime = startTimeInput.value;
                 const  endTime = endTimeInput.value;
                 let  isTimeValid = true;
                  let  timeErrorMsg = '';

                // Basic validation: End time must be after start time
                if (startTime && endTime) {
                      const  startDate = new Date(`1970-01-01T${startTime}:00`);
                     const  endDate = new Date(`1970-01-01T${endTime}:00`);
                     if (endDate <= startDate) {
                         isTimeValid = false;
                         timeErrorMsg = 'End time must be after start time.';
                         endTimeInput.classList.add('border-red-500');
                     } else {
                        endTimeInput.classList.remove('border-red-500');
                    }
                } else {
                     // Clear potential border if one time is missing
                    endTimeInput.classList.remove('border-red-500');
                }

                // Update time validation message
                if (timeValidationErrorEl) {
                    timeValidationErrorEl.textContent = timeErrorMsg;
                 }

                // Show summary only if date, start, and end times are selected and valid
                if (eventDate && startTime && endTime && isTimeValid && venuePricePerHour >= 0) {
                    summarySection.classList.remove('hidden');

                     const  durationHours = calculateDuration(startTime, endTime);
                     const  totalCost = durationHours * venuePricePerHour;

                    summaryDateEl.textContent = formatDate(eventDate);
                    summaryStartEl.textContent = formatTime(startTime);
                    summaryEndEl.textContent = formatTime(endTime);
                    summaryTotalCostEl.textContent = `₱ ${totalCost.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                    summaryErrorEl.textContent = ''; // Clear previous errors
                    submitButton.disabled = false; // Enable submit button

                } else {
                    // Hide summary or show placeholder text if inputs are incomplete/invalid
                    summarySection.classList.add('hidden'); // Or update with placeholders like '--'
                    summaryDateEl.textContent = '--';
                    summaryStartEl.textContent = '--';
                    summaryEndEl.textContent = '--';
                    summaryTotalCostEl.textContent = '₱ 0.00';
                    submitButton.disabled = true; // Disable submit button if times invalid

                    if (!isTimeValid && timeErrorMsg) {
                        summaryErrorEl.textContent = timeErrorMsg;
                    } else if (venuePricePerHour < 0){
                         summaryErrorEl.textContent = 'Invalid venue price.';
                     } else {
                         summaryErrorEl.textContent = 'Please select date and valid start/end times.';
                    }
                }
            }

            // Add event listeners to date and time inputs
            eventDateInput.addEventListener('change', updateSummary);
            startTimeInput.addEventListener('change', updateSummary);
            endTimeInput.addEventListener('change', updateSummary);

            // Initial summary update on page load (in case of pre-filled values)
            updateSummary();

             // Optional: Add form submission validation here if needed before sending to PHP
             /*
             document.getElementById('reservation-form').addEventListener('submit', function(event) {
                 // Example: Ensure times are still valid
                 if (!calculateDuration(startTimeInput.value, endTimeInput.value) > 0) {
                     alert('Please ensure the end time is after the start time.');
                     event.preventDefault(); // Stop submission
                     return false;
                 }
                 // Add other client-side checks if necessary
             });
             */

             // --- Optional: AJAX Check for Unavailable Dates ---
             // This requires a separate PHP endpoint (e.g., check_availability.php)
             /*
             eventDateInput.addEventListener('change', function() {
                 const selectedDate = this.value;
                 const venueId = document.querySelector('input[name="venue_id"]').value;
                 const availabilityMsgEl = document.getElementById('date-availability-msg');
                 availabilityMsgEl.textContent = ''; // Clear previous message

                 if (!selectedDate || !venueId) return;

                 // Fetch request to your backend
                 fetch(`check_availability.php?venue_id=${venueId}&date=${selectedDate}`)
                         .then(response => {
                              if (!response.ok) {
                                   // If response is not OK, maybe the endpoint doesn't exist or has server error
                                   console.error('Availability check failed:', response.status, response.statusText);
                                   availabilityMsgEl.textContent = 'Could not check date availability.';
                                   // Don't disable submit button automatically on fetch error unless desired
                                   return Promise.reject('Availability check failed'); // Propagate error
                              }
                              return response.json();
                         })
                     .then(data => {
                         if (!data.available) {
                              availabilityMsgEl.textContent = data.message || 'This date is not available for booking.';
                              submitButton.disabled = true; // Disable submit if date unavailable
                         } else {
                              // Date is available, re-enable submit if other fields are valid
                              updateSummary(); // Re-run summary check which handles submit button state
                         }
                     })
                     .catch(error => {
                         console.error('Error checking date availability:', error);
                         // availabilityMsgEl.textContent = 'Could not check date availability.'; // Keep the message from fetch error if any
                         // submitButton.disabled = true; // Optionally disable submit button on *any* check error
                     });
             });
             */

        });
    </script>

</body>
</html>


