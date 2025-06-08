<?php
// add_venue.php
// This file will handle adding a new venue.
// You will need to implement the form and database insertion logic here.

session_start();
require_once 'config.php';

// Check if the user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle form submission for adding a venue
    // Example:
    // $title = $_POST['title'] ?? '';
    // $price = $_POST['price'] ?? '';
    // ... validate and insert into 'venue' table

    // For now, just a placeholder message
    $_SESSION['message'] = "Add Venue functionality to be implemented here.";
    $_SESSION['message_type'] = "info";
    header('Location: dashboard.php?view=venues');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Venue</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { max-width: 600px; }
        .btn-submit { background-color: #4f46e5; color: white; }
        .btn-submit:hover { background-color: #4338ca; }
        .btn-cancel { background-color: #6b7280; color: white; }
        .btn-cancel:hover { background-color: #4b5563; }
    </style>
</head>
<body>
    <div class="card bg-white p-8 rounded-lg shadow-lg w-full">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Add New Venue</h2>

        <?php if (!empty($message)): ?>
            <div class="p-4 mb-4 rounded-md border <?php echo $message_type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="add_venue.php" method="POST" class="space-y-4">
            <div>
                <label for="title" class="block text-gray-700 text-sm font-bold mb-2">Venue Title:</label>
                <input type="text" id="title" name="title" required class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div>
                <label for="price" class="block text-gray-700 text-sm font-bold mb-2">Price Per Hour:</label>
                <input type="number" step="0.01" id="price" name="price" required class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div>
                <label for="location" class="block text-gray-700 text-sm font-bold mb-2">Location:</label>
                <input type="text" id="location" name="location" required class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="flex justify-end space-x-4">
                <button type="submit" class="btn-submit py-2 px-6 rounded-md font-semibold transition duration-200">Add Venue</button>
                <a href="dashboard.php?view=venues" class="btn-cancel py-2 px-6 rounded-md font-semibold transition duration-200 flex items-center justify-center">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>