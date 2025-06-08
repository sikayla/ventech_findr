<?php
// **1. Start Session**
session_start();

// **2. Database Connection Parameters** (Consider a separate config file for better organization)
$host = 'localhost';
$db   = 'ventech_db'; // Use your actual database name
$user_db = 'root';     // Use your actual database username
$pass = '';           // Use your actual database password
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
    error_log("Database Connection Error in reservation_details.php: " . $e->getMessage());
    die("Sorry, we're experiencing technical difficulties. Please try again later.");
}

// **4. Check User Authentication**
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php"); // Redirect to user login page
    exit;
}
$user_id = $_SESSION['user_id'];

// **5. Get Reservation ID from URL**
$reservation_id = $_GET['id'] ?? null;

if (!$reservation_id || !is_numeric($reservation_id)) {
    header("Location: reservations_list.php?error=invalid_reservation_id");
    exit;
}

// **6. Fetch Reservation Details**
$reservation = null;
try {
    $stmt = $pdo->prepare(
        "SELECT r.id, r.event_date, r.status, r.created_at,
                v.id as venue_id, v.title as venue_title
         FROM reservations r
         JOIN venue v ON r.venue_id = v.id
         WHERE r.id = ? AND r.user_id = ?" // Ensure the reservation belongs to the logged-in user
    );
    $stmt->execute([$reservation_id, $user_id]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        header("Location: reservations_list.php?error=reservation_not_found");
        exit;
    }

} catch (PDOException $e) {
    error_log("Error fetching reservation details for ID {$reservation_id}: " . $e->getMessage());
    die("Error loading reservation details. Please try again later.");
}

// --- Helper function for status badges (reused) ---
function getStatusBadgeClass($status) {
    $status = strtolower($status ?? 'unknown');
    switch ($status) {
        case 'confirmed': return 'bg-green-100 text-green-800';
        case 'cancelled': case 'rejected': return 'bg-red-100 text-red-800';
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        default: return 'bg-gray-100 text-gray-800'; // For other statuses
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Details - Ventech Locator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; }
        nav { position: sticky; top: 0; z-index: 10; }
        main { min-height: calc(100vh - 64px); }
    </style>
</head>
<body class="bg-gray-100">

    <nav class="bg-indigo-600 p-4 text-white shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <a href="/ventech_locator/index.php" class="text-xl font-bold hover:text-indigo-200">Ventech Locator</a>
            <div>
                <span class="mr-4 hidden sm:inline">Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>!</span>
                <a href="/ventech_locator/users/user_logout.php" class="bg-white text-indigo-600 hover:bg-gray-200 py-1 px-3 rounded text-sm font-medium transition duration-150 ease-in-out shadow">Logout</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto p-6 mt-8 bg-white shadow-md rounded-lg">
        <h1 class="text-2xl font-semibold text-gray-800 mb-4">Reservation Details</h1>

        <div class="mb-6">
            <a href="reservations_list.php" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <i class="fas fa-arrow-left mr-2"></i> Back to My Reservations
            </a>
        </div>

        <?php if ($reservation): ?>
            <div class="bg-gray-100 rounded-md p-6 border border-gray-200">
                <h2 class="text-xl font-semibold text-gray-700 mb-3">Reservation ID: <?= htmlspecialchars($reservation['id']) ?></h2>

                <div class="mb-3">
                    <strong class="text-gray-600">Venue:</strong>
                    <span class="text-indigo-700 font-medium"><?= htmlspecialchars($reservation['venue_title']) ?></span>
                    <a href="/ventech_locator/venue_display.php?id=<?= $reservation['venue_id'] ?>" class="text-indigo-500 hover:underline ml-2" target="_blank">View Venue</a>
                </div>

                <div class="mb-3">
                    <strong class="text-gray-600">Event Date:</strong>
                    <span><?= htmlspecialchars(date("D, M d, Y", strtotime($reservation['event_date']))) ?></span>
                </div>

                <div class="mb-3">
                    <strong class="text-gray-600">Status:</strong>
                    <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold <?= getStatusBadgeClass($reservation['status']) ?>">
                        <?= htmlspecialchars(ucfirst($reservation['status'] ?? 'N/A')) ?>
                    </span>
                </div>

                <div class="mb-3">
                    <strong class="text-gray-600">Reserved On:</strong>
                    <span><?= htmlspecialchars(date("M d, Y H:i", strtotime($reservation['created_at']))) ?></span>
                </div>

                </div>
        <?php else: ?>
            <p class="text-red-500">Error: Reservation details not found.</p>
        <?php endif; ?>

    </main>

</body>
</html>