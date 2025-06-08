<?php
// user_favorites.php

// **1. Start Session**
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// **2. Include Database Connection**
require_once '../includes/db_connection.php'; // Adjust path as needed

// **3. Check User Authentication**
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php"); // Redirect to login page if not logged in
    exit;
}
$user_id = $_SESSION['user_id'];

// **4. Check if PDO connection is available**
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("PDO connection not available in user_favorites.php");
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Error</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-gray-100 flex items-center justify-center h-screen"><div class="text-center p-8 bg-white rounded-lg shadow-md"><h1 class="text-2xl font-bold text-red-600 mb-4">System Error</h1><p class="text-gray-700">Sorry, we\'re experiencing technical difficulties with the database. Please try again later.</p></div></body></html>';
    exit;
}

// **5. Fetch Logged-in User Details**
$user = null;
try {
    $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("Invalid user_id {$user_id} in session (user_favorites).");
        session_unset();
        session_destroy();
        header("Location: user_login.php?error=invalid_session");
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching user details for user ID {$user_id} in user_favorites: " . $e->getMessage());
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Error</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-gray-100 flex items-center justify-center h-screen"><div class="text-center p-8 bg-white rounded-lg shadow-md"><h1 class="text-2xl font-bold text-red-600 mb-4">Error Loading User Data</h1><p class="text-gray-700">There was an error loading your user information. Please try again later.</p></div></body></html>';
    exit;
}

// **6. Fetch User's Favorited Venues**
$favoritedVenues = [];
try {
    $stmtFavorites = $pdo->prepare("
        SELECT v.id, v.title, v.description, v.image_path, v.price, v.amenities, v.reviews, v.num_persons, v.status
        FROM user_favorites uf
        JOIN venue v ON uf.venue_id = v.id
        WHERE uf.user_id = ?
        ORDER BY uf.favorited_at DESC
    ");
    $stmtFavorites->execute([$user_id]);
    $favoritedVenues = $stmtFavorites->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching favorited venues for user ID {$user_id} in user_favorites: " . $e->getMessage());
    // $favoritedVenues will remain empty, and an appropriate message will be displayed
}

// **7. Fetch Unread Notification Count (for navigation badge)**
$unread_notification_count = 0;
try {
    $stmtNotifyCount = $pdo->prepare("SELECT COUNT(*) FROM user_notifications WHERE user_id = :user_id AND is_read = FALSE");
    $stmtNotifyCount->execute([':user_id' => $user_id]);
    $unread_notification_count = $stmtNotifyCount->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching unread notification count for user $user_id in user_favorites: " . $e->getMessage());
    $unread_notification_count = 0;
}

// Helper function to escape HTML
function htmlspecialchars_decode_safe($str) {
    return htmlspecialchars_decode(html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES);
}

// Base URL for uploads
$uploadsBaseUrl = '/ventech_locator/uploads/'; // ADJUST PATH IF NEEDED!
$placeholderImg = 'https://via.placeholder.co/400x250/e5e7eb/4b5563?text=No+Image'; // Placeholder with gray background

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1" name="viewport"/>
    <title>My Favorites - Wedding Spot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&amp;family=Open+Sans&amp;display=swap" rel="stylesheet"/>
    <style>
        body {
            font-family: "Open Sans", sans-serif;
            background-color: #f3f4f6; /* Light gray background */
            color: #1f2937; /* Default text color */
        }
        h2 {
            font-family: 'Montserrat', sans-serif;
        }

        /* Custom styles for notification badge */
        .notification-icon-container {
             position : relative;
             display : inline-block;
             margin-right : 1.5rem;
        }
        .notification-badge {
             position : absolute;
             top : -8px;
             right : -8px;
             background-color : #ef4444;
             color : white;
             border-radius : 9999px;
             padding : 0.1rem 0.4rem;
             font-size : 0.75rem;
             font-weight : bold;
             min-width : 1.25rem;
             text-align : center;
             line-height : 1;
        }

        /* Venue Card Styling (for the list) - Reused from client_map.php */
        .venue-card-list {
            background-color: #ffffff;
            border-radius: 0.5rem; /* rounded-lg */
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06); /* shadow-md */
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            border: 1px solid #e5e7eb; /* subtle border */
        }
        .venue-card-list:hover {
             transform: translateY(-2px); /* Slight lift effect */
             box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.08);
        }
        .venue-card-list img {
            width: 100%;
            height: 180px; /* Fixed image height */
            object-fit: cover;
        }
        .venue-card-list .card-content {
            padding: 1rem; /* p-4 */
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .venue-card-list h3 {
            font-size: 1.125rem; /* text-lg */
            font-weight: 600; /* font-semibold */
            color: #1f2937; /* gray-900 */
            margin-bottom: 0.25rem; /* mb-1 - Adjusted spacing */
        }
         .venue-card-list h3 a {
             text-decoration: none;
             color: inherit;
             transition: color 0.2s ease-in-out;
         }
         .venue-card-list h3 a:hover {
             color: #f97316; /* orange-500 */
         }
        .venue-card-list .text-sm { font-size: 0.875rem; }
        .venue-card-list .text-xs { font-size: 0.75rem; }
        .venue-card-list .font-semibold { font-weight: 600; }
        .venue-card-list .font-bold { font-weight: 700; }
        .venue-card-list .text-gray-800 { color: #1f2937; }
        .venue-card-list .text-gray-600 { color: #4b5563; }
        .venue-card-list .text-gray-500 { color: #6b7280; }
        .venue-card-list .text-green-600 { color: #22c55e; }
        .venue-card-list .text-red-600 { color: #ef4444; }
        .venue-card-list .text-yellow-400 { color: #facc15; }
        .venue-card-list .mb-1 { margin-bottom: 0.25rem; }
        .venue-card-list .mb-2 { margin-bottom: 0.5rem; }
        .venue-card-list .mb-3 { margin-bottom: 0.75rem; }
        .venue-card-list .mb-4 { margin-bottom: 1rem; }
        .venue-card-list .flex { display: flex; }
        .venue-card-list .items-center { align-items: center; }
        .venue-card-list .space-x-3 > :not([hidden]) ~ :not([hidden]) { margin-left: 0.75rem; } /* space-x-3 */
        .venue-card-list .mr-1 { margin-right: 0.25rem; }
        .venue-card-list .ml-2 { margin-left: 0.5rem; }
        .venue-card-list .border-t { border-top: 1px solid #e5e7eb; }
        .venue-card-list .pt-3 { padding-top: 0.75rem; }
        .venue-card-list .rounded { border-radius: 0.25rem; }
        .venue-card-list .hover\:text-orange-600:hover { color: #f97316; }
        .venue-card-list .hover\:bg-orange-600:hover { background-color: #f97316; }
        .venue-card-list .hover\:bg-indigo-700:hover { background-color: #4338ca; }
        .venue-card-list .transition { transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter, backdrop-filter; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 0.15s; }
        .venue-card-list .shadow-sm { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
        .venue-card-list .w-full { width: 100%; }
        .venue-card-list .object-cover { object-fit: cover; }
        .venue-card-list .flex-grow { flex-grow: 1; }
        .venue-card-list .flex-col { flex-direction: column; }
        .venue-card-list .mt-auto { margin-top: auto; }
        .venue-card-list .text-center { text-align: center; }
        .venue-card-list .px-3 { padding-left: 0.75rem; padding-right: 0.75rem; }
        .venue-card-list .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
        .venue-card-list .bg-orange-500 { background-color: #f97316; }
        .venue-card-list .bg-indigo-600 { background-color: #4f46e5; }
        .venue-card-list .bg-gray-400 { background-color: #9ca3af; }
        .venue-card-list .cursor-not-allowed { cursor: not-allowed; }

        /* Favorite Heart Icon Styling */
        .favorite-toggle {
            cursor: pointer;
            color: #ccc; /* Default grey for unfavorited */
            transition: color 0.2s ease-in-out, transform 0.1s ease-in-out;
        }
        .favorite-toggle.favorited {
            color: #ef4444; /* Red for favorited */
        }
        .favorite-toggle:hover {
            transform: scale(1.1);
        }

        /* Main content padding for fixed header */
        main {
            padding-top: 6rem; /* Adjust based on header height */
        }

        /* Grid for venue cards */
        .venue-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); /* Responsive grid */
            gap: 1.5rem; /* gap-6 */
            padding: 1.5rem; /* p-6 */
        }

        @media (max-width: 768px) {
            .venue-grid {
                grid-template-columns: 1fr; /* Single column on small screens */
                padding: 1rem; /* p-4 */
            }
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-900">
    <header class="flex items-center justify-between py-4 text-xs sm:text-sm fixed w-full top-0 left-0 right-0 z-30 bg-white shadow-md px-4">
        <div class="flex items-center space-x-2">
            <button id="mobile-menu-toggle" aria-label="Menu" class="text-gray-700 text-xl md:hidden">
                <i class="fas fa-bars"></i>
            </button>
            <img alt="Wedding Spot logo with text and a ring icon" class="w-[80px] h-[30px] object-contain" height="30" src="/ventech_locator/images/logo.png" width="80"/>
        </div>

        <nav class="hidden md:flex items-center space-x-4 text-xs sm:text-sm text-gray-900 font-normal">
            <ul class="flex items-center space-x-4">
                <li class="cursor-pointer hover:underline hover:text-[#8b1d52]">
                    <a href="user_dashboard.php">Home</a>
                </li>
                <li class="cursor-pointer hover:underline hover:text-[#8b1d52]">
                    <a href="user_reservation_manage.php">My Reservations</a>
                </li>
                <li class="cursor-pointer hover:underline hover:text-[#8b1d52]">
                    <a href="/ventech_locator/client_map.php">Find Venues</a>
                </li>
                <li class="cursor-pointer text-[#8b1d52] font-semibold"> <a href="user_favorites.php">Favorites</a>
                </li>
                <li class="cursor-pointer hover:underline hover:text-[#8b1d52]">
                    <a href="user_profile.php">Profile</a>
                </li>
            </ul>

            <ul class="flex items-center space-x-4 ml-6">
                <?php if ($user): ?>
                    <li class="relative group cursor-pointer">
                        <div class="notification-icon-container inline-block">
                            <a href="user_notification_list.php" class="text-gray-700 hover:text-[#8b1d52] transition-colors" title="View Notifications">
                                <i class="fas fa-bell text-xl"></i>
                            </a>
                            <?php if ($unread_notification_count > 0): ?>
                                <span id="notification-count-badge" class="notification-badge"><?= htmlspecialchars($unread_notification_count) ?></span>
                            <?php else: ?>
                                <span id="notification-count-badge" class="notification-badge" style="display: none;">0</span>
                            <?php endif; ?>
                        </div>
                    </li>
                    <li class="cursor-pointer">
                        <span class="hidden lg:inline text-gray-700">Welcome, <strong class="font-semibold text-[#8b1d52]"><?= htmlspecialchars($user['username'] ?? 'User') ?></strong>!</span>
                    </li>
                    <li class="cursor-pointer">
                        <a href="/ventech_locator/users/user_logout.php" class="bg-[#8b1d52] text-white hover:bg-[#6f153f] py-1.5 px-4 rounded-md text-sm font-medium transition duration-150 ease-in-out shadow-sm flex items-center">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li class="cursor-pointer hover:underline hover:text-[#8b1d52]">Sign In</li>
                    <li class="cursor-pointer">
                        <a href="/ventech_locator/client/client_signup.php" class="bg-[#8b1d52] text-white hover:bg-[#6f153f] py-1.5 px-4 rounded-md text-sm font-medium transition duration-150 ease-in-out shadow-sm">
                            <i class="fas fa-user-plus mr-1"></i> Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <div id="mobile-menu" class="md:hidden bg-white py-2 px-4 mt-[64px] fixed w-full z-20 shadow-md hidden">
        <ul class="flex flex-col space-y-2 text-gray-900 font-normal">
            <li><a href="user_dashboard.php" class="block py-2 px-3 hover:bg-gray-100 rounded">Dashboard</a></li>
            <li><a href="user_reservation_manage.php" class="block py-2 px-3 hover:bg-gray-100 rounded">My Reservations</a></li>
            <li><a href="/ventech_locator/client_map.php" class="block py-2 px-3 hover:bg-gray-100 rounded">Find Venues</a></li>
            <li><a href="user_favorites.php" class="block py-2 px-3 hover:bg-gray-100 rounded text-[#8b1d52] font-semibold">Favorites</a></li>
            <li><a href="user_profile.php" class="block py-2 px-3 hover:bg-gray-100 rounded">Profile</a></li>
            <li class="border-t border-gray-200 pt-2 mt-2">
                <a href="user_notification_list.php" class="block py-2 px-3 hover:bg-gray-100 rounded flex items-center">
                    Notifications
                    <?php if ($unread_notification_count > 0): ?>
                        <span class="ml-auto bg-[#ef4444] text-white text-xs font-bold px-2 py-1 rounded-full"><?= htmlspecialchars($unread_notification_count) ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="/ventech_locator/users/user_logout.php" class="block py-2 px-3 hover:bg-red-50 rounded flex items-center text-red-600">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-4">My Favorite Venues</h1>

        <?php if (!empty($favoritedVenues)): ?>
            <div class="venue-grid">
                <?php foreach ($favoritedVenues as $venue):
                    $imgSrc = $placeholderImg;
                    if ($venue['image_path']) {
                        $imgSrc = $uploadsBaseUrl . htmlspecialchars_decode_safe($venue['image_path']);
                    }
                    $statusBadgeClass = $venue['status'] === 'open' ? 'text-green-600' : 'text-red-600';
                    $priceFormatted = number_format($venue['price'] ?? 0, 2);
                ?>
                    <div class="venue-card-list">
                        <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($venue['title'] ?? 'Venue Image') ?>" class="w-full h-48 object-cover" onerror="this.onerror=null;this.src='<?= htmlspecialchars($placeholderImg) ?>';">
                        <div class="card-content">
                            <div class="flex justify-between items-center mb-1">
                                <p class="text-xs text-gray-500">
                                    Status: <span class="font-medium <?= $statusBadgeClass ?>">
                                        <?= htmlspecialchars(ucfirst($venue['status'] ?? 'Unknown')) ?>
                                    </span>
                                </p>
                                <i class="favorite-toggle fas fa-heart text-xl cursor-pointer text-red-500"
                                   data-venue-id="<?= htmlspecialchars($venue['id']) ?>" data-is-favorited="true"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-1">
                                <a href="../venue_display.php?id=<?= htmlspecialchars($venue['id']) ?>"><?= htmlspecialchars($venue['title'] ?? 'N/A') ?></a>
                            </h3>
                            <p class="text-sm text-gray-600 mb-1">Starting from</p>
                            <p class="text-xl font-bold text-gray-900 mb-3">₱ <?= $priceFormatted ?> <span class="text-xs font-normal text-gray-600">/ Hour</span></p>
                            <?php if ($venue['num_persons']): ?>
                                <p class="text-sm text-gray-600 mb-1"><i class="fas fa-users mr-1 text-gray-400"></i> Capacity: <?= htmlspecialchars($venue['num_persons']) ?></p>
                            <?php endif; ?>
                            <?php if ($venue['amenities']): ?>
                                <p class="text-sm text-gray-600 mb-1"><i class="fas fa-tags mr-1 text-gray-400"></i> Amenities: <?= htmlspecialchars($venue['amenities']) ?></p>
                            <?php endif; ?>
                            <div class="flex items-center text-sm text-gray-500 mb-4">
                                <div class="flex text-yellow-400">
                                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i><i class="far fa-star"></i>
                                </div>
                                <span class="ml-2">(<?= htmlspecialchars($venue['reviews'] ?? 0) ?> Reviews)</span>
                            </div>
                            <div class="mt-auto pt-3 border-t border-gray-200 flex justify-center">
                                <a href="../venue_display.php?id=<?= htmlspecialchars($venue['id']) ?>" class="flex-1 text-center px-3 py-2 bg-orange-500 text-white text-xs font-bold rounded hover:bg-orange-600 transition shadow-sm max-w-xs">
                                    <i class="fas fa-info-circle mr-1"></i> VIEW DETAILS
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white p-6 rounded-lg shadow-md text-center text-gray-600">
                <p class="text-lg mb-4">You haven't favorited any venues yet.</p>
                <p class="text-sm">Explore venues and click the <i class="far fa-heart text-red-500"></i> icon to add them to your favorites!</p>
                <a href="/ventech_locator/client_map.php" class="mt-6 inline-block bg-[#8b1d52] text-white hover:bg-[#6f153f] py-2 px-4 rounded-md text-sm font-medium transition duration-150 ease-in-out shadow-sm">
                    <i class="fas fa-search mr-2"></i> Find Venues
                </a>
            </div>
        <?php endif; ?>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Mobile menu toggle logic
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const mobileMenu = document.getElementById('mobile-menu');

            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                });
            }

            /**
             * Displays a custom message box instead of alert().
             * @param {string} message The message to display.
             * @param {string} type 'success' or 'error' for styling.
             */
            function showMessageBox(message, type = 'success') {
                const messageBox = document.createElement('div');
                messageBox.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-[10000]'; // Higher z-index
                let bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
                let icon = type === 'success' ? '<i class="fas fa-check-circle text-green-700"></i>' : '<i class="fas fa-times-circle text-red-700"></i>';
                let textColor = type === 'success' ? 'text-green-700' : 'text-red-700';

                messageBox.innerHTML = `
                    <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm w-full text-center transform scale-95 opacity-0 transition-all duration-300 ease-out">
                        <div class="text-4xl mb-4 ${textColor}">${icon}</div>
                        <p class="text-lg font-semibold mb-4 text-gray-800">${message}</p>
                        <button type="button" class="px-4 py-2 ${bgColor} text-white rounded hover:opacity-90 transition" onclick="this.closest('.fixed').remove()">OK</button>
                    </div>
                `;
                document.body.appendChild(messageBox);

                // Animate in
                setTimeout(() => {
                    messageBox.querySelector('.transform').classList.remove('scale-95', 'opacity-0');
                }, 10); // Small delay to allow DOM render before transition

                // Optional: Auto-dismiss after a few seconds
                setTimeout(() => {
                    if (messageBox.parentNode) { // Check if it's still in DOM
                        messageBox.querySelector('.transform').classList.add('scale-95', 'opacity-0');
                        messageBox.addEventListener('transitionend', () => {
                            messageBox.remove();
                        }, { once: true });
                    }
                }, 3000); // Dismiss after 3 seconds
            }

            // Function to fetch unread notifications count
            function fetchNotificationCount() {
                const countEndpoint = 'get_unread_count.php'; // Ensure this path is correct

                fetch(countEndpoint)
                    .then(response => {
                        if (!response.ok) {
                            console.error('Error fetching notification count:', response.statusText);
                            document.getElementById('notification-count-badge').style.display = 'none';
                            return Promise.reject('Network response was not ok.');
                        }
                        return response.json();
                    })
                    .then(data => {
                        const badge = document.getElementById('notification-count-badge');
                        if (badge) {
                            const unreadCount = data.count || 0;

                            if (unreadCount > 0) {
                                badge.textContent = unreadCount;
                                badge.style.display = 'inline-block';
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('There was a problem fetching the notification count:', error);
                        const badge = document.getElementById('notification-count-badge');
                        if (badge) {
                            badge.style.display = 'none';
                        }
                    });
            }

            // Fetch notification count when the page loads
            fetchNotificationCount();

            // Periodically fetch notification count (e.g., every 30 seconds)
            const notificationCountCheckInterval = 30000;
            setInterval(fetchNotificationCount, notificationCountCheckInterval);

            // Logic for toggling favorite status
            async function toggleFavorite(venueId, isFavorited) {
                const loggedInUserId = <?php echo json_encode($user_id); ?>;
                if (!loggedInUserId) {
                    showMessageBox('Please log in to favorite venues.', 'error');
                    console.warn('User not logged in. Cannot favorite venue.');
                    return;
                }

                try {
                    const action = isFavorited ? 'remove' : 'add';
                    const response = await fetch('toggle_favorite.php', { // Endpoint for toggling favorite
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ venue_id: venueId, action: action })
                    });

                    const result = await response.json();

                    if (result.success) {
                        if (action === 'add') {
                            showMessageBox('Saved to your favorites!', 'success');
                        } else {
                            showMessageBox('Removed from favorites.', 'success');
                        }
                        console.log(result.message);
                        // Reload the page or re-render the list to reflect the change
                        // Reloading is simple, but re-rendering without full page load is more efficient
                        window.location.reload(); // Simple reload for now
                    } else {
                        console.error('Failed to toggle favorite:', result.message);
                        showMessageBox('Failed to update favorites: ' + result.message, 'error');
                    }
                } catch (error) {
                    console.error('Error toggling favorite:', error);
                    showMessageBox('An error occurred. Please try again.', 'error');
                }
            }

            // Event delegation for favorite toggle icons
            document.querySelectorAll('.favorite-toggle').forEach(icon => {
                icon.addEventListener('click', function() {
                    const venueId = this.dataset.venueId;
                    const isFavorited = this.dataset.isFavorited === 'true';
                    toggleFavorite(venueId, isFavorited);
                    // Optimistically update the icon (will be fully updated on reload)
                    this.classList.toggle('fa-solid');
                    this.classList.toggle('fa-regular');
                    this.classList.toggle('text-red-500');
                    this.classList.toggle('text-gray-400');
                    this.dataset.isFavorited = (!isFavorited).toString();
                });
            });
        });
    </script>

</body>
</html>
