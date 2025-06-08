<?php
// edit_venue.php
// This file will handle editing an existing venue.
// You will need to implement the form and database update logic here.

session_start();
require_once 'config.php';

// Check if the user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$venue_id = $_GET['id'] ?? null;
$venue_data = null;
$message = '';
$message_type = '';

if ($venue_id) {
    $stmt = $conn->prepare("SELECT * FROM venue WHERE id = ?");
    $stmt->bind_param("i", $venue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $venue_data = $result->fetch_assoc();
    } else {
        $message = "Venue not found.";
        $message_type = "error";
    }
    $stmt->close();
} else {
    $message = "No venue ID provided.";
    $message_type = "error";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['venue_id'])) {
    // Handle form submission for updating a venue
    // Example:
    // $update_id = $_POST['venue_id'];
    // $title = $_POST['title'] ?? '';
    // $price = $_POST['price'] ?? '';
    // ... validate and update 'venue' table

    // For now, just a placeholder message
    $_SESSION['message'] = "Edit Venue functionality to be implemented here.";
    $_SESSION['message_type'] = "info";
    header('Location: dashboard.php?view=venues');
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Venue</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { max-width: 600px; }
        .btn-update { background-color: #22c55e; color: white; }
        .btn-update:hover { background-color: #16a34a; }
        .btn-cancel { background-color: #6b7280; color: white; }
        .btn-cancel:hover { background-color: #4b5563; }
    </style>
</head>
<body>
    <div class="card bg-white p-8 rounded-lg shadow-lg w-full">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Edit Venue</h2>

        <?php if (!empty($message)): ?>
            <div class="p-4 mb-4 rounded-md border <?php echo $message_type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($venue_data): ?>
            <form action="edit_venue.php" method="POST" class="space-y-4">
                <input type="hidden" name="venue_id" value="<?php echo htmlspecialchars($venue_data['id']); ?>">
                <div>
                    <label for="title" class="block text-gray-700 text-sm font-bold mb-2">Venue Title:</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($venue_data['title']); ?>" required class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="price" class="block text-gray-700 text-sm font-bold mb-2">Price Per Hour:</label>
                    <input type="number" step="0.01" id="price" name="price" value="<?php echo htmlspecialchars($venue_data['price']); ?>" required class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="location" class="block text-gray-700 text-sm font-bold mb-2">Location:</label>
                    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($venue_data['location']); ?>" required class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="status" class="block text-gray-700 text-sm font-bold mb-2">Status:</label>
                    <select id="status" name="status" class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="open" <?php echo ($venue_data['status'] === 'open') ? 'selected' : ''; ?>>Open</option>
                        <option value="closed" <?php echo ($venue_data['status'] === 'closed') ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
                <div class="flex justify-end space-x-4">
                    <button type="submit" class="btn-update py-2 px-6 rounded-md font-semibold transition duration-200">Update Venue</button>
                    <a href="dashboard.php?view=venues" class="btn-cancel py-2 px-6 rounded-md font-semibold transition duration-200 flex items-center justify-center">Cancel</a>
                </div>
            </form>
        <?php else: ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative" role="alert">
                <span class="block sm:inline"><?php echo $message; ?></span>
            </div>
            <div class="text-center mt-6">
                <a href="dashboard.php?view=venues" class="btn-cancel py-2 px-6 rounded-md font-semibold transition duration-200">Go to Venues Management</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
