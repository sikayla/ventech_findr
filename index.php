<?php
// **1. Start Session** (MUST be the very first thing)
session_start();

// **2. Database Connection Parameters**
$host = 'localhost';
$db   = 'ventech_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
       PDO   ::ATTR_ERRMODE            =>    PDO   ::ERRMODE_EXCEPTION,
       PDO   ::ATTR_DEFAULT_FETCH_MODE =>    PDO   ::FETCH_ASSOC,
       PDO   ::ATTR_EMULATE_PREPARES   => false, // Good practice
];

// **3. Initialize User Session Variables**
$isLoggedIn = isset($_SESSION['user_id']);
$username = '';
$userRole = '';
$dashboardLink = '#'; // Default link
$logoutLink = '#'; // Default link

// **4. Establish PDO Connection and Fetch Data**
try {
    $pdo = new    PDO   ($dsn, $user, $pass, $options);

    // **Handle Guest Sign-in**
    if (isset($_GET['guest_signin']) && $_GET['guest_signin'] === 'true') {
        // Create a new guest user
        $stmt_guest = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, 'guest')");
        $guest_username = 'guest_' . time(); // Unique guest username
        $stmt_guest->execute([
            ':username' => $guest_username,
            ':email' => 'guest_' . time() . '@example.com', // Unique guest email
            ':password' => password_hash('guest_password', PASSWORD_DEFAULT), // Placeholder password
        ]);
        $guest_user_id = $pdo->lastInsertId();
        $_SESSION['user_id'] = $guest_user_id;
        header("Location: /ventech_locator/index.php"); // Redirect guest to venue list
        exit();
    }

    // **5. Check Session and Fetch User Data if Logged In**
    if ($isLoggedIn) {
        $loggedInUserId = $_SESSION['user_id'];
        // Prepare statement to fetch username and role
        $stmt_user = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
        $stmt_user->execute([$loggedInUserId]);
        $userData = $stmt_user->fetch();

        if ($userData) {
            $username = $userData['username'];
            $userRole = strtolower($userData['role'] ?? 'guest'); // Default to 'guest' if role is null/missing

            // Determine dashboard link based on role (ADJUST PATHS AS NEEDED)
            if ($userRole === 'client' || $userRole === 'admin' || $userRole === 'owner') {
                $dashboardLink = '/ventech_locator/client_dashboard.php';
            } else { // Default to user/guest dashboard (or profile page)
                $dashboardLink = '/ventech_locator/users/user_profile.php'; // Assuming user profile exists
            }
            $logoutLink = '/ventech_locator/client/client_logout.php';

        } else {
            // User ID in session doesn't exist in DB - clear invalid session
            error_log("Invalid user ID found in session on index.php: " . $loggedInUserId);
            session_unset();
            session_destroy();
            $isLoggedIn = false; // Update login status
            // No redirection here, let the landing page load for non-logged-in users
        }
    } // End session check

    // **6. Fetch Venues** (Fetch regardless of login status)
    // --- THIS SELECT QUERY FETCHES THE 'price_per_hour' COLUMN ---
    // The query now includes a JOIN with the users table to get client_address
    $stmt_venues = $pdo->prepare("
        SELECT
            v.id, v.title, v.image_path, v.price, v.status, v.reviews, v.location,
            v.num_persons, v.description, v.amenities, v.google_map_url,
            v.latitude, v.longitude, u.client_address
        FROM venue v
        JOIN users u ON v.user_id = u.id
        WHERE v.status IN ('open', 'closed')
        ORDER BY v.created_at DESC
        LIMIT 4
    "); // Limit displayed venues to 4
    $stmt_venues->execute();
    $venues = $stmt_venues->fetchAll();

} catch (  PDOException   $e) {
    error_log("Database error on index.php: " . $e->getMessage());
    // Display a user-friendly error message but don't reveal details
    echo "<div style='color:red; padding:10px; border:1px solid red; background-color:#ffe0e0; margin:10px;'>";
    echo "Sorry, we encountered a problem loading the page content. Please try again later.";
    echo "</div>";
    // You might want to die() here or just let the rest of the page render without DB data
    $venues = []; // Ensure $venues is an empty array if DB fails
    // die(); // Uncomment if you want to stop execution on DB error
}

// --- Helper function for status badges (Copied from client_dashboard for consistency) ---
function   getStatusBadgeClass($status) {
    $status = strtolower($status ?? 'unknown');
    switch ($status) {
        case 'open': case 'confirmed': case 'accepted': case 'completed': return 'bg-green-100 text-green-800'; // Group successful/active statuses
        case 'closed': case 'cancelled': case 'rejected': case 'cancellation_requested': return 'bg-red-100 text-red-800'; // Group negative statuses including cancellation requested
        case 'pending': return 'bg-yellow-100 text-yellow-800'; // Pending status
        default: return 'bg-gray-100 text-gray-800'; // Default for unknown or other statuses
    }
}

/**
 * Attempts to extract a simple place name from a Google Maps URL.
 * This is a heuristic and may not always yield perfect results.
 * @param string $url Google Maps URL
 * @return string|null The extracted name or null if not found
 */
function extractPlaceNameFromGoogleMapsUrl($url) {
    if (empty($url) || !is_string($url)) {
        return null;
    }

    // Pattern for /maps/place/NAME/
    if (preg_match('/maps\/place\/([^\/]+)\//', $url, $matches)) {
        return urldecode(str_replace('+', ' ', $matches[1]));
    }
    // Pattern for /maps/search/NAME/
    if (preg_match('/maps\/search\/([^\/]+)\//', $url, $matches)) {
        return urldecode(str_replace('+', ' ', $matches[1]));
    }
    // Pattern for /maps/dir/FROM/TO
    if (preg_match('/maps\/dir\/[^\/]+\/([^\/]+)\//', $url, $matches)) {
        return urldecode(str_replace('+', ' ', $matches[1]));
    }
    // Fallback for coordinates, try to use them as a simple string
    if (preg_match('/@(-?\d+\.?\d*),(-?\d+\.?\d*)/', $url, $matches)) {
        return "Lat: " . $matches[1] . ", Lon: " . $matches[2];
    }
    // More general pattern for query parameters like ?q=NAME
    if (preg_match('/[?&]q=([^&]+)/', $url, $matches)) {
        return urldecode(str_replace('+', ' ', $matches[1]));
    }

    return null; // No recognizable pattern found
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Ventech Locator - Find Your Perfect Venue</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    <link rel="stylesheet" href="/ventech_locator/css/index.css">
    <style>
     
    </style>
</head>
<body class="font-inter bg-gray-100 antialiased">
    <header class="relative">
        <img src="/ventech_locator/images/act.png" alt="Modern Venue Space" class="w-full h-96 object-cover brightness-75" />
        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/50 to-transparent flex flex-col justify-between items-center text-center text-white p-4 md:p-8">

            <div class="w-full flex justify-between items-center hero-overlay-content fade-in px-4 md:px-0">
                <a href="index.php">
                    <img src="/ventech_locator/images/logo.png" class="h-10 md:h-12 transition transform hover:scale-110 duration-300" alt="Ventech Locator Logo" />
                </a>

                <div class="md:hidden">
                    <button id="hamburgerButton" class="text-white focus:outline-none">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                </div>

                <nav class="hidden md:flex space-x-4 md:space-x-6 lg:space-x-8 items-center text-sm md:text-base">
                    <a class="hover:text-yellow-400 transition duration-150 fade-in fade-in-1 font-medium" href="index.php">HOME</a>
                    <a class="hover:text-yellow-400 transition duration-150 fade-in fade-in-2 font-medium" href="user_venue_list.php">VENUE LIST</a>

                    <?php if ($isLoggedIn): ?>
                        <a class="hover:text-yellow-400 transition duration-150 fade-in fade-in-3 font-medium flex items-center" href="<?= htmlspecialchars($dashboardLink) ?>">
                            <i class="fas fa-user-circle mr-2"></i> Welcome, <?= htmlspecialchars($username) ?>!
                        </a>
                        <a class="hover:text-yellow-400 transition duration-150 fade-in fade-in-3 font-medium flex items-center" href="<?= htmlspecialchars($logoutLink) ?>">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
                    <?php else: ?>
                        <div class="relative fade-in fade-in-3">
                            <button class="hover:text-yellow-400 transition duration-150 focus:outline-none font-medium flex items-center" id="signInButton" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-sign-in-alt mr-2"></i> SIGN IN
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 hidden z-30 transition-all duration-300 ease-in-out origin-top-right" id="dropdownMenu" role="menu" aria-orientation="vertical" aria-labelledby="signInButton">
                                <a href="javascript:void(0);" onclick="openUserLoginModal();" class="block px-4 py-2 text-sm text-gray-700 hover:bg-yellow-100 hover:text-gray-900 transition-colors duration-150 rounded-t-md" role="menuitem">User Login</a>
                                <a href="javascript:void(0);" onclick="openClientLoginModal();" class="block px-4 py-2 text-sm text-gray-700 hover:bg-yellow-100 hover:text-gray-900 transition-colors duration-150" role="menuitem">Client/Owner Login</a>
                                <a href="/ventech_locator/admin/login.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-yellow-100 hover:text-gray-900 transition-colors duration-150" role="menuitem">Admin Login</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </nav>
            </div>

            <div id="mobileMenu" class="fixed top-0 right-0 w-64 h-full bg-gray-800 text-white p-6 transform translate-x-full md:hidden transition-transform duration-300 ease-in-out z-50">
                <button id="closeMobileMenu" class="absolute top-4 right-4 text-white focus:outline-none">
                    <i class="fas fa-times text-2xl"></i>
                </button>
                <nav class="flex flex-col space-y-6 mt-12">
                    <a class="text-lg hover:text-yellow-400 transition duration-150 font-medium" href="index.php">HOME</a>
                    <a class="text-lg hover:text-yellow-400 transition duration-150 font-medium" href="user_venue_list.php">VENUE LIST</a>
                    <?php if ($isLoggedIn): ?>
                        <a class="text-lg hover:text-yellow-400 transition duration-150 font-medium flex items-center" href="<?= htmlspecialchars($dashboardLink) ?>">
                            <i class="fas fa-user-circle mr-2"></i> Welcome, <?= htmlspecialchars($username) ?>!
                        </a>
                        <a class="text-lg hover:text-yellow-400 transition duration-150 font-medium flex items-center" href="<?= htmlspecialchars($logoutLink) ?>">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
                    <?php else: ?>
                        <div class="relative">
                            <button class="text-lg hover:text-yellow-400 transition duration-150 focus:outline-none font-medium flex items-center w-full text-left" id="mobileSignInButton" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-sign-in-alt mr-2"></i> SIGN IN
                            </button>
                            <div class="mt-2 w-full bg-gray-700 rounded-md shadow-lg py-1 hidden transition-all duration-300 ease-in-out origin-top-right" id="mobileDropdownMenu" role="menu" aria-orientation="vertical" aria-labelledby="mobileSignInButton">
                                <a href="javascript:void(0);" onclick="openUserLoginModal();" class="block px-4 py-2 text-base text-gray-200 hover:bg-yellow-600 hover:text-white transition-colors duration-150 rounded-t-md" role="menuitem">User Login</a>
                                <a href="javascript:void(0);" onclick="openUserSignupModal();" class="block px-4 py-2 text-base text-gray-200 hover:bg-yellow-600 hover:text-white transition-colors duration-150" role="menuitem">User Signup</a>
                                <a href="javascript:void(0);" onclick="openClientLoginModal();" class="block px-4 py-2 text-base text-gray-200 hover:bg-yellow-600 hover:text-white transition-colors duration-150" role="menuitem">Client/Owner Login</a>
                                <a href="/ventech_locator/admin/login.php" class="block px-4 py-2 text-base text-gray-200 hover:bg-yellow-600 hover:text-white transition-colors duration-150" role="menuitem">Admin Login</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </nav>
            </div>

            <div class="hero-overlay-content flex flex-col items-center justify-center flex-grow pb-12 md:pb-16 px-4 md:px-0">

                <h2 class="text-4xl md:text-5xl lg:text-6xl text-yellow-500 font-bold my-2 md:my-3 fade-in fade-in-5 drop-shadow-lg">Ventech Locator</h2>
                <p class="text-base md:text-lg text-gray-200 mb-4 md:mb-6 max-w-xl fade-in fade-in-6 leading-relaxed">Discover and book the perfect venue for your next event.</p>
                <a href="user_venue_list.php" class="mt-2 px-8 py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-bold rounded-full shadow-lg transition transform hover:scale-105 duration-300 fade-in fade-in-7">
                    Explore Venues
                </a>
            </div>

            <div></div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-16 md:py-20 bg-grey rounded-lg mb-12">
        <div class="flex flex-col md:flex-row items-center md:items-center gap-12 md:gap-24">
             <div class="max-w-xl w-full space-y-6">
                <h1 class="text-3xl md:text-4xl font-bold leading-tight mb-4 fade-in fade-in-1 text-gray-800">Get your venue seen and booked — for FREE!</h1>
                <p class="text-base md:text-lg mb-8 max-w-md fade-in fade-in-2 text-gray-700 leading-relaxed">
                            Student Activities: Find Your Perfect Space.
                </p>
                <ul class="space-y-5 mb-10 max-w-md">
                    <li class="flex items-start gap-4 fade-in fade-in-3">
                        <i class=""></i> <span class="text-base md:text-lg text-gray-700"> 📢 Promote Your Venue for Free List your venue easily and get discovered by students.</span>
                    </li>
                    <li class="flex items-start gap-4 fade-in fade-in-4">
                        <i class=""></i> <span class="text-base md:text-lg text-gray-700"> ✏️ Edit Your Listing Anytime Update your photos, details, and contact info anytime.</span>
                    </li>
                    <li class="flex items-start gap-4 fade-in fade-in-5">
                        <i class=""></i> <span class="text-base md:text-lg text-gray-700"> 📩 Receive Booking Requests Online No paperwork, no hassle. Just direct messages from students.</span>
                    </li>
                    <li class="flex items-start gap-4 fade-in fade-in-6">
                        <i class=""></i> <span class="text-base md:text-lg text-gray-700"> 📈 Grow Your Venue’s Popularity - More exposure means more events and more inquiries.</span>
                    </li>
                    <li class="flex items-start gap-4 fade-in fade-in-7">
                        <i class=""></i> <span class="text-base md:text-lg text-gray-700"> 🌍 Reach More People - Be visible online – we help with SEO and local visibility.</span>
                    </li>
                </ul>
                <button id="listVenueButton" class="bg-[#f4f94e] text-black font-semibold rounded-full px-8 py-3 text-base md:text-lg hover:bg-blue-400 transition transform hover:scale-105 duration-300 shadow-md" type="button"> List Your Venue
                </button>
            </div>
            
            <div class="flex-shrink-0 w-full max-w-md md:max-w-none md:w-[500px] fade-in">
                <img
                    alt="Atome card image showing three credit cards floating on a bright blue background"
                    class="w-full h-auto object-contain rounded-lg shadow-md transition transform hover:scale-105 duration-300" src="/ventech_locator/images/locations.gif"
                />
            </div>
           
        </div>
    </main>



    <main class="max-w-7xl mx-auto px-6 py-16 md:py-20 mt-12"> <!-- Added mt-12 here -->
        <div class="container mx-auto px-4 md:px-8">
            <div class="max-w-[1200px] mx-auto px-4 py-8">
                <div class="flex items-center gap-2 text-blue-600 font-semibold text-lg mb-4">
                    <i class="fas fa-building"></i>
                    <span> Featured Venues & Student Spaces</span>
                </div>
                <h2 class="font-semibold text-xl mb-2">
                    <span class="font-extrabold">Eas</span>ily Find the Right Place for Your Next Event
                </h2>
                <p class="text-gray-600 text-sm max-w-[600px] mb-8">
             Looking for a place to hold your club meeting, small event, or student activity? We’ve gathered the best and most convenient venues around Bacoor Cavite Campus just for you. Whether it’s for a group project, celebration, or campus event — there's a space that fits your needs and budget.
                </p>
                <div class="flex justify-end mb-6">
                    <a class="text-blue-600 text-sm font-semibold flex items-center gap-1 hover:underline" href="user_venue_list.php">
                        Explore Venues
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php if (!empty($venues)): ?>
                        <?php foreach ($venues as $venue):
                            // --- Image Path Logic (Keep your existing logic) ---
                            $imagePathFromDB = $venue['image_path'] ?? null;
                            $uploadsBaseUrl = '/ventech_locator/uploads/'; // Adjust if needed
                            $placeholderImg = 'https://placehold.co/400x250/cccccc/666666?text=No+Image'; // Adjusted placeholder
                            $imgSrc = $placeholderImg;
                            if (!empty($imagePathFromDB)) {
                                if (filter_var($imagePathFromDB, FILTER_VALIDATE_URL)) {
                                    $imgSrc = htmlspecialchars($imagePathFromDB);
                                } else {
                                    $imgSrc = $uploadsBaseUrl . ltrim(htmlspecialchars($imagePathFromDB), '/');
                                    // Optional: File existence check
                                    // $filesystemPath = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $uploadsBaseUrl . ltrim($imagePathFromDB, '/');
                                    // if (!file_exists($filesystemPath)) { $imgSrc = $placeholderImg; }
                                }
                            }
                            // --- End Image Path Logic ---

                            // --- Get Price, Status, Reviews, Location, Num Persons, Description, Amenities ---
                            $pricePerHour = number_format($venue['price'] ?? 0, 2);
                            $locationText = htmlspecialchars($venue['location'] ?? ''); // Get location from venue table
                            $clientAddress = htmlspecialchars($venue['client_address'] ?? ''); // Get client_address from joined users table
                            $numPersons = htmlspecialchars($venue['num_persons'] ?? 'N/A'); // Assuming num_persons is for users
                            $description = htmlspecialchars(substr($venue['description'] ?? 'No description available.', 0, 100)) . '...'; // Truncate description
                            $amenities = explode(',', $venue['amenities'] ?? ''); // Split amenities into an array

                            // Determine location to display on card (prioritize client_address, then venue.location, then try to extract from Google Maps URL)
                            if (!empty($clientAddress)) {
                                $locationText = $clientAddress;
                            } elseif (empty($locationText)) { // If venue.location is empty
                                if (!empty($venue['google_map_url'])) {
                                    $extractedName = extractPlaceNameFromGoogleMapsUrl($venue['google_map_url']);
                                    $locationText = $extractedName ?: 'Address not available';
                                } elseif (!empty($venue['latitude']) && !empty($venue['longitude'])) {
                                    $locationText = "Lat: " . $matches[1] . ", Lon: " . $matches[2];
                                } else {
                                    $locationText = 'Address not available'; // Final fallback if all else fails
                                }
                            }

                            // Prepare location for Google Maps URL for "Get Directions" button
                            if (!empty($venue['google_map_url'])) {
                                $directionsUrl = htmlspecialchars($venue['google_map_url']);
                            } elseif (!empty($venue['latitude']) && !empty($venue['longitude'])) {
                                $directionsUrl = "http://maps.google.com/maps?q=" . urlencode($venue['latitude'] . "," . $venue['longitude']) . "&travelmode=driving";
                            } else {
                                // Use the displayed location text for directions if no specific URL/coords are available
                                $directionsUrl = "http://maps.google.com/maps?q=" . urlencode($locationText);
                            }
                            ?>
                            <div class="venue-card-link"> <!-- Changed from <a> to <div> to contain multiple buttons -->
                                <div class="relative rounded-t-lg overflow-hidden">
                                    <img alt="<?= htmlspecialchars($venue['title'] ?? 'Venue Image') ?>" class="w-full h-[200px] object-cover rounded-t-lg" height="200" src="<?= $imgSrc ?>" width="320" loading="lazy" onerror="this.onerror=null;this.src='<?= $placeholderImg ?>';"/>
                                    </div>
                                <h3 class="mt-3 font-semibold text-sm truncate px-3">
                                    <?= htmlspecialchars($venue['title'] ?? 'N/A') ?>
                                </h3>
                                <p class="text-xs text-gray-400 flex items-center gap-1 px-3">
                                    <i class="fas fa-map-marker-alt text-xs"></i>
                                    <?= $locationText ?>
                                </p>
                                <div class="flex gap-2 mt-1 px-3">
                                    <?php
                                    // Display up to 2 amenities as tags
                                    $amenity_count = 0;
                                    foreach ($amenities as $amenity) {
                                        $amenity = trim($amenity);
                                        if (!empty($amenity) && $amenity_count < 2) {
                                            echo '<span class="text-xs bg-gray-200 rounded px-2 py-[2px] text-gray-600">' . htmlspecialchars($amenity) . '</span>';
                                            $amenity_count++;
                                        }
                                    }
                                    ?>
                                </div>
                                <div class="flex items-center justify-between mt-4 text-xs font-semibold text-gray-800 px-3">
                                    <span>₱<?= $pricePerHour ?></span>
                                    <div class="flex items-center gap-4">
                                        <span class="flex items-center gap-1">
                                            <i class="fas fa-users"></i>
                                            <?= $numPersons ?>
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <i class="fas fa-chair"></i>
                                            <?= $numPersons ?> </span>
                                    </div>
                                </div>
                                <hr class="my-3 border-gray-300 mx-3"/>
                                <p class="text-xs text-gray-500 leading-tight px-3 mb-4">
                                    <?= $description ?>
                                </p>
                                <!-- Buttons for View Details and Get Directions - Now correctly placed inside the venue card -->
                                <div class="px-3 pb-3 flex space-x-2 mt-auto">
                                    <a href="venue_display.php?id=<?= htmlspecialchars($venue['id']) ?>" class="block flex-1 text-center px-2 py-2 bg-gray-700 text-white text-sm font-medium rounded-md hover:bg-gray-800 transition duration-200">
                                        View Details
                                    </a>
                                    <a href="<?= $directionsUrl ?>" target="_blank" class="block flex-1 text-center px-2 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition duration-200">
                                        <i class="fas fa-directions mr-1"></i> Get Directions
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-gray-500 p-8 bg-white rounded-lg shadow-lg mx-auto max-w-lg border border-gray-200 col-span-full">
                            <p class="text-lg font-medium mb-2">No venues found at the moment.</p>
                            <p class="text-sm">Please check back later or try a different search.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            </div>
    </main>

     <main class="max-w-7xl mx-auto px-6 py-16 md:py-20">
        <div class="flex flex-col md:flex-row w-full min-h-[300px]">
           <div class="md:w-1/2 w-full">
            <img alt="Hand holding a tablet displaying a webpage with settings and options" class="w-full h-full object-cover" height="400" src="/ventech_locator/images/section.jpg" width="600"/>
           </div>
           <div class="md:w-1/2 w-full bg-[#263238] p-8 flex flex-col justify-center">
            <h2 class="text-[#f9d949] text-lg font-normal mb-6">
             Why VenTech?
            </h2>
            <ul class="space-y-3 text-white font-light max-w-md">
             <li class="flex items-start">
              <i class="fas fa-check-square mt-[3px] text-2xl">
              </i>
              <span class="text-lg md:text-xl">
               Fast & Easy Venue Search.
              </span>
             </li>
             <li class="flex items-start">
              <i class="fas fa-check-square mt-[3px] text-2xl">
              </i>
              <span class="text-lg md:text-xl">
               Synchronise the availability with other systems/apps
              </span>
             </li>
             <li class="flex items-start">
              <i class="fas fa-check-square mt-[3px] text-2xl">
              </i>
              <span class="text-lg md:text-xl">
               See Venue Info in One Place, Photos, amenities, location, and availability – all in one click.
              </span>
             </li>
             <li class="flex items-start">
              <i class="fas fa-check-square mt-[3px] text-2xl">
              </i>
              <span class="text-lg md:text-xl">
               Owner can manage the bookings
              </span>
             </li>
             <li class="flex items-start">
              <i class="fas fa-check-square mt-[3px] text-2xl">
              </i>
              <span class="text-lg md:text-xl">
               Check Dates Instantly - View when a venue is available and reserve right away.
              </span>
             </li>
             <li class="flex items-start">
              <i class="fas fa-check-square mt-[3px] text-2xl">
              </i>
              <span class="text-lg md:text-xl">
               No more walking around campus — just search online!
              </span>
             </li>
             <li class="flex items-start">
              <i class="fas fa-check-square mt-[3px] text-2xl">
              </i>
              <span class="text-lg md:text-xl">
               Save the best spots to check out later.
              </span>
             </li>
            </ul>
           </div>
          </div>
    </main>


    <footer class="bg-gray-900 text-gray-400 text-center p-8 mt-12 rounded-t-lg shadow-inner">
        <p>© <?= date('Y') ?> Ventech Locator. All Rights Reserved.</p>
    </footer>

    <!-- User Login Modal -->
    <div id="userLoginModal" class="modal-overlay hidden">
        <div class="modal-content">
            <button onclick="closeUserLoginModal()" class="absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl font-bold z-50">
                ×
            </button>
            <iframe id="userLoginIframe" src="" class="modal-iframe" title="User Login Form"></iframe>
        </div>
    </div>

    <!-- User Signup Modal -->
    <div id="userSignupModal" class="modal-overlay hidden">
        <div class="modal-content">
            <button onclick="closeUserSignupModal()" class="absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl font-bold z-50">
                ×
            </button>
            <iframe id="userSignupIframe" src="" class="modal-iframe" title="User Signup Form"></iframe>
        </div>
    </div>

    <div id="clientLoginModal" class="modal-overlay hidden">
        <div class="modal-content">
            <button onclick="closeClientLoginModal()" class="absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl font-bold z-50">
                ×
            </button>
            <iframe id="clientLoginIframe" src="" class="modal-iframe" title="Client Login Form"></iframe>
        </div>
    </div>

    <div id="clientSignupModal" class="modal-overlay hidden">
        <div class="modal-content">
            <button onclick="closeClientSignupModal()" class="absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl font-bold z-50">
                ×
            </button>
            <iframe id="clientSignupIframe" src="" class="modal-iframe" title="Client Signup Form"></iframe>
        </div>
    </div>

    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
    <script>

         const  signInButton = document.getElementById('signInButton');
         const  dropdownMenu = document.getElementById('dropdownMenu');
        // Ensure these elements exist before adding listeners
        if (signInButton && dropdownMenu) {
            signInButton.addEventListener('click',  function  ( event ) {
                event.stopPropagation(); // Prevent click from closing immediately
                dropdownMenu.classList.toggle('hidden');
                // Optional: Add ARIA attributes for accessibility
                 const  isExpanded = dropdownMenu.classList.contains('hidden');
                signInButton.setAttribute('aria-expanded', !isExpanded);
            });

            // Close dropdown when clicking outside
            document.addEventListener('click',  function  ( event ) {
                if (!dropdownMenu.classList.contains('hidden') && !signInButton.contains(event.target) && !dropdownMenu.contains(event.target)) {
                    dropdownMenu.classList.add('hidden');
                    signInButton.setAttribute('aria-expanded', false);
                }
            });

            // Close dropdown on Escape key
            document.addEventListener('keydown',  function ( event ) {
                if (event.key === 'Escape' && !dropdownMenu.classList.contains('hidden')) {
                    dropdownMenu.classList.add('hidden');
                    signInButton.setAttribute('aria-expanded', false);
                    signInButton.focus(); // Return focus to the button
                }
            });

             // Optional: Handle keyboard navigation within the dropdown
             dropdownMenu.addEventListener('keydown',  function ( event ) {
                  const  focusableElements = dropdownMenu.querySelectorAll('a');
                 if (focusableElements.length === 0) return;

                  const  firstElement = focusableElements[0];
                  const  lastElement = focusableElements[focusableElements.length - 1];
                  const  activeElement = document.activeElement;

                 if (event.key === 'ArrowDown') {
                     event.preventDefault();
                     if (activeElement === lastElement || activeElement === dropdownMenu) {
                         firstElement.focus();
                     } else {
                          const  nextElement = Array.from(focusableElements).find(( el ,  index ,  arr )  =>  el === activeElement && arr[index + 1]);
                         if (nextElement) nextElement.focus();
                     }
                 } else if (event.key === 'ArrowUp') {
                      event.preventDefault();
                      if (activeElement === firstElement || activeElement === dropdownMenu) {
                          lastElement.focus();
                      } else {
                           const  prevElement = Array.from(focusableElements).find(( el ,  index ,  arr )  =>  el === activeElement && arr[index - 1]);
                          if (prevElement) prevElement.focus();
                      }
                 } else if (event.key === 'Home' || event.key === 'PageUp') {
                      event.preventDefault();
                      firstElement.focus();
                 } else if (event.key === 'End' || event.key === 'PageDown') {
                      event.preventDefault();
                      lastElement.focus();
                 }
             });
        }


        // JavaScript for user dropdown if logged in (assuming you have a similar structure for logged-in users)
        document.addEventListener('DOMContentLoaded',      function     () {
            // This part seems to be for a different dropdown, potentially for a logged-in user's profile menu.
            // Ensure you have elements with IDs 'userDropdownButton' and 'userDropdownMenu' if you intend to use this.
            // const userButton = document.getElementById('userDropdownButton'); // Assuming an ID for the button
            // const userDropdown = document.getElementById('userDropdownMenu'); // Assuming an ID for the dropdown content

            // if (userButton && userDropdown) {
            //     userButton.addEventListener('click', function (event) {
            //         event.stopPropagation();
            //         userDropdown.classList.toggle('hidden');
            //     });

            //     document.addEventListener('click', function (event) {
            //         if (!userDropdown.classList.contains('hidden') && !userButton.contains(event.target) && !userDropdown.contains(event.target)) {
            //             userDropdown.classList.add('hidden');
            //         }
            //     });
            // }


            // Initialize Swiper
             const  swiperElement = document.querySelector('.venue-slider');
            if (swiperElement) {
                 const  swiper = new Swiper(swiperElement, {
                    // Optional parameters
                    loop: false, // Set to true if you want infinite loop
                    slidesPerView: 1, // Slides visible on smallest screens
                    spaceBetween: 15, // Space between slides

                    // Responsive breakpoints
                    breakpoints: {
                        // when window width is >= 640px (sm)
                        640: {
                            slidesPerView: 2,
                            spaceBetween: 20
                        },
                        // when window width is >= 1024px (lg) - Adjusted to show 3 per row
                        1024: {
                            slidesPerView: 3, // Show 3 slides on screens >= 1024px
                            spaceBetween: 30
                        }
                    },

                    // Navigation arrows
                    navigation: {
                        nextEl: '.swiper-button-next',
                        prevEl: '.swiper-button-prev',
                    },

                    // Optional: Add pagination dots if desired
                    // pagination: {
                    //   el: '.swiper-pagination',
                    //   clickable: true,
                    // },
                    // Add A11y module
                    modules: [  Swiper  .prototype.modules.A11y],
                    a11y: {
                        prevSlideMessage: 'Previous slide',
                        nextSlideMessage: 'Next slide',
                        firstSlideMessage: 'This is the first slide',
                        lastSlideMessage: 'This is the last slide',
                        paginationBulletMessage: 'Go to slide {{index}}',
                        notificationClass: 'swiper-notification',
                        containerMessage: 'Carousel',
                        containerRoleDescriptionMessage: 'carousel',
                        itemRoleDescriptionMessage: 'slide',
                    },
                });
            }


            // Add fade-in animation on page load
            // Select all elements with the 'fade-in' class
              const   elementsToAnimate = document.querySelectorAll('.fade-in');

            // Add the animation class after a small delay to ensure elements are in the DOM
            setTimeout(()   =>   {
                elementsToAnimate.forEach(  element     =>   {
                    element.style.opacity = 1; // Set initial opacity to 1 (handled by animation 'forwards')
                    element.style.transform = 'translateY(0)'; // Set initial transform (handled by animation 'forwards')
                    // The animation property is already in the CSS, so adding the class triggers it
                    // element.classList.add('is-visible'); // If you were using a separate class to trigger
                });
            }, 100); // Small delay

            // Hamburger menu logic
            const hamburgerButton = document.getElementById('hamburgerButton');
            const mobileMenu = document.getElementById('mobileMenu');
            const closeMobileMenu = document.getElementById('closeMobileMenu');
            const mobileSignInButton = document.getElementById('mobileSignInButton');
            const mobileDropdownMenu = document.getElementById('mobileDropdownMenu');

            if (hamburgerButton && mobileMenu && closeMobileMenu) {
                hamburgerButton.addEventListener('click', function() {
                    mobileMenu.classList.remove('translate-x-full');
                });

                closeMobileMenu.addEventListener('click', function() {
                    mobileMenu.classList.add('translate-x-full');
                });

                // Close mobile menu when clicking outside
                document.addEventListener('click', function(event) {
                    if (!mobileMenu.classList.contains('translate-x-full') &&
                        !mobileMenu.contains(event.target) &&
                        !hamburgerButton.contains(event.target)) {
                        mobileMenu.classList.add('translate-x-full');
                    }
                });

                // Close mobile menu on Escape key
                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape' && !mobileMenu.classList.contains('translate-x-full')) {
                        mobileMenu.classList.add('translate-x-full');
                    }
                });
            }

            // Mobile Sign In dropdown logic
            if (mobileSignInButton && mobileDropdownMenu) {
                mobileSignInButton.addEventListener('click', function(event) {
                    event.stopPropagation();
                    mobileDropdownMenu.classList.toggle('hidden');
                    const isExpanded = !mobileDropdownMenu.classList.contains('hidden');
                    mobileSignInButton.setAttribute('aria-expanded', isExpanded);
                });

                // Close mobile dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (!mobileDropdownMenu.classList.contains('hidden') &&
                        !mobileSignInButton.contains(event.target) &&
                        !mobileDropdownMenu.contains(event.target)) {
                        mobileDropdownMenu.classList.add('hidden');
                        mobileSignInButton.setAttribute('aria-expanded', false);
                    }
                });

                // Close mobile dropdown on Escape key
                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape' && !mobileDropdownMenu.classList.contains('hidden')) {
                        mobileDropdownMenu.classList.add('hidden');
                        mobileSignInButton.setAttribute('aria-expanded', false);
                        mobileSignInButton.focus();
                    }
                });
            }

            // --- "List Your Venue" button logic ---
            const listVenueButton = document.getElementById('listVenueButton');
            if (listVenueButton) {
                listVenueButton.addEventListener('click', function(event) {
                    event.preventDefault(); // Prevent default button action

                    const isLoggedIn = <?php echo json_encode($isLoggedIn); ?>;
                    const userRole = <?php echo json_encode($userRole); ?>;

                    if (!isLoggedIn || (userRole !== 'client' && userRole !== 'admin' && userRole !== 'owner')) { // Added 'owner' role check
                        // Display custom alert message
                        const messageBox = document.createElement('div');
                        messageBox.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50';
                        messageBox.innerHTML = `
                            <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm w-full text-center">
                                <p class="text-lg font-semibold mb-4">You must be registered as a client, owner, or admin to list a venue.</p>
                                <div class="flex justify-center space-x-4">
                                    <button type="button" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600" onclick="openClientLoginModal()">Login as Client/Owner</button>
                                    <button type="button" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600" onclick="openClientSignupModal()">Register as Client/Owner</button>
                                    <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400" onclick="this.closest('.fixed').remove()">Cancel</button>
                                </div>
                            </div>
                        `;
                        document.body.appendChild(messageBox);
                    } else {
                        // If logged in as client/admin/owner, redirect to add venue page
                        // IMPORTANT: Adjust this path to your actual add venue page
                        window.location.href = '/ventech_locator/client/client_dashboard.php'; // Assuming client_dashboard.php is the entry for owners
                    }
                });
            }

            // --- Modal Functions ---
            const userLoginModal = document.getElementById('userLoginModal');
            const userLoginIframe = document.getElementById('userLoginIframe');
            const userSignupModal = document.getElementById('userSignupModal');
            const userSignupIframe = document.getElementById('userSignupIframe');
            const clientLoginModal = document.getElementById('clientLoginModal');
            const clientLoginIframe = document.getElementById('clientLoginIframe');
            const clientSignupModal = document.getElementById('clientSignupModal');
            const clientSignupIframe = document.getElementById('clientSignupIframe');

            // Function to open the user login modal
            window.openUserLoginModal = function() {
                userLoginIframe.src = '/ventech_locator/users/user_login.php';
                userLoginModal.classList.add('visible');
                userLoginModal.classList.remove('hidden');
                // Hide any other open dropdowns or modals
                if (!dropdownMenu.classList.contains('hidden')) {
                    dropdownMenu.classList.add('hidden');
                    signInButton.setAttribute('aria-expanded', false);
                }
                if (!mobileDropdownMenu.classList.contains('hidden')) {
                    mobileDropdownMenu.classList.add('hidden');
                    mobileSignInButton.setAttribute('aria-expanded', false);
                }
                closeClientLoginModal(); // Close client login if open
                closeClientSignupModal(); // Close client signup if open
                closeUserSignupModal(); // Close user signup if open
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
                // Hide any other open dropdowns or modals
                if (!dropdownMenu.classList.contains('hidden')) {
                    dropdownMenu.classList.add('hidden');
                    signInButton.setAttribute('aria-expanded', false);
                }
                if (!mobileDropdownMenu.classList.contains('hidden')) {
                    mobileDropdownMenu.classList.add('hidden');
                    mobileSignInButton.setAttribute('aria-expanded', false);
                }
                closeUserLoginModal(); // Close user login if open
                closeClientLoginModal(); // Close client login if open
                closeClientSignupModal(); // Close client signup if open
            };

            // Function to close the user signup modal
            window.closeUserSignupModal = function() {
                userSignupModal.classList.remove('visible');
                userSignupModal.classList.add('hidden');
                userSignupIframe.src = ''; // Clear iframe content
            };

            // Function to open the client login modal
            window.openClientLoginModal = function() {
                clientLoginIframe.src = '/ventech_locator/client/client_login.php';
                clientLoginModal.classList.add('visible');
                clientLoginModal.classList.remove('hidden');
                // Hide the dropdown menu if it's open
                if (!dropdownMenu.classList.contains('hidden')) {
                    dropdownMenu.classList.add('hidden');
                    signInButton.setAttribute('aria-expanded', false);
                }
                if (!mobileDropdownMenu.classList.contains('hidden')) {
                    mobileDropdownMenu.classList.add('hidden');
                    mobileSignInButton.setAttribute('aria-expanded', false);
                }
                // Ensure other modals are closed if open
                closeUserLoginModal(); // Close user login if open
                closeUserSignupModal(); // Close user signup if open
                closeClientSignupModal(); // Close client signup if open
            };

            // Function to close the client login modal
            window.closeClientLoginModal = function() {
                clientLoginModal.classList.remove('visible');
                clientLoginModal.classList.add('hidden');
                clientLoginIframe.src = ''; // Clear iframe content
            };

            // Function to open the client signup modal
            window.openClientSignupModal = function() {
                clientSignupIframe.src = '/ventech_locator/client/client_signup.php';
                clientSignupModal.classList.add('visible');
                clientSignupModal.classList.remove('hidden');
                // Ensure other modals are closed if open
                closeUserLoginModal(); // Close user login if open
                closeUserSignupModal(); // Close user signup if open
                closeClientLoginModal(); // Close client login if open
            };

            // Function to close the client signup modal
            window.closeClientSignupModal = function() {
                clientSignupModal.classList.remove('visible');
                clientSignupModal.classList.add('hidden');
                clientSignupIframe.src = ''; // Clear iframe content
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

            // Close client login modal when clicking outside the content
            clientLoginModal.addEventListener('click', function(event) {
                if (event.target === clientLoginModal) {
                    closeClientLoginModal();
                }
            });

            // Close client login modal on Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && clientLoginModal.classList.contains('visible')) {
                    closeClientLoginModal();
                }
            });

            // Close signup modal when clicking outside the content
            clientSignupModal.addEventListener('click', function(event) {
                if (event.target === clientSignupModal) {
                    closeClientSignupModal();
                }
            });

            // Close signup modal on Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && clientSignupModal.classList.contains('visible')) {
                    closeClientSignupModal();
                }
            });

        });


    </script>
</body>
</html>
