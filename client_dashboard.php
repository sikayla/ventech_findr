<?php
// **1. Start Session**
session_start();

// **2. Include Database Connection**
require_once 'includes/db_connection.php';

// **3. Check User Authentication**
if (!isset($_SESSION['user_id'])) {
    header("Location: client_login.php"); // Adjust path if client_login.php is elsewhere
    exit;
}
$loggedInOwnerUserId = $_SESSION['user_id'];

// **4. Check if PDO connection is available**
if (!isset($pdo) || !$pdo instanceof  PDO) {
    error_log("PDO connection not available in client_dashboard.php");
    die("Sorry, we're experiencing technical difficulties with the database. Please try again later.");
}

// **5. Fetch Logged-in User (Owner) Details**
try {
    $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->execute([$loggedInOwnerUserId]);
    $owner = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$owner) {
        error_log("Invalid user_id in session: " . $loggedInOwnerUserId);
        session_unset();
        session_destroy();
        header("Location: client_login.php?error=invalid_session"); // Adjust path
        exit;
    }
    if ($owner['role'] !== 'client' && $owner['role'] !== 'admin' && $owner['role'] !== 'owner') { // Added 'owner' role check
         error_log("User ID {$loggedInOwnerUserId} attempted to access client dashboard with role: {$owner['role']}");
         session_unset();
         session_destroy();
         header("Location: client_login.php?error=unauthorized_access"); // Adjust path
         exit;
    }

} catch (PDOException $e) {
    error_log("Error fetching user details for user ID {$loggedInOwnerUserId}: " . $e->getMessage());
    die("Error loading your information. Please try refreshing the page or contact support.");
}

// **6. Fetch Venues Owned by the Logged-in User**
$venues = [];
$venue_ids_owned = [];
try {
    $status_filter = $_GET['status'] ?? 'all';
    $allowed_statuses = ['all', 'open', 'closed'];

    $sql = "SELECT id, title, price, status, reviews, image_path, created_at FROM venue WHERE user_id = ?";
    $params = [$loggedInOwnerUserId];

    if (in_array($status_filter, $allowed_statuses) && $status_filter !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $status_filter;
    }
    $sql .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $venue_ids_owned = array_column($venues, 'id');

} catch (PDOException $e) {
    error_log("Error fetching venues for user $loggedInOwnerUserId (status: $status_filter): " . $e->getMessage());
}


// **7. Fetch Dashboard Counts for Owned Venues**
$total_venue_bookings_count = 0;
$pending_reservations_count = 0;
$cancelled_reservations_count = 0;
$total_venues_count = count($venues); // Get total count of venues owned

if (!empty($venue_ids_owned)) {
    try {
        $in_placeholders = implode(',', array_fill(0, count($venue_ids_owned), '?'));

        $stmtTotalBookings = $pdo->prepare("SELECT COUNT(*) FROM venue_reservations WHERE venue_id IN ($in_placeholders)");
        $stmtTotalBookings->execute($venue_ids_owned);
        $total_venue_bookings_count = $stmtTotalBookings->fetchColumn();

        $stmtPendingBookings = $pdo->prepare("SELECT COUNT(*) FROM venue_reservations WHERE venue_id IN ($in_placeholders) AND status = 'pending'");
        $stmtPendingBookings->execute($venue_ids_owned); // Re-execute with same params
        $pending_reservations_count = $stmtPendingBookings->fetchColumn();

        $stmtCancelledBookings = $pdo->prepare("SELECT COUNT(*) FROM venue_reservations WHERE venue_id IN ($in_placeholders) AND (status = 'cancelled' OR status = 'cancellation_requested')");
        $stmtCancelledBookings->execute($venue_ids_owned); // Re-execute with same params
        $cancelled_reservations_count = $stmtCancelledBookings->fetchColumn();

    } catch (PDOException $e) {
        error_log("Error fetching dashboard counts for owned venues (Owner ID: $loggedInOwnerUserId): " . $e->getMessage());
    }
}


// **8. Fetch Recent Reservations for Owned Venues**
$recent_venue_reservations = [];
if (!empty($venue_ids_owned)) {
     try {
        $in_placeholders_reservations = implode(',', array_fill(0, count($venue_ids_owned), '?')); // Ensure unique placeholder name if needed, but it's fine here.
         $sql_reservations = "SELECT
                     r.id, r.event_date, r.status, r.created_at,
                     v.id as venue_id, v.title as venue_title,
                     u.id as booker_user_id, u.username as booker_username, u.email as booker_email
                   FROM venue_reservations r
                   JOIN venue v ON r.venue_id = v.id
                   LEFT JOIN users u ON r.user_id = u.id
                   WHERE r.venue_id IN ($in_placeholders_reservations)
                   ORDER BY r.created_at DESC
                   LIMIT 10";

         $stmt_reservations = $pdo->prepare($sql_reservations);
         $stmt_reservations->execute($venue_ids_owned);
         $recent_venue_reservations = $stmt_reservations->fetchAll(PDO::FETCH_ASSOC);

     } catch (PDOException $e) {
         error_log("Error fetching recent reservations for owned venues (Owner ID: $loggedInOwnerUserId): " . $e->getMessage());
     }
}


// **9. Handle Messages (Modified to use session for one-time display)**
$new_venue_message = "";
$new_venue_id_for_link = null;
if (isset($_GET['new_venue']) && $_GET['new_venue'] == 'true') {
    $_SESSION['new_venue_message'] = "Venue successfully added!";
    try {
        $stmtLastVenue = $pdo->prepare("SELECT id FROM venue WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmtLastVenue->execute([$loggedInOwnerUserId]);
        $lastVenue = $stmtLastVenue->fetch(PDO::FETCH_ASSOC);
        if ($lastVenue) {
             $_SESSION['new_venue_id_for_link'] = $lastVenue['id'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching last venue ID for user {$loggedInOwnerUserId}: " . $e->getMessage());
    }
    // Redirect to clean the URL, preventing message from reappearing on refresh
    header("Location: client_dashboard.php");
    exit;
}

// Retrieve and unset session messages
if (isset($_SESSION['new_venue_message'])) {
    $new_venue_message = $_SESSION['new_venue_message'];
    $new_venue_id_for_link = $_SESSION['new_venue_id_for_link'] ?? null;
    unset($_SESSION['new_venue_message']);
    unset($_SESSION['new_venue_id_for_link']);
}


$venue_updated_message = "";
if (isset($_GET['venue_updated']) && $_GET['venue_updated'] == 'true') {
    $_SESSION['venue_updated_message'] = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-4' role='alert'><p class='font-bold'><i class='fas fa-check-circle mr-2'></i>Success!</p><p>Venue details updated successfully!</p></div>";
    header("Location: client_dashboard.php");
    exit;
}
if (isset($_SESSION['venue_updated_message'])) {
    $venue_updated_message = $_SESSION['venue_updated_message'];
    unset($_SESSION['venue_updated_message']);
}

// Handle venue deletion messages
$venue_deleted_message = "";
if (isset($_GET['venue_deleted']) && $_GET['venue_deleted'] == 'true') {
    $_SESSION['venue_deleted_message'] = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-4' role='alert'><p class='font-bold'><i class='fas fa-check-circle mr-2'></i>Success!</p><p>Venue successfully deleted!</p></div>";
    header("Location: client_dashboard.php");
    exit;
}
if (isset($_SESSION['venue_deleted_message'])) {
    $venue_deleted_message = $_SESSION['venue_deleted_message'];
    unset($_SESSION['venue_deleted_message']);
}

$venue_delete_error_message = "";
if (isset($_GET['delete_error'])) {
    $delete_error_map = [
        'invalid_id' => "Error: Invalid venue ID.",
        'unauthorized' => "Error: You are not authorized to delete this venue.",
        'db_error' => "Error: A database error occurred during deletion."
    ];
    $_SESSION['venue_delete_error_message'] = $delete_error_map[$_GET['delete_error']] ?? "An unknown error occurred during deletion.";
    header("Location: client_dashboard.php");
    exit;
}
if (isset($_SESSION['venue_delete_error_message'])) {
    $venue_delete_error_message = $_SESSION['venue_delete_error_message'];
    unset($_SESSION['venue_delete_error_message']);
}


$reservation_created_message = "";
if (isset($_GET['reservation_created']) && $_GET['reservation_created'] == 'true') {
    $_SESSION['reservation_created_message'] = "Reservation successfully created!";
    header("Location: client_dashboard.php");
    exit;
}
if (isset($_SESSION['reservation_created_message'])) {
    $reservation_created_message = $_SESSION['reservation_created_message'];
    unset($_SESSION['reservation_created_message']);
}


$reservation_error_message = "";
if (isset($_GET['error'])) {
    // Basic error mapping, can be expanded
    $error_map = [
        'reservation_failed' => "Failed to create reservation. Please try again.",
        'invalid_reservation_data' => "Invalid reservation data. Please check your input.",
        'unauthorized_access' => "You do not have permission to access this page.",
        'invalid_session' => "Your session is invalid. Please log in again."
    ];
    $_SESSION['reservation_error_message'] = $error_map[$_GET['error']] ?? "An unspecified error occurred.";
    header("Location: client_dashboard.php");
    exit;
}
if (isset($_SESSION['reservation_error_message'])) {
    $reservation_error_message = $_SESSION['reservation_error_message'];
    unset($_SESSION['reservation_error_message']);
}


$reservation_action_message = "";
if (isset($_GET['action_success'])) {
    $action_success_map = [
        'accepted' => "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-4' role='alert'><p class='font-bold'><i class='fas fa-check-circle mr-2'></i>Success!</p><p>Reservation accepted.</p></div>",
        'rejected' => "<div class='bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md mb-4' role='alert'><p class='font-bold'><i class='fas fa-check-circle mr-2'></i>Success!</p><p>Reservation rejected.</p></div>",
        'confirmed' => "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-4' role='alert'><p class='font-bold'><i class='fas fa-check-circle mr-2'></i>Success!</p><p>Reservation confirmed.</p></div>",
        'cancelled' => "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-4' role='alert'><p class='font-bold'><i class='fas fa-check-circle mr-2'></i>Success!</p><p>Reservation cancelled.</p></div>",
        'completed' => "<div class='bg-purple-100 border-l-4 border-purple-500 text-purple-700 p-4 rounded-md mb-4' role='alert'><p class='font-bold'><i class='fas fa-check-circle mr-2'></i>Success!</p><p>Reservation marked as completed.</p></div>"
    ];
    $_SESSION['reservation_action_message'] = $action_success_map[$_GET['action_success']] ?? '';
    header("Location: client_dashboard.php");
    exit;
} elseif (isset($_GET['action_error'])) {
     $action_error_map = [
        'invalid' => "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-4' role='alert'><p class='font-bold'><i class='fas fa-exclamation-triangle mr-2'></i>Error!</p><p>Invalid action or reservation ID.</p></div>",
        'db_error' => "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-4' role='alert'><p class='font-bold'><i class='fas fa-exclamation-triangle mr-2'></i>Error!</p><p>A database error occurred.</p></div>",
    ];
    $_SESSION['reservation_action_message'] = $action_error_map[$_GET['action_error']] ?? "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-4' role='alert'><p class='font-bold'><i class='fas fa-exclamation-triangle mr-2'></i>Error!</p><p>An error occurred.</p></div>";
    header("Location: client_dashboard.php");
    exit;
}
if (isset($_SESSION['reservation_action_message'])) {
    $reservation_action_message = $_SESSION['reservation_action_message'];
    unset($_SESSION['reservation_action_message']);
}


// --- Helper function for status badges ---
function getStatusBadgeClass($status) {
    $status = strtolower($status ?? 'unknown');
    switch ($status) {
        case 'open': case 'confirmed': case 'accepted': case 'completed': return 'bg-green-100 text-green-800';
        case 'closed': case 'cancelled': case 'rejected': case 'cancellation_requested': return 'bg-red-100 text-red-800';
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}
// Adjust path for client_logout.php if client_dashboard.php is in a subfolder like 'client'
$logoutPath = '/ventech_locator/client/client_logout.php';
$indexPath = '/ventech_locator/index.php';
$addVenuePath = '/ventech_locator/client/add_venue.php';
$clientMapPath = '/ventech_locator/client_map.php'; // Adjusted for consistency
$clientProfilePath = '/ventech_locator/client/client_profile.php';
$reservationManagePath = '/ventech_locator/reservation_manage.php'; // Adjusted for client
$clientNotificationListPath = '/ventech_locator/client/client_notification_list.php';
$clientNotificationEndpoint = '/ventech_locator/client/client_notification.php'; // For JS fetch

// Path for venue_display.php and edit_venue.php
$venueDisplayPath = '/ventech_locator/venue_display.php';
$editVenuePath = '/ventech_locator/client/edit_venue.php';
$deleteVenueEndpoint = '/ventech_locator/client/delete_venue.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1" name="viewport"/>
    <title>Client Dashboard - Ventech Locator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <!-- Use Montserrat for headings, Open Sans for body as per user_dashboard.php -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="/ventech_locator/css/client_dashboard.css">
    
    <style>
       
    </style>
</head>
<body class="bg-white text-gray-900">

    <!-- Loading Overlay -->
    <div id="loading-overlay">
        <div class="loader-container">
            <i class="fas fa-map-marker-alt loader-pin"></i>
            <div class="loader-bar">
                <div class="loader-indicator"></div>
            </div>
        </div>
    </div>

    <header class="flex items-center justify-between py-4 text-xs sm:text-sm fixed w-full top-0 left-0 right-0 z-30 bg-white shadow-md px-4">
        <div class="flex items-center space-x-2">
            <button id="mobile-menu-toggle" aria-label="Menu" class="text-gray-700 text-xl md:hidden">
                <i class="fas fa-bars"></i>
            </button>
             <a href="<?php echo htmlspecialchars($indexPath); ?>">
                <img alt="Ventech Locator Logo" class="w-[80px] h-[38px] object-contain" height="30" src="/ventech_locator/images/logo.png" width="88" />
            </a>
        </div>

        <nav class="hidden md:flex items-center space-x-4 text-xs sm:text-sm text-gray-900 font-normal">
            <ul class="flex items-center space-x-4">
        
                <li class="cursor-pointer hover:underline hover:text-[#ff5722]">
                    <a href="javascript:void(0);" onclick="openAddVenueModal();">Add Venue</a>
                </li>
                <li class="cursor-pointer hover:underline hover:text-[#ff5722]">
                    <a href="<?php echo htmlspecialchars($clientMapPath); ?>">Map</a>
                </li>
                <li class="cursor-pointer hover:underline hover:text-[#ff5722]">
                    <a href="<?php echo htmlspecialchars($clientProfilePath); ?>">Profile</a>
                </li>
                <li class="cursor-pointer hover:underline hover:text-[#ff5722]">
                    <a href="<?php echo htmlspecialchars($reservationManagePath); ?>">Manage Reservations</a>
                </li>
            </ul>

            <ul class="flex items-center space-x-4 ml-6">
                <?php if ($owner): ?>
                    <li class="relative group cursor-pointer">
                        <div class="notification-icon-container inline-block">
                            <a href="<?php echo htmlspecialchars($reservationManagePath); ?>?status_filter=pending" class="text-gray-700 hover:text-[#ff5722] transition-colors" title="View Pending Reservations">
                                <i class="fas fa-bell text-xl"></i>
                            </a>
                            <?php if ($pending_reservations_count > 0): ?>
                                <span id="client-notification-count-badge" class="notification-badge"><?= htmlspecialchars($pending_reservations_count) ?></span>
                            <?php else: ?>
                                <span id="client-notification-count-badge" class="notification-badge" style="display: none;">0</span>
                            <?php endif; ?>
                        </div>
                    </li>
                    <li class="cursor-pointer">
                        <span class="hidden lg:inline text-gray-700">Welcome, <strong class="font-semibold text-[#ff5722]"><?= htmlspecialchars($owner['username'] ?? 'Owner') ?></strong>!</span>
                    </li>
                    <li class="cursor-pointer">
                        <a href="<?php echo htmlspecialchars($logoutPath); ?>" class="bg-[#ff5722] text-white hover:bg-[#e64a19] py-1.5 px-4 rounded-md text-sm font-medium transition duration-150 ease-in-out shadow-sm flex items-center">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li class="cursor-pointer hover:underline hover:text-[#ff5722]">Sign In</li>
                    <li class="cursor-pointer">
                        <a href="/ventech_locator/client/client_signup.php" class="bg-[#ff5722] text-white hover:bg-[#e64a19] py-1.5 px-4 rounded-md text-sm font-medium transition duration-150 ease-in-out shadow-sm">
                            <i class="fas fa-user-plus mr-1"></i> Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <div id="mobile-menu" class="md:hidden bg-white py-2 px-4 mt-[64px] fixed w-full z-20 shadow-md hidden">
        <ul class="flex flex-col space-y-2 text-gray-900 font-normal">
            <li><a href="client_dashboard.php" class="block py-2 px-3 hover:bg-gray-100 rounded">Dashboard</a></li>
            <li><a href="javascript:void(0);" onclick="openAddVenueModal();" class="block py-2 px-3 hover:bg-gray-100 rounded">Add Venue</a></li>
            <li><a href="<?php echo htmlspecialchars($clientMapPath); ?>" class="block py-2 px-3 hover:bg-gray-100 rounded">Map</a></li>
            <li><a href="<?php echo htmlspecialchars($clientProfilePath); ?>" class="block py-2 px-3 hover:bg-gray-100 rounded">Profile</a></li>
            <li><a href="<?php echo htmlspecialchars($reservationManagePath); ?>" class="block py-2 px-3 hover:bg-gray-100 rounded">Manage Reservations</a></li>
            <li class="border-t border-gray-200 pt-2 mt-2">
                <a href="<?php echo htmlspecialchars($reservationManagePath); ?>?status_filter=pending" class="block py-2 px-3 hover:bg-gray-100 rounded flex items-center">
                    Pending Requests
                    <?php if ($pending_reservations_count > 0): ?>
                        <span class="ml-auto bg-[#ef4444] text-white text-xs font-bold px-2 py-1 rounded-full"><?= htmlspecialchars($pending_reservations_count) ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($logoutPath); ?>" class="block py-2 px-3 hover:bg-red-50 rounded flex items-center text-red-600">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <div class="dashboard-container max-w-7xl mx-auto px-4">
        <main class="flex-1 p-6 md:p-8 lg:p-10 overflow-y-auto">
            <section class="hero-section">
                <div class="hero-overlay">
                    <h1 class="hero-title">Manage Your Venues & Bookings</h1>
                    <p class="hero-description">Oversee your listings and reservation requests efficiently.</p>
                    <a href="client_map.php?status=all" class="hero-button">
                        <i class="fas fa-store mr-2"></i> View All Venues
                    </a>
                </div>
            </section>

            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-6">
                <?php echo htmlspecialchars($owner['username'] ?? 'Owner'); ?> Dashboard
            </h1>

            <?php if (!empty($new_venue_message)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-3 sm:p-4 rounded-md mb-6 shadow relative" role="alert">
                    <p class="font-bold"><i class="fas fa-check-circle mr-2"></i>Success!</p>
                    <p class="text-sm sm:text-base"><?= htmlspecialchars($new_venue_message) ?>
                        <?php if ($new_venue_id_for_link): ?>
                            You can now view or edit its details.
                            <a href="<?php echo htmlspecialchars($venueDisplayPath); ?>?id=<?= htmlspecialchars($new_venue_id_for_link) ?>" class="font-medium text-blue-600 hover:text-blue-800 underline ml-1">View Venue</a> or
                            <a href="<?php echo htmlspecialchars($editVenuePath); ?>?id=<?= htmlspecialchars($new_venue_id_for_link) ?>" class="font-medium text-blue-600 hover:text-blue-800 underline ml-1">Edit Details</a>.
                        <?php else: ?>
                            Please find it in your list below to add/edit details.
                        <?php endif; ?>
                    </p>
                    <button type="button" class="absolute top-0 right-0 mt-1 mr-1 sm:mt-2 sm:mr-2 text-green-700 hover:text-green-900" onclick="this.parentElement.style.display='none';" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            <?php if (!empty($venue_updated_message)): ?>
                <?= $venue_updated_message ?>
            <?php endif; ?>
            <?php if (!empty($venue_deleted_message)): ?>
                <?= $venue_deleted_message ?>
            <?php endif; ?>
            <?php if (!empty($venue_delete_error_message)): ?>
                 <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 sm:p-4 rounded-md mb-6 shadow relative" role="alert">
                    <p class="font-bold"><i class="fas fa-exclamation-triangle mr-2"></i>Error!</p>
                    <p class="text-sm sm:text-base"><?= htmlspecialchars($venue_delete_error_message) ?></p>
                    <button type="button" class="absolute top-0 right-0 mt-1 mr-1 sm:mt-2 sm:mr-2 text-red-700 hover:text-red-900" onclick="this.parentElement.style.display='none';" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            <?php if (!empty($reservation_created_message)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-3 sm:p-4 rounded-md mb-6 shadow relative" role="alert">
                    <p class="font-bold"><i class="fas fa-check-circle mr-2"></i>Success!</p>
                    <p class="text-sm sm:text-base"><?= htmlspecialchars($reservation_created_message) ?></p>
                    <button type="button" class="absolute top-0 right-0 mt-1 mr-1 sm:mt-2 sm:mr-2 text-green-700 hover:text-green-900" onclick="this.parentElement.style.display='none';" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            <?php if (!empty($reservation_error_message)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 sm:p-4 rounded-md mb-6 shadow relative" role="alert">
                    <p class="font-bold"><i class="fas fa-exclamation-triangle mr-2"></i>Error!</p>
                    <p class="text-sm sm:text-base"><?= htmlspecialchars($reservation_error_message) ?></p>
                    <button type="button" class="absolute top-0 right-0 mt-1 mr-1 sm:mt-2 sm:mr-2 text-red-700 hover:text-red-900" onclick="this.parentElement.style.display='none';" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            <?= $reservation_action_message ?>


            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                <div class="bg-white p-4 sm:p-5 rounded-lg shadow hover:shadow-md transition-shadow flex flex-col">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-600 mb-2 flex items-center"><i class="fas fa-store mr-2 text-[#ff5722]"></i>Your Venues</h3>
                    <p class="text-2xl sm:text-3xl font-bold text-[#ff5722] mt-auto"><?= htmlspecialchars($total_venues_count) ?></p>
                    <p class="text-xs text-gray-500 mt-1">Total venues you manage.</p>
                </div>
                <div class="bg-white p-4 sm:p-5 rounded-lg shadow hover:shadow-md transition-shadow flex flex-col">
                   <h3 class="text-base sm:text-lg font-semibold text-gray-600 mb-2 flex items-center"><i class="fas fa-calendar-alt mr-2 text-green-500"></i>Total Bookings</h3>
                   <p class="text-2xl sm:text-3xl font-bold text-green-600 mt-auto"><?= htmlspecialchars($total_venue_bookings_count) ?></p>
                   <p class="text-xs text-gray-500 mt-1">Total booking requests.</p>
                </div>
                <div class="bg-white p-4 sm:p-5 rounded-lg shadow hover:shadow-md transition-shadow flex flex-col">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-600 mb-2 flex items-center"><i class="fas fa-hourglass-half mr-2 text-yellow-500"></i>Pending Requests</h3>
                    <p class="text-2xl sm:text-3xl font-bold text-yellow-600 mt-auto"><?= htmlspecialchars($pending_reservations_count) ?></p>
                     <p class="text-xs text-gray-500 mt-1">Requests needing confirmation.</p>
                </div>
                <div class="bg-white p-4 sm:p-5 rounded-lg shadow hover:shadow-md transition-shadow flex flex-col">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-600 mb-2 flex items-center"><i class="fas fa-ban mr-2 text-red-500"></i>Cancellations</h3>
                    <p class="text-2xl sm:text-3xl font-bold text-red-600 mt-auto"><?= htmlspecialchars($cancelled_reservations_count) ?></p>
                    <p class="text-xs text-gray-500 mt-1">Cancelled or requested.</p>
                    <a href="<?php echo htmlspecialchars($reservationManagePath); ?>?status_filter=cancelled" class="text-xs text-blue-600 hover:text-blue-800 mt-2 self-start">View Details &rarr;</a>
                </div>
            </section>

            <section class="mb-8">
                <div class="flex flex-col sm:flex-row justify-between items-center mb-4 flex-wrap gap-3 sm:gap-4">
                    <h2 class="text-xl sm:text-2xl font-semibold text-gray-800">Your Venues</h2>
                    <div>
                        <label for="status-filter" class="text-xs sm:text-sm text-gray-600 mr-2">Filter by status:</label>
                        <select id="status-filter" onchange="window.location.href='client_dashboard.php?status='+this.value" class="text-xs sm:text-sm border-gray-300 rounded-md shadow-sm focus:border-orange-500 focus:ring focus:ring-orange-200 focus:ring-opacity-50 py-1 px-2 sm:py-1.5 sm:px-3">
                            <option value="all" <?= ($status_filter ?? 'all') === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="open" <?= ($status_filter ?? '') === 'open' ? 'selected' : '' ?>>Open</option>
                            <option value="closed" <?= ($status_filter ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                    <?php if (count($venues) > 0): ?>
                        <?php foreach ($venues as $venue): ?>
                            <?php
                                $imagePathFromDB = $venue['image_path'] ?? null;
                                $uploadsBaseUrl = '/ventech_locator/uploads/'; // Ensure this is correct for your setup
                                $placeholderImg = 'https://placehold.co/400x400/fbbf24/ffffff?text=No+Image'; // Changed to square placeholder
                                $imgSrc = $placeholderImg;
                                if (!empty($imagePathFromDB)) {
                                    $imgSrc = rtrim($uploadsBaseUrl, '/') . '/' . ltrim(htmlspecialchars($imagePathFromDB), '/');
                                }
                            ?>
                            <div class="border rounded-lg shadow-md overflow-hidden bg-white flex flex-col transition duration-300 ease-in-out hover:shadow-lg relative">
                                <!-- Delete Button -->
                                <button type="button" onclick="confirmDelete(<?= htmlspecialchars($venue['id']) ?>, '<?= htmlspecialchars($venue['title']) ?>')" class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-7 h-7 flex items-center justify-center text-sm font-bold hover:bg-red-600 transition-colors duration-200 z-10" title="Delete Venue">
                                    <i class="fas fa-times"></i>
                                </button>

                                <a href="<?php echo htmlspecialchars($venueDisplayPath); ?>?id=<?= htmlspecialchars($venue['id']) ?>" class="block hover:opacity-90 aspect-square-img-container">
                                    <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($venue['title'] ?? 'Venue Image') ?>" loading="lazy" onerror="this.onerror=null;this.src='<?= $placeholderImg ?>';" />
                                </a>
                                <div class="p-3 sm:p-4 flex flex-col flex-grow">
                                    <div class="flex justify-between items-start mb-1 sm:mb-2">
                                        <h3 class="text-sm sm:text-md font-semibold text-gray-800 leading-tight flex-grow mr-2">
                                            <a href="<?php echo htmlspecialchars($venueDisplayPath); ?>?id=<?= htmlspecialchars($venue['id']) ?>" class="hover:text-[#ff5722]">
                                                <?= htmlspecialchars($venue['title'] ?? 'N/A') ?>
                                            </a>
                                        </h3>
                                        <span class="flex-shrink-0 inline-block px-1.5 sm:px-2 py-0.5 text-xs font-semibold rounded-full <?= getStatusBadgeClass($venue['status']) ?>">
                                            <?= ucfirst(htmlspecialchars($venue['status'] ?? 'unknown')); ?>
                                        </span>
                                    </div>
                                    <div class="text-sm sm:text-base text-gray-600 mb-2 sm:mb-3">
                                        <p class="text-md sm:text-lg font-bold text-gray-900">₱<?= number_format((float)($venue['price'] ?? 0), 2) ?> <span class="text-xs font-normal">/ Hour</span></p>
                                    </div>
                                    <div class="flex items-center text-xs text-gray-500 mb-3 sm:mb-4">
                                         <div class="flex text-yellow-400 mr-1 sm:mr-1.5">
                                             <?php for($i=0; $i<5; $i++): ?><i class="fas fa-star<?= ($i < ($venue['reviews_avg'] ?? 0) ? '' : ($i < ceil($venue['reviews_avg'] ?? 0) ? '-half-alt' : ' far fa-star')) ?>"></i><?php endfor; // Example stars, replace with actual review logic ?>
                                         </div>
                                         <span>(<?= htmlspecialchars($venue['reviews'] ?? 0) ?> Reviews)</span>
                                    </div>
                                    <div class="mt-auto pt-2 sm:pt-3 border-t border-gray-200 flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                                         <a href="<?php echo htmlspecialchars($venueDisplayPath); ?>?id=<?= htmlspecialchars($venue['id']) ?>" title="View Public Page" class="flex-1 inline-flex items-center justify-center bg-gray-500 hover:bg-gray-600 text-white text-xs font-medium py-1.5 px-2 sm:px-3 rounded shadow-sm transition duration-150 ease-in-out">
                                            <i class="fas fa-eye mr-1"></i> View
                                        </a>
                                         <a href="<?php echo htmlspecialchars($editVenuePath); ?>?id=<?= htmlspecialchars($venue['id']) ?>" title="Edit Details" class="flex-1 inline-flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white text-xs font-medium py-1.5 px-2 sm:px-3 rounded shadow-sm transition duration-150 ease-in-out">
                                            <i class="fas fa-edit mr-1"></i> Edit
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="col-span-full text-gray-600 bg-white p-6 rounded-lg shadow text-center">
                            You haven't added any venues yet<?php if ($status_filter !== 'all') echo " matching status '" . htmlspecialchars($status_filter) . "'"; ?>.
                             <a href="javascript:void(0);" onclick="openAddVenueModal();" class="text-[#ff5722] hover:underline font-medium ml-1">Add your first venue now!</a>
                        </p>
                    <?php endif; ?>
                </div>
            </section>

            <section>
                <div class="flex flex-col sm:flex-row justify-between items-center mb-4 flex-wrap gap-3 sm:gap-4">
                    <h2 class="text-xl sm:text-2xl font-semibold text-gray-800">Recent Booking Requests</h2>
                     <a href="<?php echo htmlspecialchars($reservationManagePath); ?>" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                        Manage All Bookings &rarr;
                    </a>
                </div>
                <div class="bg-white shadow-md rounded-lg overflow-x-auto responsive-table-container table-container">
                    <?php if (count($recent_venue_reservations) > 0): ?>
                        <table class="w-full table-auto text-xs sm:text-sm text-left">
                            <thead class="bg-gray-100 text-xs text-gray-600 uppercase table-sticky-header table-header">
                                <tr>
                                    <th scope="col" class="table-cell">Booker</th>
                                    <th scope="col" class="table-cell">Venue</th>
                                    <th scope="col" class="table-cell hidden md:table-cell">Event Date</th>
                                    <th scope="col" class="table-cell">Status</th>
                                    <th scope="col" class="table-cell hidden lg:table-cell">Requested On</th>
                                    <th scope="col" class="table-cell">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_venue_reservations as $reservation): ?>
                                <tr class="table-row">
                                    <td class="table-cell font-medium" title="<?= htmlspecialchars($reservation['booker_email'] ?? '') ?>">
                                         <?= htmlspecialchars($reservation['booker_username'] ?? 'N/A') ?>
                                    </td>
                                    <td class="table-cell font-medium">
                                         <a href="<?php echo htmlspecialchars($venueDisplayPath); ?>?id=<?= htmlspecialchars($reservation['venue_id'] ?? '') ?>" class="table-link" title="View Venue">
                                            <?= htmlspecialchars($reservation['venue_title'] ?? 'N/A') ?>
                                        </a>
                                    </td>
                                    <td class="table-cell hidden md:table-cell">
                                        <?= htmlspecialchars(date("D, M d, Y", strtotime($reservation['event_date'] ?? ''))) ?>
                                    </td>
                                    <td class="table-cell">
                                        <span class="status-badge <?= getStatusBadgeClass($reservation['status']) ?>">
                                            <?= htmlspecialchars(ucfirst($reservation['status'] ?? 'N/A')) ?>
                                        </span>
                                    </td>
                                    <td class="table-cell text-gray-600 hidden lg:table-cell">
                                        <?= htmlspecialchars(date("M d, Y H:i", strtotime($reservation['created_at'] ?? ''))) ?>
                                    </td>
                                    <td class="table-cell">
                                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-1 sm:gap-2">
                                         <?php if (strtolower($reservation['status'] ?? '') === 'pending'): ?>
                                             <form method="post" action="process_reservation_action.php" class="inline-block">
                                                 <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($reservation['id']) ?>">
                                                 <input type="hidden" name="action" value="accept">
                                                 <button type="submit" class="bg-green-500 hover:bg-green-600 text-white text-xs font-medium py-1 px-1.5 sm:px-2 rounded focus:outline-none focus:shadow-outline w-full sm:w-auto">Accept</button>
                                             </form>
                                              <form method="post" action="process_reservation_action.php" class="inline-block">
                                                 <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($reservation['id']) ?>">
                                                 <input type="hidden" name="action" value="reject">
                                                 <button type="submit" class="bg-red-500 hover:bg-red-600 text-white text-xs font-medium py-1 px-1.5 sm:px-2 rounded focus:outline-none focus:shadow-outline w-full sm:w-auto">Reject</button>
                                             </form>
                                         <?php else: ?>
                                             <span class="text-gray-500 text-xs italic">No pending actions</span>
                                         <?php endif; ?>
                                          <a href="<?php echo htmlspecialchars($reservationManagePath); ?>?id=<?= htmlspecialchars($reservation['id'] ?? '') ?>" class="table-link">View Details</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="p-6 text-center text-gray-600">No booking requests received for your venues yet.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="py-12 px-4 max-w-7xl mx-auto text-center">
                <h2 class="text-gray-700 text-xl md:text-2xl font-semibold mb-2">
                    Why VenTech?
                </h2>
                <p class="text-gray-500 text-xs md:text-sm max-w-xl mx-auto mb-10">
             Whatever the activities, wherever the facility, Ventech Locator makes it easy for owners to manage bookings and venues with simple steps.
                </p>
                <div class="flex flex-col md:flex-row justify-center gap-6 md:gap-8">
                    <div class="bg-white rounded-md shadow-sm p-6 md:w-1/3">
                        <img alt="Illustration of a calendar with a checkmark, symbolizing managing reservations" class="mx-auto mb-4" height="80" src="https://storage.googleapis.com/a1aa/image/98612075-7d8d-4a65-6479-9397a0b0a393.jpg" width="80"/>
                        <h3 class="text-gray-700 font-semibold text-base mb-2">
                            Manage Reservations
                        </h3>
                        <p class="text-gray-600 text-xs leading-relaxed">
                            Effortlessly accept, reject, or manage all your venue booking requests in one centralized place.
                        </p>
                    </div>
                    <div class="bg-white rounded-md shadow-sm p-6 md:w-1/3">
                        <img alt="Illustration of a computer screen with data charts, symbolizing insights" class="mx-auto mb-4" height="80" src="https://storage.googleapis.com/a1aa/image/ed2722ae-42d7-473e-e410-9a19307c9f65.jpg" width="80"/>
                        <h3 class="text-gray-700 font-semibold text-base mb-2">
                           Gain Insights
                        </h3>
                        <p class="text-gray-600 text-xs leading-relaxed">
                            Track your venue's performance with simple dashboards, showing total bookings, pending requests, and more.
                        </p>
                    </div>
                    <div class="bg-white rounded-md shadow-sm p-6 md:w-1/3">
                        <img alt="Illustration of a map pin with a crown, symbolizing venue ownership" class="mx-auto mb-4" height="80" src="https://storage.googleapis.com/a1aa/image/471152ab-5119-4369-de9c-50a40c7405f6.jpg" width="80"/>
                        <h3 class="text-gray-700 font-semibold text-base mb-2">
                           Optimize Your Listing
                        </h3>
                        <p class="text-gray-600 text-xs leading-relaxed">
                            Easily edit and update your venue details, photos, and availability to attract more bookings.
                        </p>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Add Venue Modal -->
    <div id="addVenueModal" class="modal">
        <div class="modal-content">
            <button type="button" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600" onclick="closeAddVenueModal();" aria-label="Close modal">
                <i class="fas fa-times text-xl"></i>
            </button>
            <h2 class="text-xl font-semibold text-gray-800 mb-6">Add New Venue</h2>
            <form id="addVenueForm" method="POST" action="/ventech_locator/client/add_venue.php" enctype="multipart/form-data">
                <div class="mb-5">
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Venue Title</label>
                    <input type="text" id="title" name="title" value="" placeholder="e.g., The Grand Ballroom" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none transition duration-150 ease-in-out text-sm">
                </div>

                <div class="mb-5">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="description" name="description" placeholder="Describe the venue, its features, capacity, and suitability for events..." required
                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none transition duration-150 ease-in-out text-sm min-h-[100px]"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-5">
                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700 mb-1">Price per Hour</label>
                        <div class="relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">₱</span>
                            </div>
                            <input type="number" id="price" name="price" value="" placeholder="e.g., 5000.00" min="0.01" step="0.01" required
                                   class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none transition duration-150 ease-in-out text-sm">
                        </div>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Initial Status</label>
                        <select id="status" name="status" required
                                class="w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none transition duration-150 ease-in-out text-sm">
                            <option value="open">Open (Available for Booking)</option>
                            <option value="closed">Closed (Not Available)</option>
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
                            class="w-full flex justify-center items-center bg-[#ff5722] hover:bg-[#e64a19] text-white font-bold py-2.5 px-4 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#ff5722] transition duration-150 ease-in-out">
                        <i class="fas fa-plus-circle mr-2"></i> Add Venue
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirmation Modal for Deletion -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content">
            <h3 class="text-lg font-bold text-gray-800">Confirm Deletion</h3>
            <p class="text-gray-700">Are you sure you want to delete "<span id="venueToDeleteName" class="font-semibold"></span>"? This action cannot be undone.</p>
            <div class="button-group">
                <button id="cancelDeleteBtn" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md transition duration-150 ease-in-out">
                    Cancel
                </button>
                <form id="deleteVenueForm" method="POST" action="<?= htmlspecialchars($deleteVenueEndpoint) ?>" class="inline-block">
                    <input type="hidden" name="venue_id" id="deleteVenueId">
                    <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-md shadow-sm transition duration-150 ease-in-out">
                        Yes, Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Chatbot Bubble and Window -->
    <div id="chat-bubble" class="chat-bubble">
        <i class="fas fa-comments"></i>
    </div>

    <div id="chat-window" class="chat-window">
        <div class="chat-header">
            <span>AI Assistant</span>
            <button id="close-chat" class="close-btn">&times;</button>
        </div>
        <div id="chat-messages" class="chat-messages">
            </div>
        <div class="chat-input-container">
            <input type="text" id="chat-input" class="chat-input" placeholder="Type your message...">
            <button id="send-chat" class="chat-send-btn">Send</button>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const mobileMenu = document.getElementById('mobile-menu');

            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                });
            }

            // Client-specific notification count (pending reservations)
            function fetchClientNotificationCount() {
                // Assuming you have an endpoint for this, e.g., '/ventech_locator/client/get_pending_reservations_count.php'
                // For now, it's PHP-rendered, but if you want dynamic updates:
                // fetch('/ventech_locator/client/get_pending_reservations_count.php')
                //     .then(response => response.json())
                //     .then(data => {
                //         const badge = document.getElementById('client-notification-count-badge');
                //         if (badge) {
                //             if (data.count > 0) {
                //                 badge.textContent = data.count;
                //                 badge.style.display = 'inline-block';
                //             } else {
                //                 badge.style.display = 'none';
                //             }
                //         }
                //     })
                //     .catch(error => console.error('Error fetching client notification count:', error));
            }

            // Initial check for notification badge (it's populated by PHP on load)
            // If you later implement dynamic updates, uncomment the fetchClientNotificationCount calls.
            // fetchClientNotificationCount();
            // setInterval(fetchClientNotificationCount, 30000); // Check every 30 seconds

            // Modal elements
            const addVenueModal = document.getElementById('addVenueModal');
            const addVenueForm = document.getElementById('addVenueForm');
            const modalCloseButton = addVenueModal.querySelector('.fa-times');

            // Form elements within the modal
            const imageInput = addVenueForm.querySelector('#image');
            const imagePreview = addVenueForm.querySelector('#imagePreview');
            const fileNameSpan = addVenueForm.querySelector('#fileName');

            // Loading overlay elements
            const loadingOverlay = document.getElementById('loading-overlay');

            // Confirmation Modal elements
            const confirmationModal = document.getElementById('confirmationModal');
            const venueToDeleteNameSpan = document.getElementById('venueToDeleteName');
            const deleteVenueIdInput = document.getElementById('deleteVenueId');
            const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
            const deleteVenueForm = document.getElementById('deleteVenueForm');


            // --- Add Venue Modal Functions ---
            window.openAddVenueModal = function() {
                addVenueModal.classList.add('show');
                resetAddVenueForm();
            };

            window.closeAddVenueModal = function() {
                addVenueModal.classList.remove('show');
            };

            if (modalCloseButton) {
                modalCloseButton.addEventListener('click', closeAddVenueModal);
            }

            // Close modal if clicking outside the content
            addVenueModal.addEventListener('click', function(event) {
                if (event.target === addVenueModal) {
                    closeAddVenueModal();
                }
            });

            // --- Form Reset Function ---
            function resetAddVenueForm() {
                addVenueForm.reset();
                fileNameSpan.textContent = 'No file chosen';
                imagePreview.src = '#';
                imagePreview.classList.add('hidden');
                addVenueForm.querySelector('#status').value = 'open';
            }

            // --- Image Preview Logic ---
            if (imageInput && imagePreview && fileNameSpan) {
                imageInput.addEventListener('change', function(event) {
                    const file = event.target.files[0];

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
                });
            }

            // --- Loading Overlay JavaScript (from add_venue.php) ---
            // Show loading overlay when the form is submitted
            if (addVenueForm && loadingOverlay) {
                addVenueForm.addEventListener('submit', function() {
                    loadingOverlay.classList.add('visible');
                });
            }

            // --- Venue Deletion Confirmation Logic ---
            window.confirmDelete = function(venueId, venueTitle) {
                venueToDeleteNameSpan.textContent = venueTitle;
                deleteVenueIdInput.value = venueId;
                confirmationModal.classList.add('show');
            };

            cancelDeleteBtn.addEventListener('click', function() {
                confirmationModal.classList.remove('show');
            });

            // Close confirmation modal if clicking outside the content
            confirmationModal.addEventListener('click', function(event) {
                if (event.target === confirmationModal) {
                    confirmationModal.classList.remove('show');
                }
            });

            // Show loading overlay when delete form is submitted
            if (deleteVenueForm && loadingOverlay) {
                deleteVenueForm.addEventListener('submit', function() {
                    loadingOverlay.classList.add('visible');
                });
            }

            // --- Chatbot Logic (Copied from user_dashboard.php) ---
            const chatBubble = document.getElementById('chat-bubble');
            const chatWindow = document.getElementById('chat-window');
            const closeChatBtn = document.getElementById('close-chat');
            const chatMessages = document.getElementById('chat-messages');
            const chatInput = document.getElementById('chat-input');
            const sendChatBtn = document.getElementById('send-chat');

            let chatHistory = [{ role: "model", parts: [{ text: "Hello! How can I assist you today?" }] }];

            function appendMessage(text, sender) {
                const messageDiv = document.createElement('div');
                messageDiv.classList.add('message', sender);
                messageDiv.textContent = text;
                chatMessages.appendChild(messageDiv);
                return messageDiv;
            }

            chatBubble.addEventListener('click', () => {
                chatWindow.classList.toggle('open');
                if (chatWindow.classList.contains('open')) {
                    if (chatMessages.children.length === 0) {
                        appendMessage(chatHistory[0].parts[0].text, 'bot');
                    }
                    chatInput.focus();
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            });

            closeChatBtn.addEventListener('click', () => {
                chatWindow.classList.remove('open');
            });

            sendChatBtn.addEventListener('click', sendMessage);
            chatInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    sendMessage();
                }
            });

            async function sendMessage() {
                const userMessage = chatInput.value.trim();
                if (userMessage === '') return;

                appendMessage(userMessage, 'user');
                chatInput.value = '';
                chatMessages.scrollTop = chatMessages.scrollHeight;

                chatHistory.push({ role: "user", parts: [{ text: userMessage }] });

                const loadingMessageDiv = appendMessage('...', 'bot loading');
                chatMessages.scrollTop = chatMessages.scrollHeight;

                try {
                    const payload = { contents: chatHistory };
                    const apiKey = "";
                    const apiUrl = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=${apiKey}`;

                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });

                    const result = await response.json();

                    chatMessages.removeChild(loadingMessageDiv);

                    if (result.candidates && result.candidates.length > 0 &&
                        result.candidates[0].content && result.candidates[0].content.parts &&
                        result.candidates[0].content.parts.length > 0) {
                        const botResponse = result.candidates[0].content.parts[0].text;
                        appendMessage(botResponse, 'bot');
                        chatHistory.push({ role: "model", parts: [{ text: botResponse }] });
                    } else {
                        appendMessage("Sorry, I couldn't get a response. Please try again.", 'bot');
                        console.error('Unexpected API response structure:', result);
                    }
                } catch (error) {
                    chatMessages.removeChild(loadingMessageDiv);
                    appendMessage("Error connecting to the assistant. Please try again later.", 'bot');
                    console.error('Error fetching from Gemini API:', error);
                }
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        });

        // Hide loading overlay with 4-second minimum and immediate hide on load after that
        window.addEventListener('load', function() {
            const loadingOverlay = document.getElementById('loading-overlay');
            let minLoadTimePassed = false;
            let pageFullyLoaded = false;

            // Set a timeout for the minimum 4-second display
            setTimeout(() => {
                minLoadTimePassed = true;
                // If page has already fully loaded AND minimum time has passed, hide it.
                if (pageFullyLoaded && loadingOverlay) {
                    loadingOverlay.classList.add('hidden');
                    loadingOverlay.addEventListener('transitionend', function handler() {
                        if (loadingOverlay.classList.contains('hidden')) {
                            loadingOverlay.remove();
                            loadingOverlay.removeEventListener('transitionend', handler);
                        }
                    });
                }
            }, 4000); // 4000 milliseconds = 4 seconds

            pageFullyLoaded = true;
            // If minimum time has already passed, hide it.
            if (minLoadTimePassed && loadingOverlay) {
                loadingOverlay.classList.add('hidden');
                loadingOverlay.addEventListener('transitionend', function handler() {
                    if (loadingOverlay.classList.contains('hidden')) {
                        loadingOverlay.remove();
                        loadingOverlay.removeEventListener('transitionend', handler);
                    }
                });
            }
        });
    </script>

</body>
</html>