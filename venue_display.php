<?php
// **1. Start Session**
session_start();

// **2. Database Connection Parameters**
$host = 'localhost';
$db   = 'ventech_db';
$user_db = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// **3. Establish PDO Connection**
try {
    $pdo = new PDO($dsn, $user_db, $pass, $options);
} catch (PDOException $e) {
    handle_error("Could not connect to the database: " . $e->getMessage());
}

// Function to handle errors (basic example) - Ensure this is defined before use
function handle_error($message, $is_user_facing = false) {
    error_log($message); // Always log the detailed error
    // Avoid outputting raw errors in production unless explicitly user-facing
    $display_message = $is_user_facing ? htmlspecialchars($message) : "An internal error occurred. Please try again later or contact support.";
    echo "<div style='color:red;padding:10px;border:1px solid red;background-color:#ffe0e0;margin:10px;'>"
         . $display_message
         . "</div>";
    die();
}

// **4. Check User Authentication & Get Role**
$loggedInUserId = $_SESSION['user_id'] ?? null;
$loggedInUserRole = null;
$loggedInUsername = null; // Store username if needed
if ($loggedInUserId) {
    try {
        $stmt = $pdo->prepare("SELECT role, username FROM users WHERE id = ?");
        $stmt->execute([$loggedInUserId]);
        $loggedInUserData = $stmt->fetch();
        if ($loggedInUserData) {
            $loggedInUserRole = $loggedInUserData['role'];
            $loggedInUsername = $loggedInUserData['username']; // Get username
        } else {
            // User ID in session but not DB - clear session
            unset($_SESSION['user_id']);
            unset($_SESSION['username']); // Also clear username if set
            $loggedInUserId = null;
            error_log("User ID $loggedInUserId found in session but not in database.");
        }
    } catch (PDOException $e) {
        error_log("Error fetching logged-in user role/username: " . $e->getMessage());
        // Don't kill the page, just proceed as logged out
        $loggedInUserId = null;
        $loggedInUserRole = null;
        $loggedInUsername = null;
    }
}

// **5. Get and validate the venue ID**
$venue_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($venue_id === false || $venue_id <= 0) {
    handle_error("Invalid or missing Venue ID.", true); // User facing error
}

// **6. Function to fetch data (Modified for single row fetch)**
function fetch_row($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch(); // Use fetch for single row
    } catch (PDOException $e) {
        error_log("Database query error (fetch_row): " . $e->getMessage() . " Query: " . $query);
        return null; // Return null on error
    }
}
function fetch_all($pdo, $query, $params = []) {
     try {
         $stmt = $pdo->prepare($query);
         $stmt->execute($params);
         return $stmt->fetchAll(); // Use fetchAll for multiple rows
     } catch (PDOException $e) {
         error_log("Database query error (fetch_all): " . $e->getMessage() . " Query: " . $query);
         return []; // Return empty array on error
     }
 }


// **7. Fetch Venue Data**
// Fetch venue details along with the owner's username and ID
$venue = fetch_row($pdo, "SELECT v.*, u.username as owner_username, u.id as owner_id FROM venue v LEFT JOIN users u ON v.user_id = u.id WHERE v.id = ?", [$venue_id]);
if (!$venue) {
    handle_error("Venue with ID " . htmlspecialchars($venue_id) . " not found.", true); // User facing error
}
// Check if venue is 'open' - might want to restrict booking if not
$isVenueOpen = (isset($venue['status']) && strtolower($venue['status']) === 'open');

// **8. Fetch Media & Determine Header Image/Video**
// Prioritize virtual tour > video > image for the header background
// Fetch all media, ordered to prioritize images for display in gallery
// This query fetches all media associated with the venue, which will be used for the gallery.
$media = fetch_all($pdo, "SELECT id, media_type, media_url FROM venue_media WHERE venue_id = ? ORDER BY FIELD(media_type, 'image', 'video'), created_at ASC", [$venue_id]);

$header_content_url = 'https://placehold.co/1200x500/cccccc/999999?text=Venue+Image+Not+Available'; // Default fallback
$header_content_type = 'image'; // Can be 'image', 'video', or 'virtual_tour'

// Check for virtual tour first
if (!empty($venue['virtual_tour_url'])) {
    $header_content_url = htmlspecialchars($venue['virtual_tour_url']);
    $header_content_type = 'virtual_tour';
} else if (!empty($media)) {
    // Find first video or image for header (prioritize video if available)
    foreach ($media as $item) {
        if ($item['media_type'] === 'video') {
            $header_content_url = htmlspecialchars($item['media_url']);
            $header_content_type = 'video';
            break; // Prioritize video over image for header if no virtual tour
        } elseif ($item['media_type'] === 'image') {
             // Use the first image found if no video is encountered first
            $header_content_url = htmlspecialchars($item['media_url']);
            $header_content_type = 'image';
            break;
        }
    }
}
// Ensure media URLs are web-accessible (Consider prepending base URL if needed)
// Example: $baseMediaUrl = '/ventech_locator/uploads/venue_media/';
// if ($header_content_type !== 'virtual_tour') { $header_content_url = $baseMediaUrl . basename($header_content_url); }
// Do similar for gallery items below if needed.

// **9. Fetch Other Data**
$unavailableDatesResult = fetch_all($pdo, "SELECT unavailable_date FROM unavailable_dates WHERE venue_id = ?", [$venue_id]);
$unavailableDates = array_column($unavailableDatesResult, 'unavailable_date'); // Extract just the dates

// Fetch client contact info associated with the venue (if available)
$venue_contact_info = fetch_row($pdo, "SELECT client_name, client_email, client_phone, client_address FROM client_info WHERE venue_id = ?", [$venue_id]);


// **10. Calendar Setup**
$currentMonth = $_GET['month'] ?? date('n');
$currentYear = $_GET['year'] ?? date('Y');
// Validate month and year
$currentMonth = filter_var($currentMonth, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
$currentYear = filter_var($currentYear, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1970, 'max_range' => 2100]]); // Adjust range as needed
if (!$currentMonth) $currentMonth = date('n');
if (!$currentYear) $currentYear = date('Y');
$today = date('Y-m-d'); // Get today's date for comparison

// **11. PHP Calendar Function (Updated)**
function generateCalendarPHP($year, $month, $unavailableDates, $today) {
    $calendar = '<div class="calendar" data-year="' . $year . '" data-month="' . $month . '">';
    $calendar .= '<div class="month-header">';
    $calendar .= '<button type="button" class="month-change prev-month" aria-label="Previous month"><i class="fas fa-chevron-left"></i></button>';

    $dateObj = DateTime::createFromFormat('!Y-n', "$year-$month");
    $monthYearString = $dateObj ? $dateObj->format('F Y') : 'Invalid Date';
    $calendar .= '<span class="month-year-text">' . $monthYearString . '</span>'; // Use span for text

    $calendar .= '<button type="button" class="month-change next-month" aria-label="Next month"><i class="fas fa-chevron-right"></i></button>';
    $calendar .= '</div>';
    $calendar .= '<div class="weekdays" aria-hidden="true">'; // Hide from screen readers
    $calendar .= '<div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>';
    $calendar .= '</div>';

    if ($dateObj) {
        $daysInMonth = (int)$dateObj->format('t');
        $firstDayOfMonth = (int)$dateObj->format('w'); // 0 (for Sunday) through 6 (for Saturday)

        $calendar .= '<div class="days" role="grid" aria-labelledby="calendar-heading">'; // ARIA roles

        // Add empty cells for days before the first of the month
        for ($i = 0; $i < $firstDayOfMonth; $i++) {
            $calendar .= '<div class="empty" role="gridcell"></div>';
        }

        // Add day cells
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateString = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
            $isUnavailable = in_array($dateString, $unavailableDates);
            $isPast = $dateString < $today;
            $class = 'day-cell '; // Base class
            $ariaLabel = $dateObj->format('F') . " $day, $year"; // Full date for screen reader
            $ariaDisabled = 'false'; // Default

            if ($isPast) {
                $class .= 'past ';
                $class .= $isUnavailable ? 'unavailable' : 'available'; // Still mark past unavailable days
                $ariaDisabled = 'true';
                 $ariaLabel .= $isUnavailable ? ' (Unavailable, Past)' : ' (Past)';
            } elseif ($isUnavailable) {
                $class .= 'unavailable';
                $ariaDisabled = 'true';
                $ariaLabel .= ' (Unavailable)';
            } else {
                $class .= 'available future'; // Mark future available dates
                 $ariaLabel .= ' (Available)';
            }

            $calendar .= '<div class="' . trim($class) . '" data-date="' . $dateString . '" role="gridcell" tabindex="-1" aria-label="' . $ariaLabel . '" aria-disabled="' . $ariaDisabled . '">'; // ARIA attributes
            $calendar .= $day;
            $calendar .= '</div>';
        }

        // Add empty cells for days after the last of the month
        $totalCells = $firstDayOfMonth + $daysInMonth;
        $remainingCells = (7 - ($totalCells % 7)) % 7;
        for ($i = 0; $i < $remainingCells; $i++) {
             $calendar .= '<div class="empty" role="gridcell"></div>';
        }

        $calendar .= '</div>'; // Close days
    } else {
        $calendar .= '<div class="p-4 text-red-500">Error generating calendar.</div>';
    }
    $calendar .= '</div>'; // Close calendar
    return $calendar;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($venue['title'] ?? 'Venue Details'); ?> - Ventech Locator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
    <link rel="stylesheet" href="/ventech_locator/css/venue_display.css">
    <style>
       
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <nav class="bg-white shadow-sm p-4 sticky top-0 z-20">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center"> <!-- Added flex-col for mobile stacking -->
            <a href="/ventech_locator/index.php" class="text-2xl font-bold text-yellow-600 hover:text-yellow-700 mb-2 sm:mb-0">Ventech Locator</a>
            <div class="flex flex-wrap justify-center sm:justify-end items-center text-sm"> <!-- Added flex-wrap and justify-center -->
                <?php if ($loggedInUserId): ?>
                    <a href="/ventech_locator/client_dashboard.php" class="text-gray-700 hover:text-yellow-600 mx-2 sm:mr-4 font-medium">Dashboard</a>
                    <span class="text-gray-500 mx-2 sm:mr-4 hidden sm:inline">|</span> <!-- Hide separator on very small screens -->
                    <span class="text-gray-700 mx-2 sm:mr-2">Hi, <?= htmlspecialchars($loggedInUsername ?? 'User'); ?></span>
                    <a href="/ventech_locator/client_logout.php" class="text-gray-700 hover:text-yellow-600 mx-2 font-medium">Logout</a>
                <?php else: ?>
                    <a href="javascript:void(0);" onclick="openUserLoginModal()" class="text-gray-700 hover:text-yellow-600 mx-2 sm:mr-4 font-medium">Login</a>
                    <a href="javascript:void(0);" onclick="openUserSignupModal()" class="text-gray-700 hover:text-yellow-600 mx-2 font-medium">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="venue-header">
        <?php if ($header_content_type === 'virtual_tour'): ?>
            <iframe src="<?php echo $header_content_url; ?>" class="venue-header-bg-iframe" allowfullscreen allow="xr-spatial-tracking; gyroscope; accelerometer" title="<?php echo htmlspecialchars($venue['title'] ?? ''); ?> Virtual Tour"></iframe>
        <?php elseif ($header_content_type === 'video'): ?>
            <video autoplay loop muted playsinline class="venue-header-bg" poster=""> <source src="<?php echo $header_content_url; ?>" type="video/mp4">
                Your browser does not support the video tag.
                </video>
        <?php else: // Default to image ?>
            <img src="<?php echo $header_content_url; ?>" alt="<?php echo htmlspecialchars($venue['title'] ?? ''); ?> Header" class="venue-header-bg">
        <?php endif; ?>
        <div class="venue-header-overlay">
            <h1><?php echo htmlspecialchars($venue['title'] ?? 'Venue Title'); ?></h1>
            <p>
                <span><i class="fas fa-users fa-fw mr-1 opacity-80"></i> Up to <?= htmlspecialchars($venue['num_persons'] ?? 'N/A'); ?> guests</span>
                <span><i class="fas fa-money-bill-wave fa-fw mr-1 opacity-80"></i> From ₱<?= number_format($venue['price'] ?? 0, 2) ?>/Hour</span>
                <?php if($venue['owner_username']): ?>
                <span><i class="fas fa-user-tie fa-fw mr-1 opacity-80"></i> By <?= htmlspecialchars($venue['owner_username']); ?></span>
                <?php endif; ?>
                 <?php if(!$isVenueOpen): ?>
                    <span class="text-red-400 font-semibold"><i class="fas fa-exclamation-circle fa-fw mr-1"></i> Currently Closed</span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8 relative z-10 main-content-block">
        <div class="bg-white shadow-xl rounded-lg p-6 md:p-8 mb-6">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                <div class="lg:col-span-2 space-y-8">

                    <?php if (!empty($venue['description'])) : ?>
                        <section aria-labelledby="description-heading">
                            <h2 id="description-heading" class="text-xl font-semibold text-gray-800 mb-3 border-b pb-2">Description</h2>
                            <div class="text-gray-700 prose prose-sm">
                                <?php echo nl2br(htmlspecialchars($venue['description'])); ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-gray-50 p-4 rounded border border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800 mb-3 border-b pb-2">Venue Details</h3>
                            <ul class="space-y-2 text-sm">
                                <li><i class="fas fa-users fa-fw mr-2 text-blue-600 w-5 text-center"></i><strong>Capacity:</strong> <?php echo htmlspecialchars($venue['num_persons'] ?? 'N/A'); ?> persons</li>
                                <li><i class="fas fa-list fa-fw mr-2 text-blue-600 w-5 text-center"></i><strong>Amenities:</strong> <?php echo htmlspecialchars($venue['amenities'] ?? 'N/A'); ?></li>
                                <li><i class="fas fa-star fa-fw mr-2 text-yellow-500 w-5 text-center"></i><strong>Reviews:</strong> <?php echo htmlspecialchars($venue['reviews'] ?? 'N/A'); ?> (Feature coming soon)</li>
                                <li><i class="fas fa-wifi fa-fw mr-2 text-blue-600 w-5 text-center"></i><strong>Wifi:</strong> <?php echo ($venue['wifi'] ?? 'no') === 'yes' ? 'Available' : 'Not Available'; ?></li>
                                <li><i class="fas fa-parking fa-fw mr-2 text-blue-600 w-5 text-center"></i><strong>Parking:</strong> <?php echo ($venue['parking'] ?? 'no') === 'yes' ? 'Available' : 'Not Available'; ?></li>
                            </ul>
                        </div>
                        <div class="bg-gray-50 p-4 rounded border border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800 mb-3 border-b pb-2">Additional Information</h3>
                            <div class="text-sm text-gray-600 prose prose-sm">
                                <?php echo !empty($venue['additional_info']) ? nl2br(htmlspecialchars($venue['additional_info'])) : '<p>No additional information provided.</p>'; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($venue_contact_info)) : ?>
                        <section aria-labelledby="contact-heading">
                            <h2 id="contact-heading" class="text-xl font-semibold text-gray-800 mb-3 border-b pb-2">Venue Contact</h2>
                            <div class="bg-blue-50 p-4 rounded border border-blue-200">
                                <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                    <li><i class="fas fa-user fa-fw mr-2 text-blue-600 w-5 text-center"></i><strong>Name:</strong> <?php echo htmlspecialchars($venue_contact_info['client_name'] ?? 'N/A'); ?></li>
                                    <li><i class="fas fa-envelope fa-fw mr-2 text-blue-600 w-5 text-center"></i><strong>Email:</strong> <?php echo htmlspecialchars($venue_contact_info['client_email'] ?? 'N/A'); ?></li>
                                    <li><i class="fas fa-phone fa-fw mr-2 text-blue-600 w-5 text-center"></i><strong>Phone:</strong> <?php echo htmlspecialchars($venue_contact_info['client_phone'] ?? 'N/A'); ?></li>
                                    <li><i class="fas fa-map-marker-alt fa-fw mr-2 text-blue-600 w-5 text-center"></i><strong>Address:</strong> <?php echo htmlspecialchars($venue_contact_info['client_address'] ?? 'N/A'); ?></li>
                                </ul>
                            </div>
                        </section>
                    <?php endif; ?>

                    <section id="venue-location" aria-labelledby="location-heading">
                        <h2 id="location-heading" class="text-xl font-semibold text-gray-800 mb-3 border-b pb-2">Location</h2>
                        <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($venue['location'] ?? 'Address not provided.'); ?></p>
                        <div id="map"></div> <?php if (!empty($venue['google_map_url'])): ?>
                            <div class="mt-4 text-center">
                                <a href="<?php echo htmlspecialchars($venue['google_map_url']); ?>" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    <i class="fas fa-directions mr-2"></i> Get Directions on Google Maps
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-gray-500 p-2 text-sm">No direct Google Maps link available.</p>
                        <?php endif; ?>
                    </section>
                </div>

                <div class="lg:col-span-1 space-y-6 sticky-sidebar">
                    <section id="availability-section" aria-labelledby="calendar-heading" class="bg-white p-4 rounded-lg border shadow-sm">
                        <h2 id="calendar-heading" class="text-xl font-semibold text-gray-800 mb-4 text-center">Check Availability</h2>
                        <div id="calendar-container">
                            <?php echo generateCalendarPHP($currentYear, $currentMonth, $unavailableDates, $today); ?>
                        </div>
                        <div id="selected-date-display">Select an available date</div>
                         <p class="text-xs text-center text-gray-500 mt-2"><span class="inline-block w-3 h-3 rounded-full bg-red-100 border border-red-300 mr-1 align-middle"></span> Unavailable/Past</p>
                    </section>

                    <section class="bg-white p-4 rounded-lg border shadow-sm">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4 text-center">Book This Venue</h3>

                        <?php if ($isVenueOpen): ?>
                            <?php if ($loggedInUserId): ?>
                                <?php // Form now directly submits to venue_reservation_form.php ?>
                                <form id="booking-redirect-form" action="/ventech_locator/venue_reservation_form.php" method="GET">
                                    <input type="hidden" name="venue_id" value="<?php echo $venue_id; ?>">
                                    <input type="hidden" name="venue_name" value="<?php echo htmlspecialchars($venue['title'] ?? ''); ?>">
                                    <input type="hidden" name="price_per_hour" value="<?php echo htmlspecialchars($venue['price'] ?? '0'); ?>">
                                    <input type="hidden" id="selected_date_input" name="event_date" value="">

                                    <?php // Optional: Display selected date clearly for user (read-only) ?>
                                    <div class="mb-3">
                                        <label for="booking_date_display" class="block text-sm font-medium text-gray-600 mb-1">Selected Date:</label>
                                        <input type="text" id="booking_date_display" readonly
                                               class="w-full bg-gray-100 border border-gray-300 rounded px-3 py-2 text-center text-gray-700"
                                               value="Please select a date from the calendar">
                                    </div>

                                    <button type="submit" id="proceed-to-booking-btn" disabled
                                            class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2.5 px-4 rounded transition duration-300 ease-in-out shadow disabled:opacity-50 disabled:cursor-not-allowed">
                                        <i class="fas fa-calendar-check mr-2"></i> Proceed to Booking
                                    </button>
                                </form>
                                <?php
                                    // Display chat button only for logged-in 'guest' users (not for owners of this venue or admins)
                                    if ($loggedInUserId && ($loggedInUserRole === 'guest' || $loggedInUserRole === null) && $loggedInUserId !== ($venue['owner_id'] ?? null) ) :
                                ?>
                                    <button type="button" id="open-chat-owner-btn"
                                            data-owner-id="<?= htmlspecialchars($venue['owner_id'] ?? '') ?>"
                                            data-owner-username="<?= htmlspecialchars($venue['owner_username'] ?? 'Venue Owner') ?>"
                                            class="w-full mt-4 bg-purple-600 hover:bg-purple-700 text-white font-bold py-2.5 px-4 rounded transition duration-300 ease-in-out shadow">
                                        <i class="fas fa-comments mr-2"></i> Chat with Owner
                                    </button>
                                <?php endif; ?>

                            <?php else: ?>
                                <p class="text-center text-red-600 bg-red-50 p-3 rounded border border-red-200 text-sm">
                                    Please <a href="javascript:void(0);" onclick="openUserLoginModal('<?php echo urlencode($_SERVER['REQUEST_URI']); ?>')" class="font-bold underline hover:text-red-800">Login</a> or
                                    <a href="javascript:void(0);" onclick="openUserSignupModal()" class="font-bold underline hover:text-red-800">Register</a> to make a reservation.
                                </p>
                            <?php endif; ?>
                         <?php else: // Venue is closed ?>
                             <p class="text-center text-orange-700 bg-orange-50 p-3 rounded border border-orange-200 text-sm">
                                This venue is currently marked as closed and cannot be booked at this time.
                            </p>
                         <?php endif; ?>
                    </section>
                </div>
            </div>

            <?php if (!empty($media)): ?>
            <section id="media-section" aria-labelledby="gallery-heading" class="mt-8">
                <h2 id="gallery-heading" class="text-xl font-semibold text-gray-800 mb-3 border-b pb-2">Media Gallery</h2>
                <div class="flex flex-col items-center">
                    <div id="main-media-display" class="w-full max-w-4xl bg-gray-100 rounded-lg overflow-hidden border mb-4">
                        <?php
                            // Display the first media item as the initial main content
                            if (!empty($media)) {
                                $first_item = $media[0];
                                $first_media_url = htmlspecialchars($first_item['media_url']);
                                if ($first_item['media_type'] === 'image') {
                                    echo '<img src="' . $first_media_url . '" alt="Venue Image" class="w-full h-auto max-h-[60vh] object-contain mx-auto"/>';
                                } elseif ($first_item['media_type'] === 'video') {
                                    echo '<video controls class="w-full h-auto max-h-[60vh] object-contain mx-auto" preload="metadata">';
                                    echo '<source src="' . $first_media_url . '" type="video/mp4">';
                                    echo 'Your browser does not support the video tag.';
                                    echo '</video>';
                                }
                            } else {
                                echo '<img src="https://placehold.co/1200x500/cccccc/999999?text=No+Media+Available" alt="No Media" class="w-full h-auto max-h-[60vh] object-contain mx-auto"/>';
                            }
                        ?>
                    </div>

                    <div class="swiper swiper-thumbs w-full max-w-4xl" id="media-thumbs-slider">
                        <div class="swiper-wrapper">
                            <?php
                            // Loop through all fetched media items to create thumbnails
                            foreach ($media as $index => $item) :
                                $mediaUrl = htmlspecialchars($item['media_url']);
                            ?>
                                <div class="swiper-slide cursor-pointer border-2 border-transparent rounded-md overflow-hidden transition-all duration-200 ease-in-out hover:border-blue-500 thumbnail-slide" data-index="<?php echo $index; ?>">
                                    <?php if ($item['media_type'] === 'image') : ?>
                                        <img src="<?php echo $mediaUrl; ?>" alt="Thumbnail Image" class="w-full h-20 object-cover"/>
                                    <?php elseif ($item['media_type'] === 'video') : ?>
                                        <video preload="metadata" class="w-full h-20 object-cover bg-black">
                                            <source src="<?php echo $mediaUrl; ?>#t=0.5" type="video/mp4">
                                        </video>
                                        <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50">
                                            <i class="fas fa-play text-white text-xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="swiper-button-prev !text-gray-600 hover:!text-black after:!text-xl"></div>
                        <div class="swiper-button-next !text-gray-600 hover:!text-black after:!text-xl"></div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($loggedInUserId && isset($loggedInUserRole) && (strtolower($loggedInUserRole) === 'admin' || $loggedInUserId === $venue['user_id']) ): ?>
                <div class="text-center mt-10 border-t pt-6">
                    <a href="venue_details.php?id=<?php echo $venue_id; ?>" class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-6 rounded transition duration-300 ease-in-out shadow hover:shadow-md">
                        <i class="fas fa-edit mr-2"></i>Edit Venue Details
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- User Login Modal -->
    <div id="userLoginModal" class="modal-overlay">
        <div class="modal-content">
            <button type="button" onclick="closeUserLoginModal()" class="absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl font-bold z-50">
                ×
            </button>
            <iframe id="userLoginIframe" src="" class="modal-iframe" title="User Login Form"></iframe>
        </div>
    </div>

    <!-- User Signup Modal -->
    <div id="userSignupModal" class="modal-overlay">
        <div class="modal-content">
            <button type="button" onclick="closeUserSignupModal()" class="absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl font-bold z-50">
                ×
            </button>
            <iframe id="userSignupIframe" src="" class="modal-iframe" title="User Signup Form"></iframe>
        </div>
    </div>

    <!-- Main Chat Modal (Copied from user_dashboard.php and adapted) -->
    <div id="chat-modal" class="chat-modal">
        <div id="chat-modal-content">
            <div class="chat-modal-header">
                <h3 id="chat-title">Messages</h3>
                <button id="close-chat-modal" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <div class="chat-modal-body">
                <!-- Contacts List -->
                <div class="chat-contacts-list">
                    <div class="p-3 border-b border-gray-200">
                        <input type="text" placeholder="Search contacts..." class="w-full p-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-[#8b1d52]">
                    </div>
                    <ul id="contacts-list">
                        <!-- Default placeholder contacts. The venue owner will be added dynamically. -->
                        <li class="contact-list-item" data-contact-id="ai_assistant" data-contact-name="AI Assistant">
                            <div class="contact-avatar bg-purple-400">AI</div>
                            <div>
                                <div class="contact-name">AI Assistant</div>
                                <div class="last-message-snippet">How can I help you today?</div>
                            </div>
                        </li>
                    </ul>
                    <div class="p-3 text-xs text-gray-500 border-t border-gray-200">
                        <p>To initiate a chat, select a contact.</p>
                    </div>
                </div>

                <!-- Conversation Area -->
                <div class="chat-conversation-area">
                    <div class="chat-modal-header flex-shrink-0">
                        <h3 id="current-chat-name" class="text-lg font-semibold text-gray-800">Select a chat</h3>
                        <button class="text-gray-500 hover:text-gray-700 focus:outline-none">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    </div>
                    <div id="chat-messages-display" class="chat-messages-container">
                        <!-- Messages will be loaded here -->
                        <div class="message-bubble bot shadow-sm">
                            <p>Hello! Select a contact from the left to start a conversation.</p>
                            <span class="message-timestamp">AI Assistant, 10:00 AM</span>
                        </div>
                    </div>
                    <div class="chat-input-area flex-shrink-0">
                        <textarea id="chat-message-input" class="chat-message-input" placeholder="Type your message..." rows="1"></textarea>
                        <button id="send-message-button" class="chat-send-button">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script>
    document.addEventListener("DOMContentLoaded", function () {
        // --- Initialize Leaflet Map ---
        const venueLat = <?php echo json_encode($venue['latitude'] ?? null); ?>;
        const venueLon = <?php echo json_encode($venue['longitude'] ?? null); ?>;
        const venueTitle = <?php echo json_encode($venue['title'] ?? 'Venue Location'); ?>;
        const mapDiv = document.getElementById('map');
        let map = null;

        function initMap() {
            if (!mapDiv) return;

            // Check for valid, non-zero coordinates
            if (!venueLat || !venueLon || isNaN(parseFloat(venueLat)) || isNaN(parseFloat(venueLon)) || parseFloat(venueLat) === 0 || parseFloat(venueLon) === 0) {
                mapDiv.innerHTML = '<p class="text-center text-gray-500 p-4">Map location not available or invalid coordinates.</p>';
                return;
            }
            const latNum = parseFloat(venueLat);
            const lonNum = parseFloat(venueLon);
            try {
                map = L.map(mapDiv).setView([latNum, lonNum], 15); // Zoom level 15
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors'
                }).addTo(map);
                L.marker([latNum, lonNum]).addTo(map)
                    .bindPopup(`<b>${venueTitle}</b><br>${<?php echo json_encode($venue['location'] ?? ''); ?>}`) // Use 'location' for address in popup
                    .openPopup();
            } catch (e) {
                console.error("Leaflet map initialization failed:", e);
                mapDiv.innerHTML = '<p class="text-center text-red-500 p-4">Error loading map. Please try again later.</p>';
            }
        }
        initMap(); // Initialize the map

        // --- Media Gallery Swipers ---
        // PHP-generated media data is passed to JavaScript
        const mediaData = <?php echo json_encode($media); ?>;
        const mainMediaDisplay = document.getElementById('main-media-display');
        const mediaThumbsSliderElement = document.getElementById('media-thumbs-slider');

        let thumbsSwiper = null;

        // Only initialize Swiper if there is media data and the slider element exists
        if (mediaThumbsSliderElement && mediaData.length > 0) {
            thumbsSwiper = new Swiper(mediaThumbsSliderElement, {
                spaceBetween: 10,
                slidesPerView: 2, // Default for mobile
                freeMode: true,
                watchSlidesProgress: true,
                watchSlidesVisibility: true,
                navigation: {
                    nextEl: ".swiper-button-next",
                    prevEl: ".swiper-button-prev",
                },
                breakpoints: {
                    640: {
                        slidesPerView: 3, // Changed from 5 to 3 for better tablet view
                    },
                    768: {
                        slidesPerView: 4, // Changed from 6 to 4
                    },
                    1024: {
                        slidesPerView: 5, // Changed from 7 to 5
                    },
                },
            });

            // Function to update the main media display
            function updateMainMedia(index) {
                if (index < 0 || index >= mediaData.length) return;

                const item = mediaData[index];
                let contentHtml = '';

                if (item.media_type === 'image') {
                    contentHtml = `<img src="${item.media_url}" alt="Venue Image" class="w-full h-auto max-h-[60vh] object-contain mx-auto"/>`;
                } else if (item.media_type === 'video') {
                    contentHtml = `
                        <video controls class="w-full h-auto max-h-[60vh] object-contain mx-auto" preload="metadata">
                            <source src="${item.media_url}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    `;
                }
                mainMediaDisplay.innerHTML = contentHtml;

                // Highlight the active thumbnail
                document.querySelectorAll('.thumbnail-slide').forEach((slide, idx) => {
                    if (idx === index) {
                        slide.classList.add('border-blue-500');
                    } else {
                        slide.classList.remove('border-blue-500');
                    }
                });
            }

            // Set initial main media and highlight first thumbnail
            updateMainMedia(0);

            // Add click listener to thumbnails to change main media
            thumbsSwiper.slides.forEach((slide, index) => {
                slide.addEventListener('click', () => {
                    updateMainMedia(index);
                });
            });
        } else if (mainMediaDisplay) {
            // If no media, display a placeholder in the main display area
            mainMediaDisplay.innerHTML = '<img src="https://placehold.co/1200x500/cccccc/999999?text=No+Media+Available" alt="No Media" class="w-full h-auto max-h-[60vh] object-contain mx-auto"/>';
        }


        // --- Calendar AJAX & Click Logic ---
        const calendarContainer = document.getElementById('calendar-container');
        const venueId = <?php echo json_encode($venue_id); ?>;
        const selectedDateDisplay = document.getElementById('selected-date-display');
        const selectedDateInput = document.getElementById('selected_date_input'); // Hidden input for form
        const bookingDateDisplay = document.getElementById('booking_date_display'); // Visible input for user
        const proceedToBookingBtn = document.getElementById('proceed-to-booking-btn'); // The button to redirect
        let currentSelectedDateElement = null; // Keep track of the selected DOM element

        if (calendarContainer) { // Only add listener if calendar exists
             calendarContainer.addEventListener('click', function (e) {
                // --- Handle Month Changes (AJAX) ---
                const monthButton = e.target.closest('.month-change');
                if (monthButton) {
                    e.preventDefault(); // Prevent potential button submit behaviour
                    const calendarEl = monthButton.closest('.calendar');
                    if (!calendarEl) return;

                    let year = parseInt(calendarEl.getAttribute('data-year'));
                    let month = parseInt(calendarEl.getAttribute('data-month'));

                    if (monthButton.classList.contains('prev-month')) { month--; if (month < 1) { month = 12; year--; } }
                    else if (monthButton.classList.contains('next-month')) { month++; if (month > 12) { month = 1; year++; } }
                    else { return; } // Should not happen with closest()

                    calendarContainer.innerHTML = '<div class="p-4 text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</div>'; // Loading state

                    fetchUnavailableDates(year, month, venueId)
                        .then(unavailableDates => {
                             // Update the calendar container with new HTML from JS function
                             calendarContainer.innerHTML = generateCalendarJS(year, month, unavailableDates, '<?php echo $today; ?>');
                             resetSelection(); // Clear selection when month changes
                         })
                         .catch(error => {
                             console.error('Error fetching/generating calendar:', error);
                             calendarContainer.innerHTML = '<div class="p-4 text-center text-red-500">Failed to load calendar. Please try again.</div>';
                         });
                    return; // Stop further processing for month change click
                 }

                // --- Handle Date Selection Clicks ---
                const dayCell = e.target.closest('.day-cell.available.future'); // Target only future available cells
                if (dayCell) {
                    const dateValue = dayCell.getAttribute('data-date'); //YYYY-MM-DD

                    // Remove selected class from previously selected date
                    if (currentSelectedDateElement && currentSelectedDateElement !== dayCell) {
                        currentSelectedDateElement.classList.remove('selected');
                        currentSelectedDateElement.setAttribute('aria-selected', 'false');
                        currentSelectedDateElement.setAttribute('tabindex', '-1');
                    }

                    // Add selected class to the new date and store the element
                    dayCell.classList.add('selected');
                    dayCell.setAttribute('aria-selected', 'true');
                    dayCell.setAttribute('tabindex', '0'); // Make selectable via keyboard
                    currentSelectedDateElement = dayCell;
                    // dayCell.focus(); // Optionally focus the selected cell

                    // Format date for display (e.g., "April 13, 2025")
                    const dateObj = new Date(dateValue + 'T00:00:00'); // Add time part to avoid timezone issues
                    const displayFormat = dateObj.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

                    if (selectedDateDisplay) {
                        selectedDateDisplay.textContent = `Selected: ${displayFormat}`;
                    }
                    // Update form inputs
                    if (selectedDateInput) {
                        selectedDateInput.value = dateValue; // Store ISO-MM-DD
                    }
                    if (bookingDateDisplay) {
                         bookingDateDisplay.value = displayFormat; // Show formatted date in booking form
                    }
                    // Enable booking button if it exists
                    if (proceedToBookingBtn) {
                        proceedToBookingBtn.disabled = false;
                    }
                }
             });
        }

        // Function to clear date selection UI and form inputs
        function resetSelection() {
            if (currentSelectedDateElement) {
                currentSelectedDateElement.classList.remove('selected');
                currentSelectedDateElement.setAttribute('aria-selected', 'false');
                currentSelectedDateElement.setAttribute('tabindex', '-1');
                currentSelectedDateElement = null;
            }
            if (selectedDateDisplay) selectedDateDisplay.textContent = 'Select an available date';
            if (selectedDateInput) selectedDateInput.value = '';
            if (bookingDateDisplay) bookingDateDisplay.value = 'Please select a date from the calendar';
            if (proceedToBookingBtn) proceedToBookingBtn.disabled = true;
        }


        // Function to fetch unavailable dates using AJAX
        function fetchUnavailableDates(year, month, venueId) {
            // Use URLSearchParams for cleaner parameter handling
            const params = new URLSearchParams({
                venue_id: venueId,
                year: year,
                month: month
            });
            const url = `get_unavailable_dates.php?${params.toString()}`; // Assuming script is in same directory

            return fetch(url)
                .then(response => {
                    if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        console.error('API Error fetching unavailable dates:', data.error);
                        return []; // Return empty on API error
                    }
                     // Ensure it returns an array
                    return Array.isArray(data.unavailableDates) ? data.unavailableDates : [];
                })
                .catch(error => {
                     console.error('Fetch Error fetching unavailable dates:', error);
                     throw error; // Re-throw to be caught by the caller
                 });
         }

        // Function to generate the calendar HTML using JavaScript (Completed & Enhanced)
        function generateCalendarJS(year, month, unavailableDates, today) {
            // --- Calendar structure setup ---
            let calendar = `<div class="calendar" data-year="${year}" data-month="${month}">`;
            calendar += '<div class="month-header">';
            calendar += '<button type="button" class="month-change prev-month" aria-label="Previous month"><i class="fas fa-chevron-left"></i></button>';

            const dateObjHeader = new Date(year, month - 1, 1); // Month is 0-indexed in JS Date
            const monthYearString = dateObjHeader.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
            calendar += `<span class="month-year-text" id="calendar-heading-${year}-${month}">${monthYearString}</span>`; // Unique ID for heading

            calendar += '<button type="button" class="month-change next-month" aria-label="Next month"><i class="fas fa-chevron-right"></i></button>';
            calendar += '</div>';
            calendar += '<div class="weekdays" aria-hidden="true"><div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div></div>';

            // --- Days calculation ---
            const daysInMonth = new Date(year, month, 0).getDate(); // Day 0 of next month gives last day of current
            const firstDayOfMonth = new Date(year, month - 1, 1).getDay(); // 0=Sun, 6=Sat

            calendar += `<div class="days" role="grid" aria-labelledby="calendar-heading-${year}-${month}">`; // Reference heading

            // Add empty cells before the first day
            for (let i = 0; i < firstDayOfMonth; i++) {
                calendar += '<div class="empty" role="gridcell"></div>';
            }

            // Add day cells
            for (let day = 1; day <= daysInMonth; day++) {
                 // Ensure two digits for month and day for correct ISO-MM-DD format
                const monthPadded = String(month).padStart(2, '0');
                const dayPadded = String(day).padStart(2, '0');
                const dateString = `${year}-${monthPadded}-${dayPadded}`;

                const isUnavailable = unavailableDates.includes(dateString);
                const isPast = dateString < '<?php echo $today; ?>'; // Use PHP's $today for consistency
                let classes = 'day-cell '; // Base class
                let ariaLabel = `${monthYearString} ${day}`;
                let ariaDisabled = 'false';

                if (isPast) {
                    classes += 'past ';
                    classes += isUnavailable ? 'unavailable' : 'available';
                    ariaDisabled = 'true';
                    ariaLabel += isUnavailable ? ' (Unavailable, Past)' : ' (Past)';
                } else if (isUnavailable) {
                    classes += 'unavailable';
                    ariaDisabled = 'true';
                    ariaLabel += ' (Unavailable)';
                } else {
                    classes += 'available future';
                    ariaLabel += ' (Available)';
                }

                calendar += `<div class="${classes.trim()}" data-date="${dateString}" role="gridcell" tabindex="-1" aria-label="${ariaLabel}" aria-disabled="${ariaDisabled}" aria-selected="false">`;
                calendar += day;
                calendar += '</div>';
            }

            // Add empty cells after the last day
            const totalCells = firstDayOfMonth + daysInMonth;
            const remainingCells = (7 - (totalCells % 7)) % 7;
            for (let i = 0; i < remainingCells; i++) {
                calendar += '<div class="empty" role="gridcell"></div>';
            }

            calendar += '</div>'; // Close days
            calendar += '</div>'; // Close calendar
            return calendar;
        }

        // Add event listener for the booking form if it exists
        const bookingForm = document.getElementById('booking-redirect-form');
        if (bookingForm) {
            bookingForm.addEventListener('submit', function(event) {
                const selectedDate = document.getElementById('selected_date_input').value;
                if (!selectedDate) {
                    event.preventDefault(); // Stop form submission
                    // Replaced alert with a custom message for better UX
                    const messageBox = document.createElement('div');
                    messageBox.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50';
                    messageBox.innerHTML = `
                        <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm w-full text-center">
                            <p class="text-lg font-semibold mb-4">Please select an available date from the calendar before booking.</p>
                            <button type="button" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600" onclick="this.closest('.fixed').remove()">OK</button>
                        </div>
                    `;
                    document.body.appendChild(messageBox);
                }
                // If date is selected, the form will submit normally to venue_reservation_form.php
            });
        }

        // --- User Login/Signup Modal Logic ---
        const userLoginModal = document.getElementById('userLoginModal');
        const userLoginIframe = document.getElementById('userLoginIframe');
        const userSignupModal = document.getElementById('userSignupModal');
        const userSignupIframe = document.getElementById('userSignupIframe');

        // Function to open the user login modal
        window.openUserLoginModal = function(redirectUrl = '') {
            let src = '/ventech_locator/users/user_login.php';
            if (redirectUrl) {
                src += '?redirect=' + encodeURIComponent(redirectUrl);
            }
            userLoginIframe.src = src;
            userLoginModal.classList.add('visible');
            userLoginModal.classList.remove('hidden');
            // Ensure other modals are closed if open
            // closeReservationModal(); // Removed as reservation modal is gone
            closeUserSignupModal();
        };

        // Function to close the user login modal
        window.closeUserLoginModal = function() {
            userLoginModal.classList.remove('visible');
            userLoginModal.classList.add('hidden');
            userLoginIframe.src = ''; // Clear iframe content
        };

        // Function to open the user signup modal
        window.openUserSignupModal = function() {
            userSignupIframe.src = '/ventech_locator/users/user_signup.php';
            userSignupModal.classList.add('visible');
            userSignupModal.classList.remove('hidden');
            // Ensure other modals are closed if open
            // closeReservationModal(); // Removed as reservation modal is gone
            closeUserLoginModal();
        };

        // Function to close the user signup modal
        window.closeUserSignupModal = function() {
            userSignupModal.classList.remove('visible');
            userSignupModal.classList.add('hidden');
            userSignupIframe.src = ''; // Clear iframe content
        };

        // Close user login modal when clicking outside the content
        userLoginModal.addEventListener('click', function(event) {
            if (event.target === userLoginModal) {
                closeUserLoginModal();
            }
        });

        // Close user login modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && userLoginModal.classList.contains('visible')) {
                closeUserLoginModal();
            }
        });

        // Close user signup modal when clicking outside the content
        userSignupModal.addEventListener('click', function(event) {
            if (event.target === userSignupModal) {
                closeUserSignupModal();
            }
        });

        // Close user signup modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && userSignupModal.classList.contains('visible')) {
                closeUserSignupModal();
            }
        });

        // Listen for messages from the iframe (user_login.php, user_signup.php)
        window.addEventListener('message', function(event) {
            const message = event.data;

            if (message.type === 'loginSuccess' || message.type === 'signupSuccess') {
                // Reload the parent page to reflect login/signup status
                window.location.reload();
            } else if (message.type === 'loginError' || message.type === 'signupError') {
                // Optionally display an error message on the parent page
                const errorMessageBox = document.createElement('div');
                errorMessageBox.className = 'fixed inset-0 bg-red-600 bg-opacity-50 flex items-center justify-center z-50';
                errorMessageBox.innerHTML = `
                    <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm w-full text-center">
                        <p class="text-lg font-semibold mb-4 text-red-700"><i class="fas fa-exclamation-triangle mr-2"></i>Authentication Failed</p>
                        <p class="text-gray-700 mb-4">${message.error || 'An unexpected error occurred.'}</p>
                        <button type="button" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600" onclick="this.closest('.fixed').remove();">OK</button>
                    </div>
                `;
                document.body.appendChild(errorMessageBox);
            }
        });

        // --- Chat Feature Logic (Adapted from user_dashboard.php) ---
        const openChatOwnerBtn = document.getElementById('open-chat-owner-btn');
        const chatModal = document.getElementById('chat-modal');
        const closeChatModalButton = document.getElementById('close-chat-modal');
        const contactsList = document.getElementById('contacts-list');
        const chatMessagesDisplay = document.getElementById('chat-messages-display');
        const chatMessageInput = document.getElementById('chat-message-input');
        const sendMessageButton = document.getElementById('send-message-button');
        const currentChatNameDisplay = document.getElementById('current-chat-name');

        let currentChatHistory = []; // Stores messages for the currently selected chat
        let currentContactId = null; // Stores the ID of the currently selected contact

        // Helper function to escape HTML for display
        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // Function to append messages to the chat display
        function appendMessage(text, sender, timestamp = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})) {
            const messageDiv = document.createElement('div');
            // Determine the class for the message bubble based on sender
            let senderClass = '';
            if (sender === 'user') {
                senderClass = 'user';
            } else if (sender === 'bot') {
                senderClass = 'bot';
            } else { // Assuming 'other' for client/owner messages
                senderClass = 'other';
            }

            messageDiv.classList.add('message-bubble', senderClass, 'shadow-sm');
            messageDiv.innerHTML = `<p>${escapeHtml(text)}</p><span class="message-timestamp">${sender === 'user' ? 'You' : (sender === 'bot' ? 'AI Assistant' : currentChatNameDisplay.textContent)}, ${timestamp}</span>`;
            chatMessagesDisplay.appendChild(messageDiv);
            chatMessagesDisplay.scrollTop = chatMessagesDisplay.scrollHeight; // Scroll to bottom
        }

        // Function to load conversation for a selected contact
        function loadConversation(contactId, contactName) {
            currentContactId = contactId;
            currentChatNameDisplay.textContent = contactName;
            chatMessagesDisplay.innerHTML = ''; // Clear previous messages

            // Simulate loading chat history (replace with actual fetch from backend)
            if (contactId === 'ai_assistant') {
                currentChatHistory = [{ role: "model", parts: [{ text: "Hello! How can I assist you today?" }] }];
                appendMessage(currentChatHistory[0].parts[0].text, 'bot', new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
            } else {
                // Placeholder for actual user-to-user conversation history
                // In a real app, you'd fetch messages from your database:
                // fetch(`/api/get_messages.php?user_id=<?= htmlspecialchars($loggedInUserId) ?>&contact_id=${contactId}`)
                // .then(response => response.json())
                // .then(messages => {
                //    currentChatHistory = messages;
                //    messages.forEach(msg => appendMessage(msg.text, msg.sender, msg.timestamp));
                // });

                // Simulate initial messages with the owner
                currentChatHistory = [
                    { role: "other", text: `Hello, this is ${contactName}. How can I help you regarding ${venueTitle}?`, timestamp: "10:00 AM" },
                    { role: "user", text: `Hi ${contactName}! I have a question about booking your venue for a party.`, timestamp: "10:05 AM" }
                ];
                currentChatHistory.forEach(msg => appendMessage(msg.text, msg.role, msg.timestamp));
            }

            // Update active state in contact list
            document.querySelectorAll('.contact-list-item').forEach(item => item.classList.remove('active'));
            const activeContactItem = document.querySelector(`.contact-list-item[data-contact-id="${contactId}"]`);
            if (activeContactItem) {
                activeContactItem.classList.add('active');
            } else {
                // If the contact is not in the list (e.g., first time chatting with owner), add it
                const ownerId = openChatOwnerBtn.dataset.ownerId;
                const ownerName = openChatOwnerBtn.dataset.ownerUsername;
                if (ownerId && ownerName && contactId === ownerId) { // Ensure it's the owner we're trying to add
                    const newOwnerContact = document.createElement('li');
                    newOwnerContact.classList.add('contact-list-item', 'active');
                    newOwnerContact.setAttribute('data-contact-id', ownerId);
                    newOwnerContact.setAttribute('data-contact-name', ownerName);
                    newOwnerContact.innerHTML = `
                        <div class="contact-avatar bg-blue-400">${ownerName.charAt(0)}</div>
                        <div>
                            <div class="contact-name">${ownerName}</div>
                            <div class="last-message-snippet">Start a new conversation...</div>
                        </div>
                    `;
                    contactsList.prepend(newOwnerContact); // Add to the top
                    newOwnerContact.addEventListener('click', () => loadConversation(ownerId, ownerName));
                }
            }
            chatMessageInput.focus();
        }

        // Event listener for the "Chat with Owner" button
        if (openChatOwnerBtn) {
            openChatOwnerBtn.addEventListener('click', () => {
                const ownerId = openChatOwnerBtn.dataset.ownerId;
                const ownerUsername = openChatOwnerBtn.dataset.ownerUsername;

                // Ensure owner details are available
                if (ownerId && ownerUsername) {
                    chatModal.classList.add('open');
                    loadConversation(ownerId, ownerUsername); // Load conversation with the specific owner
                } else {
                    // Fallback or error message if owner details are missing
                    console.error('Owner ID or username not available for chat.');
                    alert('Could not start chat. Owner information is missing.'); // Consider custom modal
                }
            });
        }

        // Event listener for closing chat modal
        closeChatModalButton.addEventListener('click', () => {
            chatModal.classList.remove('open');
        });

        // Event listener for clicking outside the chat modal content to close it
        chatModal.addEventListener('click', function(event) {
            if (event.target === chatModal) {
                chatModal.classList.remove('open');
            }
        });

        // Event listener for clicking contacts in the list
        contactsList.addEventListener('click', function(event) {
            const clickedItem = event.target.closest('.contact-list-item');
            if (clickedItem) {
                const contactId = clickedItem.dataset.contactId;
                const contactName = clickedItem.dataset.contactName;
                loadConversation(contactId, contactName);
            }
        });

        // Event listener for sending messages
        sendMessageButton.addEventListener('click', sendMessageHandler);
        chatMessageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault(); // Prevent new line
                sendMessageHandler();
            }
        });

        async function sendMessageHandler() {
            const userMessage = chatMessageInput.value.trim();
            if (userMessage === '' || currentContactId === null) return; // Do not send empty message or if no contact selected

            appendMessage(userMessage, 'user'); // Display user's message immediately
            chatMessageInput.value = ''; // Clear input field

            // Add user message to chat history for current conversation
            currentChatHistory.push({ role: "user", text: userMessage, timestamp: new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) });

            if (currentContactId === 'ai_assistant') {
                // AI Assistant Logic using Gemini API
                const loadingMessageDiv = document.createElement('div');
                loadingMessageDiv.classList.add('message-bubble', 'bot', 'loading', 'shadow-sm');
                loadingMessageDiv.innerHTML = `<p>...</p><span class="message-timestamp">AI Assistant, ${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>`;
                chatMessagesDisplay.appendChild(loadingMessageDiv);
                chatMessagesDisplay.scrollTop = chatMessagesDisplay.scrollHeight;

                try {
                    const payload = { contents: currentChatHistory.map(msg => ({ role: msg.role === 'other' ? 'model' : msg.role, parts: [{ text: msg.text }] })) };
                    const apiKey = ""; // Canvas will automatically provide this
                    const apiUrl = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=${apiKey}`;

                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });

                    const result = await response.json();

                    chatMessagesDisplay.removeChild(loadingMessageDiv); // Remove loading indicator

                    if (result.candidates && result.candidates.length > 0 &&
                        result.candidates[0].content && result.candidates[0].content.parts &&
                        result.candidates[0].content.parts.length > 0) {
                        const botResponse = result.candidates[0].content.parts[0].text;
                        appendMessage(botResponse, 'bot');
                        currentChatHistory.push({ role: "model", text: botResponse, timestamp: new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) });
                    } else {
                        appendMessage("Sorry, I couldn't get a response from AI. Please try again.", 'bot');
                        console.error('Unexpected AI API response structure:', result);
                    }
                } catch (error) {
                    chatMessagesDisplay.removeChild(loadingMessageDiv);
                    appendMessage("Error connecting to the AI assistant. Please try again later.", 'bot');
                    console.error('Error fetching from Gemini API:', error);
                }
            } else {
                // --- Placeholder for actual client-to-customer message sending ---
                // For a real client-to-customer chat, you would send this message to your backend:
                // fetch('/api/send_user_message.php', {
                //     method: 'POST',
                //     headers: { 'Content-Type': 'application/json' },
                //     body: JSON.stringify({
                //         sender_id: <?= htmlspecialchars($loggedInUserId) ?>,
                //         receiver_id: currentContactId, // This is the owner's ID
                //         message: userMessage
                //     })
                // })
                // .then(response => response.json())
                // .then(data => {
                //     if (data.success) {
                //         console.log('Message sent successfully to owner:', data);
                //     } else {
                //         appendMessage("Failed to send message to owner.", 'other');
                //         console.error('Error sending message to owner:', data.error);
                //     }
                // })
                // .catch(error => {
                //     appendMessage("Network error sending message to owner.", 'other');
                //     console.error('Network error to owner:', error);
                // });

                // Simulate a response from the "other" user (owner) for demonstration
                setTimeout(() => {
                    appendMessage("Got your message! I'll reply soon.", 'other');
                    currentChatHistory.push({ role: "other", text: "Got your message! I'll reply soon.", timestamp: new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) });
                }, 1000);
            }
        }
    });
    </script>

</body>
</html>