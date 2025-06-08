<?php
// **1. Start Session**
session_start();

// **2. Include Database Connection Parameters**
$host = 'localhost';
$db   = 'ventech_db';
$user_db = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE         => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// **3. Get User ID from Session**
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

// **4. Check if User is Logged In**
if ($user_id === null) {
    die("You must be logged in to view your reservations.");
}

$reservations = [];
$error_message = '';
$success_message = '';

// **6. Handle Cancellation Message (if any)**
if (isset($_GET['cancel_success']) && $_GET['cancel_success'] === 'true') {
    $success_message = "Reservation cancelled successfully.";
} elseif (isset($_GET['cancel_error']) && !empty($_GET['cancel_error'])) {
    $error_message = htmlspecialchars($_GET['cancel_error']);
}

// **5. Fetch User's Reservations**
try {
    $pdo = new PDO($dsn, $user_db, $pass, $options);

    $stmt = $pdo->prepare("
        SELECT
            r.id AS reservation_id,
            v.title AS venue_name,
            r.event_date,
            r.start_time,
            r.end_time,
            r.status
        FROM
            reservations r
        JOIN
            venue v ON r.venue_id = v.id
        WHERE
            r.user_id = :user_id
        ORDER BY
            r.created_at DESC
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $reservations = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Database error fetching user reservations: " . $e->getMessage());
    $error_message = "An error occurred while fetching your reservations. Please try again later.";
} finally {
    $pdo = null; // Close connection
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations - Ventech Locator</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script> <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" rel="stylesheet"> <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"> <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        /* You can include your existing styles here or link to a separate CSS file */
    </style>
</head>
<body class="bg-gray-100 py-8 px-4">
    <div class="max-w-3xl mx-auto bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">My Reservations</h2>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Success!</strong>
                <span class="block sm:inline"><?= htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"><?= htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($reservations)): ?>
            <p class="text-gray-600">You haven't made any reservations yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full leading-normal">
                    <thead>
                        <tr>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Venue
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Date
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Start Time
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                End Time
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $reservation): ?>
                            <tr>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <?= htmlspecialchars($reservation['venue_name']); ?>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <?= htmlspecialchars($reservation['event_date']); ?>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <?= htmlspecialchars(date('h:i A', strtotime($reservation['start_time']))); ?>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <?= htmlspecialchars(date('h:i A', strtotime($reservation['end_time']))); ?>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <span class="relative inline-block px-3 py-1 font-semibold text-sm rounded-full <?php
                                        switch ($reservation['status']) {
                                            case 'pending':
                                                echo 'text-yellow-600 bg-yellow-200';
                                                break;
                                            case 'confirmed':
                                                echo 'text-green-600 bg-green-200';
                                                break;
                                            case 'cancelled':
                                                echo 'text-red-600 bg-red-200';
                                                break;
                                            default:
                                                echo 'text-gray-600 bg-gray-200';
                                                break;
                                        }
                                    ?>">
                                        <?= htmlspecialchars(ucfirst($reservation['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <?php if ($reservation['status'] === 'pending'): ?>
                                        <a href="cancel_reservation.php?id=<?= $reservation['reservation_id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to cancel this reservation?')">Cancel</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="mt-6">
            <a href="user_dashboard.php" class="text-indigo-600 hover:text-indigo-900">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>