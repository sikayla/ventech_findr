<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6; /* Light gray background */
        }
        .container {
            max-width: 1200px;
        }
        .table-header {
            background-color: #4f46e5; /* Indigo 600 */
            color: white;
        }
        .btn-primary {
            background-color: #4f46e5; /* Indigo 600 */
            color: white;
        }
        .btn-primary:hover {
            background-color: #4338ca; /* Indigo 700 */
        }
        .btn-danger {
            background-color: #ef4444; /* Red 500 */
            color: white;
        }
        .btn-danger:hover {
            background-color: #dc2626; /* Red 600 */
        }
        .sidebar {
            width: 250px;
            background-color: #1f2937; /* Dark gray */
        }
        .sidebar a {
            color: #d1d5db; /* Light gray text */
        }
        .sidebar a:hover {
            background-color: #374151; /* Slightly lighter dark gray */
            color: white;
        }
    </style>
</head>
<body>

<?php
// dashboard.php
// PHP code for the admin panel dashboard.

// Start session to manage user login state
session_start();

// Include database configuration
require_once 'config.php';

// Check if the user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // If not logged in or not an admin, redirect to login page
    header('Location: login.php');
    exit();
}

// Determine which view to display
$view = $_GET['view'] ?? 'dashboard'; // Default to 'dashboard'

// Initialize data arrays
$users = [];
$venues = [];
$reservations = [];

// Handle user deletion (only if view is 'users')
if ($view === 'users' && isset($_GET['action']) && $_GET['action'] === 'delete_user' && isset($_GET['id'])) {
    $user_id_to_delete = $_GET['id'];

    // Prepare a DELETE statement
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id_to_delete);

    if ($stmt->execute()) {
        $_SESSION['message'] = "User deleted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting user: " . $stmt->error;
        $_SESSION['message_type'] = "error";
    }
    $stmt->close();
    // Redirect to prevent re-submission on refresh
    header('Location: dashboard.php?view=users');
    exit();
}

// Handle venue deletion (only if view is 'venues')
if ($view === 'venues' && isset($_GET['action']) && $_GET['action'] === 'delete_venue' && isset($_GET['id'])) {
    $venue_id_to_delete = $_GET['id'];

    // Prepare a DELETE statement
    $stmt = $conn->prepare("DELETE FROM venue WHERE id = ?");
    $stmt->bind_param("i", $venue_id_to_delete);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Venue deleted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting venue: " . $stmt->error;
        $_SESSION['message_type'] = "error";
    }
    $stmt->close();
    // Redirect to prevent re-submission on refresh
    header('Location: dashboard.php?view=venues');
    exit();
}


// Fetch data based on the current view
if ($view === 'users' || $view === 'dashboard') {
    $sql_users = "SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC";
    $result_users = $conn->query($sql_users);
    if ($result_users->num_rows > 0) {
        while ($row = $result_users->fetch_assoc()) {
            $users[] = $row;
        }
    }
}

if ($view === 'venues' || $view === 'dashboard') {
    $sql_venues = "SELECT id, title, price, location, status, created_at FROM venue ORDER BY created_at DESC";
    $result_venues = $conn->query($sql_venues);
    if ($result_venues->num_rows > 0) {
        while ($row = $result_venues->fetch_assoc()) {
            $venues[] = $row;
        }
    }
}

if ($view === 'reservations') {
    $sql_reservations = "SELECT vr.id, v.title AS venue_title, u.username AS user_username, vr.event_date, vr.start_time, vr.end_time, vr.total_cost, vr.status, vr.created_at
                         FROM venue_reservations vr
                         LEFT JOIN venue v ON vr.venue_id = v.id
                         LEFT JOIN users u ON vr.user_id = u.id
                         ORDER BY vr.created_at DESC";
    $result_reservations = $conn->query($sql_reservations);
    if ($result_reservations->num_rows > 0) {
        while ($row = $result_reservations->fetch_assoc()) {
            $reservations[] = $row;
        }
    }
}


// Close the database connection
$conn->close();
?>

<div class="flex h-screen">
    <div class="sidebar flex flex-col p-6 shadow-lg rounded-r-lg">
        <div class="text-white text-2xl font-bold mb-8">Admin Panel</div>
        <nav>
            <ul class="space-y-4">
                <li><a href="dashboard.php" class="block py-2 px-4 rounded-md transition duration-200 <?php echo ($view === 'dashboard') ? 'bg-gray-700 text-white' : ''; ?>">Dashboard</a></li>
                <li><a href="dashboard.php?view=users" class="block py-2 px-4 rounded-md transition duration-200 <?php echo ($view === 'users') ? 'bg-gray-700 text-white' : ''; ?>">Manage Users</a></li>
                <li><a href="dashboard.php?view=venues" class="block py-2 px-4 rounded-md transition duration-200 <?php echo ($view === 'venues') ? 'bg-gray-700 text-white' : ''; ?>">Manage Venues</a></li>
                <li><a href="dashboard.php?view=reservations" class="block py-2 px-4 rounded-md transition duration-200 <?php echo ($view === 'reservations') ? 'bg-gray-700 text-white' : ''; ?>">Manage Reservations</a></li>
                <li><a href="export_db.php" class="block py-2 px-4 rounded-md transition duration-200 text-blue-400 hover:text-blue-300">Export Database (SQL Dump)</a></li>
                <li><a href="logout.php" class="block py-2 px-4 rounded-md transition duration-200 text-red-400 hover:text-red-300">Logout</a></li>
            </ul>
        </nav>
    </div>

    <div class="flex-1 p-8 overflow-y-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Welcome, Admin!</h1>

        <?php
        // Display session messages (success/error)
        if (isset($_SESSION['message'])) {
            $message_class = ($_SESSION['message_type'] === 'success') ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
            echo '<div class="p-4 mb-4 rounded-md border ' . $message_class . '">';
            echo $_SESSION['message'];
            echo '</div>';
            unset($_SESSION['message']); // Clear the message after displaying
            unset($_SESSION['message_type']);
        }
        ?>

        <?php if ($view === 'dashboard'): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">Total Users</h3>
                    <p class="text-4xl font-bold text-indigo-600"><?php echo count($users); ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">Total Venues</h3>
                    <p class="text-4xl font-bold text-green-600"><?php echo count($venues); ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">Total Reservations</h3>
                    <p class="text-4xl font-bold text-purple-600"><?php echo count($reservations); ?></p>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                <h2 class="text-2xl font-semibold text-gray-700 mb-4">Recent Users</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white rounded-lg overflow-hidden">
                        <thead>
                            <tr class="table-header text-left">
                                <th class="py-3 px-4 uppercase font-semibold text-sm rounded-tl-lg">ID</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm">Username</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm">Email</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm rounded-tr-lg">Role</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="4" class="py-4 px-4 text-center">No users found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach (array_slice($users, 0, 5) as $user): // Show only 5 recent users ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($user['id']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($user['role']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-right mt-4">
                    <a href="dashboard.php?view=users" class="text-indigo-600 hover:text-indigo-800 font-semibold">View All Users &rarr;</a>
                </div>
            </div>

        <?php elseif ($view === 'users'): ?>
            <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                <h2 class="text-2xl font-semibold text-gray-700 mb-4">Users Management</h2>
                <div class="mb-4">
                    <a href="add_user.php" class="btn-primary py-2 px-4 rounded-md text-sm transition duration-200">Add New User</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white rounded-lg overflow-hidden">
                        <thead>
                            <tr class="table-header text-left">
                                <th class="py-3 px-4 uppercase font-semibold text-sm rounded-tl-lg">ID</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm">Username</th>
                                <th class="py-3 px-4 uppercase font-semibold  text-sm">Email</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm">Role</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm">Created At</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm rounded-tr-lg">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" class="py-4 px-4 text-center">No users found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($user['id']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($user['role']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($user['created_at']); ?></td>
                                        <td class="py-3 px-4 flex space-x-2">
                                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn-primary py-2 px-4 rounded-md text-sm transition duration-200">Edit</a>
                                            <a href="dashboard.php?view=users&action=delete_user&id=<?php echo $user['id']; ?>" class="btn-danger py-2 px-4 rounded-md text-sm transition duration-200" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($view === 'venues'): ?>
            <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                <h2 class="text-2xl font-semibold text-gray-700 mb-4">Venues Management</h2>
                <div class="mb-4">
                    <a href="add_venue.php" class="btn-primary py-2 px-4 rounded-md text-sm transition duration-200">Add New Venue</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white rounded-lg overflow-hidden">
                        <thead>
                            <tr class="table-header text-left">
                                <th class="py-3 px-4 uppercase font-semibold text-sm rounded-tl-lg">ID</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm">Title</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm">Price</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm">Location</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm">Status</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm">Created At</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm rounded-tr-lg">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            <?php if (empty($venues)): ?>
                                <tr>
                                    <td colspan="7" class="py-4 px-4 text-center">No venues found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($venues as $venue): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($venue['id']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($venue['title']); ?></td>
                                        <td class="py-3 px-4">$<?php echo htmlspecialchars(number_format($venue['price'], 2)); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($venue['location']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($venue['status']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($venue['created_at']); ?></td>
                                        <td class="py-3 px-4 flex space-x-2">
                                            <a href="edit_venue.php?id=<?php echo $venue['id']; ?>" class="btn-primary py-2 px-4 rounded-md text-sm transition duration-200">Edit</a>
                                            <a href="dashboard.php?view=venues&action=delete_venue&id=<?php echo $venue['id']; ?>" class="btn-danger py-2 px-4 rounded-md text-sm transition duration-200" onclick="return confirm('Are you sure you want to delete this venue?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($view === 'reservations'): ?>
            <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                <h2 class="text-2xl font-semibold text-gray-700 mb-4">Reservations Management</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white rounded-lg overflow-hidden">
                        <thead>
                            <tr class="table-header text-left">
                                <th class="py-3 px-4 uppercase font-semibold text-sm rounded-tl-lg">ID</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm">Venue</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm">User</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm">Date</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm">Time</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm">Total Cost</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm">Status</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm">Booked On</th>
                                <th class="py-3 px-4 uppercase font-semibold text-sm rounded-tr-lg">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            <?php if (empty($reservations)): ?>
                                <tr>
                                    <td colspan="9" class="py-4 px-4 text-center">No reservations found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reservations as $reservation): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($reservation['id']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($reservation['venue_title'] ?? 'N/A'); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($reservation['user_username'] ?? 'Guest'); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($reservation['event_date']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($reservation['start_time']) . ' - ' . htmlspecialchars($reservation['end_time']); ?></td>
                                        <td class="py-3 px-4">$<?php echo htmlspecialchars(number_format($reservation['total_cost'], 2)); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($reservation['status']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($reservation['created_at']); ?></td>
                                        <td class="py-3 px-4 flex space-x-2">
                                            <a href="edit_reservation.php?id=<?php echo $reservation['id']; ?>" class="btn-primary py-2 px-4 rounded-md text-sm transition duration-200">Edit</a>
                                            <a href="dashboard.php?view=reservations&action=delete_reservation&id=<?php echo $reservation['id']; ?>" class="btn-danger py-2 px-4 rounded-md text-sm transition duration-200" onclick="return confirm('Are you sure you want to delete this reservation?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>