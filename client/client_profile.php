<?php
// Include the database connection and config files
include_once('../includes/db_connection.php');
include_once('../includes/config.php');

// Start the session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: client_login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch client details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $contact_number = trim($_POST["contact_number"]);
    $location = trim($_POST["location"]);

    // Update user profile
    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, contact_number = ?, location = ? WHERE id = ?");
    $stmt->execute([$username, $email, $contact_number, $location, $user_id]);

    $success = "Profile updated successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
      body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="bg-gray-100">

    <nav class="bg-orange-500 p-4 text-white text-center shadow-md rounded-b-lg">
        <h1 class="text-xl font-bold">Client Profile</h1>
    </nav>

    <div class="container mx-auto mt-8 p-4 flex justify-center">
        <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-2xl">
            <h2 class="text-2xl font-semibold mb-6 text-orange-600 text-center">Edit Your Profile</h2>

            <?php if (isset($success)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md shadow-md">
                    <i class="fas fa-check-circle mr-2 text-green-500"></i> <?= $success ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-semibold text-gray-700">Username <span class="text-red-500">*</span></label>
                    <div class="mt-1">
                        <input type="text" id="username" name="username" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="<?= htmlspecialchars($user['username']) ?>" required>
                    </div>
                </div>
                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-700">Email <span class="text-red-500">*</span></label>
                    <div class="mt-1">
                        <input type="email" id="email" name="email" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                </div>
                <div>
                    <label for="contact_number" class="block text-sm font-semibold text-gray-700">Contact Number <span class="text-red-500">*</span></label>
                    <div class="mt-1">
                        <input type="text" name="contact_number" id="contact_number" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="<?= htmlspecialchars($user['contact_number']) ?>" required>
                    </div>
                </div>
                <div>
                    <label for="location" class="block text-sm font-semibold text-gray-700">Location <span class="text-red-500">*</span></label>
                    <div class="mt-1">
                        <input type="text" name="location" id="location" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="<?= htmlspecialchars($user['location']) ?>" required>
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" class="bg-orange-500 hover:bg-orange-700 text-white font-bold py-3 px-6 rounded-md shadow-md focus:outline-none focus:shadow-outline transition duration-300 ease-in-out">
                        Update Profile <i class="fas fa-user-edit ml-2"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <footer class="bg-gray-800 text-white text-center py-4 rounded-t-lg shadow-md mt-8">
        <p>© <?= date('Y') ?> Ventech Locator. All rights reserved.</p>
    </footer>

    <script>
      // You can add some client-side validation here if needed
      // For example, to check if the email is valid before submitting the form
      document.querySelector('form').addEventListener('submit', function(event) {
        const emailInput = document.getElementById('email');
        if (!emailInput.value.trim() || !/^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$/.test(emailInput.value)) {
          alert('Please enter a valid email address.');
          event.preventDefault(); // Prevent form submission
        }
      });
    </script>
</body>
</html>
