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
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // Good practice
];

// **3. Initialize User Session Variables**
$isLoggedIn = false;
$username = '';
$userRole = '';
$dashboardLink = '#'; // Default link
$logoutLink = '#'; // Default link

// **4. Establish PDO Connection and Fetch Data**
try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // **5. Check Session and Fetch User Data if Logged In**
    if (isset($_SESSION['user_id'])) {
        $loggedInUserId = $_SESSION['user_id'];
        // Prepare statement to fetch username and role
        $stmt_user = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
        $stmt_user->execute([$loggedInUserId]);
        $userData = $stmt_user->fetch();

        if ($userData) {
            $isLoggedIn = true;
            $username = $userData['username'];
            $userRole = strtolower($userData['role'] ?? 'user'); // Default to 'user' if role is null/missing

            // Determine dashboard and logout links based on role (ADJUST PATHS AS NEEDED)
            // Example: If 'client' is your venue owner role
            if ($userRole === 'client' || $userRole === 'admin' || $userRole === 'owner') {
                 $dashboardLink = '/ventech_locator/client/client_dashboard.php';
                 $logoutLink = '/ventech_locator/client/client_logout.php'; // Assuming separate logout for clients
            } else { // Default to user dashboard/logout
                 $dashboardLink = '/ventech_locator/users/user_dashboard.php'; // Assuming user dashboard exists
                 $logoutLink = '/ventech_locator/users/user_logout.php'; // Assuming separate logout for users
            }
            // If you have a single unified logout script:
            // $logoutLink = '/ventech_locator/logout.php';

        } else {
            // User ID in session doesn't exist in DB - clear invalid session
            error_log("Invalid user ID found in session on index.php: " . $loggedInUserId);
            session_unset();
            session_destroy();
             // No need to redirect here, just proceed as logged out
        }
    } // End session check

    // **6. Fetch Venues** (Fetch regardless of login status)
    $stmt_venues = $pdo->prepare("SELECT * FROM venue WHERE status IN ('open', 'closed') ORDER BY created_at DESC LIMIT 9"); // Limit displayed venues?
    $stmt_venues->execute();
    $venues = $stmt_venues->fetchAll();

} catch (PDOException $e) {
    error_log("Database error on index.php: " . $e->getMessage());
    // Display a user-friendly error message but don't reveal details
    echo "<div style='color:red; padding:10px; border:1px solid red; background-color:#ffe0e0; margin:10px;'>";
    echo "Sorry, we encountered a problem loading the page content. Please try again later.";
    echo "</div>";
    // You might want to die() here or just let the rest of the page render without DB data
    $venues = []; // Ensure $venues is an empty array if DB fails
    // die(); // Uncomment if you want to stop execution on DB error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Ventech Locator - Find Your Perfect Venue</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" rel="stylesheet"/> <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet"/>
    <style>
        /* Optional: Add minor custom styles if needed */
        .hero-overlay-content {
            max-width: 1200px; /* Limit width of content over hero */
            width: 100%;
        }
    </style>
</head>
<body class="font-roboto bg-gray-100"> <header class="relative">
    <img src="https://storage.googleapis.com/a1aa/image/K_EDQPTQu2U4iz3tp8MzfHSgPnSlvbtIy4OxGn8rihQ.jpg" alt="Modern Venue Space" class="w-full h-96 object-cover brightness-75" /> <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/50 to-transparent flex flex-col justify-between items-center text-center text-white p-4 md:p-8">

        <div class="w-full flex justify-between items-center hero-overlay-content">
             <a href="index.php"> <img src="https://storage.googleapis.com/a1aa/image/hnN7Al3bjnkfO3IsgYjIgHrULVNdWiF-JXUCBNDe-sw.jpg" class="h-10 md:h-12" alt="Ventech Locator Logo" />
            </a>

            <nav class="space-x-4 md:space-x-6 lg:space-x-8 flex items-center text-sm md:text-base">
                <a class="hover:text-yellow-400 transition duration-150" href="index.php">HOME</a>
                <a class="hover:text-yellow-400 transition duration-150" href="user_venue_list.php">VENUE LIST</a>
                <?php if ($isLoggedIn): ?>
                    <span class="hidden lg:inline text-gray-200">|</span>
                    <span class="hidden sm:inline">Hi, <?= htmlspecialchars($username); ?>!</span>
                    <a href="<?= htmlspecialchars($dashboardLink); ?>" class="hover:text-yellow-400 transition duration-150" title="Go to your dashboard">Dashboard</a>
                    <a href="<?= htmlspecialchars($logoutLink); ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded-md text-xs md:text-sm font-medium transition shadow">Logout</a>
                <?php else: ?>
                    <div class="relative">
                        <button class="hover:text-yellow-400 transition duration-150 focus:outline-none font-medium" id="signInButton">
                           <i class="fas fa-sign-in-alt mr-1"></i> SIGN IN
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 hidden z-30" id="dropdownMenu"> <a href="/ventech_locator/users/user_login.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-yellow-100 hover:text-gray-900">User Login</a>
                            <a href="/ventech_locator/client/client_login.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-yellow-100 hover:text-gray-900">Client/Owner Login</a>
                            <a href="/ventech_locator/client/client_login.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-yellow-100 hover:text-gray-900">Admin</a>
                            </div>
                    </div>
                <?php endif; ?>
            </nav>
        </div>

        <div class="hero-overlay-content flex flex-col items-center justify-center flex-grow pb-12 md:pb-16">
            <h1 class="text-2xl md:text-3xl uppercase tracking-wider">Welcome to</h1>
            <h2 class="text-4xl md:text-5xl lg:text-6xl text-yellow-500 font-bold my-2 md:my-3">Ventech Locator</h2>
            <p class="text-base md:text-lg text-gray-200 mb-4 md:mb-6 max-w-xl">Discover and book the perfect venue for your next event.</p>
            <a href="user_venue_list.php" class="mt-2 px-6 py-2 bg-yellow-500 hover:bg-yellow-600 text-white font-bold rounded-md shadow-md transition transform hover:scale-105">
                Explore Venues
            </a>
        </div>

         <div></div>

    </div></header>


<main class="container mx-auto py-12 md:py-16 px-4 md:px-8">
    <h2 class="text-2xl md:text-3xl font-bold text-center text-gray-800 mb-8 md:mb-12">Featured Venues</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
        <?php if (!empty($venues)): ?>
            <?php foreach ($venues as $venue):
                // Construct the correct image path (same logic as before)
                $imagePathFromDB = $venue['image_path'] ?? null;
                // IMPORTANT: Adjust this base URL if your uploads folder location changes relative to the web root!
                $uploadsBaseUrl = '/ventech_locator/uploads/';
                $placeholderImg = 'https://via.placeholder.com/400x250/e2e8f0/64748b?text=No+Image'; // Placeholder color adjusted
                $imgSrc = $placeholderImg;

                if (!empty($imagePathFromDB)) {
                    // Check if it's already a full URL (less common but possible)
                    if (filter_var($imagePathFromDB, FILTER_VALIDATE_URL)) {
                         $imgSrc = htmlspecialchars($imagePathFromDB);
                    } else {
                        // Assume relative path and construct full URL
                        $imgSrc = $uploadsBaseUrl . ltrim(htmlspecialchars($imagePathFromDB), '/');
                        // Basic check if file exists (optional, uncomment if needed, adjust path)
                        // $filesystemPath = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $uploadsBaseUrl . ltrim($imagePathFromDB, '/');
                        // if (!file_exists($filesystemPath)) { $imgSrc = $placeholderImg; }
                    }
                }
            ?>
            <div class="border rounded-lg shadow-md overflow-hidden bg-white flex flex-col transition duration-300 hover:shadow-xl">
                <a href="venue_display.php?id=<?= $venue['id'] ?>" class="block">
                    <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($venue['title']) ?>" class="w-full h-48 object-cover" loading="lazy" />
                </a>
                <div class="p-4 flex flex-col flex-grow">
                     <p class="text-xs text-gray-500 mb-1">
                        Status: <span class="font-medium <?= $venue['status'] === 'open' ? 'text-green-600' : 'text-red-600' ?>">
                            <?= ucfirst(htmlspecialchars($venue['status'])) ?>
                        </span>
                    </p>
                    <h3 class="text-lg font-semibold text-gray-800 hover:text-orange-600 mb-2">
                         <a href="venue_display.php?id=<?= $venue['id'] ?>"><?= htmlspecialchars($venue['title']) ?></a>
                     </h3>

                    <p class="text-sm text-gray-600 mb-1">Starting from</p>
                    <p class="text-xl font-bold text-gray-900 mb-3">₱ <?= number_format($venue['price'] ?? 0, 2) ?> <span class="text-xs font-normal">/ Hour</span></p>

                    <div class="flex items-center text-sm text-gray-500 mb-4">
                        <div class="flex text-yellow-400">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i><i class="far fa-star"></i>
                        </div>
                        <span class="ml-2">(<?= htmlspecialchars($venue['reviews'] ?? 0) ?> Reviews)</span>
                    </div>

                    <div class="mt-auto pt-3 border-t border-gray-200 flex space-x-3">
                        <a href="venue_display.php?id=<?= $venue['id'] ?>" class="flex-1 text-center px-3 py-2 bg-orange-500 text-white text-xs font-bold rounded hover:bg-orange-600 transition shadow-sm">
                           <i class="fas fa-info-circle mr-1"></i> DETAILS
                        </a>
                        <?php if ($venue['status'] === 'open'): // Only show reservation button if venue is open ?>
                        <a href="/ventech_locator/venue_reservation.php?venue_id=<?= $venue['id'] ?>&venue_name=<?= urlencode(htmlspecialchars($venue['title'])) ?>" class="flex-1 text-center px-3 py-2 bg-indigo-600 text-white text-xs font-bold rounded hover:bg-indigo-700 transition shadow-sm">
                           <i class="fas fa-calendar-check mr-1"></i> RESERVE NOW
                        </a>
                        <?php else: ?>
                         <span class="flex-1 text-center px-3 py-2 bg-gray-400 text-white text-xs font-bold rounded cursor-not-allowed" title="Venue is currently closed for reservations">
                            <i class="fas fa-calendar-times mr-1"></i> CLOSED
                         </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="col-span-full text-center text-gray-500">No venues found matching the criteria.</p>
        <?php endif; ?>
    </div>

    <div class="text-center mt-12">
        <a href="user_venue_list.php" class="inline-block px-8 py-3 bg-gray-700 hover:bg-gray-800 text-white font-semibold rounded-md shadow-md transition">
            View All Venues &rarr;
        </a>
    </div>
</main>

<footer class="bg-gray-800 text-gray-300 text-center p-6 mt-12">
    <p>&copy; <?= date('Y') ?> Ventech Locator. All Rights Reserved.</p>
    </footer>

<script>
    const signInButton = document.getElementById('signInButton');
    const dropdownMenu = document.getElementById('dropdownMenu');

    if (signInButton && dropdownMenu) {
        signInButton.addEventListener('click', function (event) {
            event.stopPropagation(); // Prevent click from immediately closing menu
            dropdownMenu.classList.toggle('hidden');
        });

        // Close dropdown if clicking outside
        document.addEventListener('click', function (event) {
            if (!dropdownMenu.classList.contains('hidden') && !signInButton.contains(event.target)) {
                dropdownMenu.classList.add('hidden');
            }
        });
    }
</script>
</body>
</html>