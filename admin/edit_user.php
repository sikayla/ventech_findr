<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6; /* Light gray background */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .edit-card {
            max-width: 600px;
        }
        .btn-update {
            background-color: #22c55e; /* Green 500 */
            color: white;
        }
        .btn-update:hover {
            background-color: #16a34a; /* Green 600 */
        }
        .btn-cancel {
            background-color: #6b7280; /* Gray 500 */
            color: white;
        }
        .btn-cancel:hover {
            background-color: #4b5563; /* Gray 600 */
        }
    </style>
</head>
<body>
    <?php
    // edit_user.php
    // This file handles editing an existing user.

    session_start();
    require_once 'config.php';

    // Check if the user is logged in and has admin role
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: login.php');
        exit();
    }

    $user_id = $_GET['id'] ?? null;
    $user_data = null;
    $error_message = '';

    // Fetch user data if ID is provided
    if ($user_id) {
        $stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $user_data = $result->fetch_assoc();
        } else {
            $error_message = "User not found.";
        }
        $stmt->close();
    } else {
        $error_message = "No user ID provided.";
    }

    // Handle form submission for updating user
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
        $update_id = $_POST['user_id'];
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $role = $_POST['role'] ?? 'guest';
        $password = $_POST['password'] ?? ''; // Optional: only update if provided

        // Basic validation
        if (empty($username) || empty($email)) {
            $_SESSION['message'] = "Username and Email cannot be empty.";
            $_SESSION['message_type'] = "error";
            header('Location: edit_user.php?id=' . $update_id);
            exit();
        }

        $sql = "UPDATE users SET username = ?, email = ?, role = ?";
        $params = "sss";
        $values = [$username, $email, $role];

        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql .= ", password = ?";
            $params .= "s";
            $values[] = $hashed_password;
        }

        $sql .= " WHERE id = ?";
        $params .= "i";
        $values[] = $update_id;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($params, ...$values);

        if ($stmt->execute()) {
            $_SESSION['message'] = "User updated successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            if ($conn->errno == 1062) { // MySQL error code for duplicate entry
                $_SESSION['message'] = "Error: Username or Email already exists.";
            } else {
                $_SESSION['message'] = "Error updating user: " . $stmt->error;
            }
            $_SESSION['message_type'] = "error";
        }
        $stmt->close();
        $conn->close();
        header('Location: dashboard.php'); // Redirect back to dashboard after update
        exit();
    }

    $conn->close();
    ?>

    <div class="edit-card bg-white p-8 rounded-lg shadow-lg w-full">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Edit User</h2>

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

        <?php if ($user_data): ?>
            <form action="edit_user.php" method="POST" class="space-y-4">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_data['id']); ?>">
                <div>
                    <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">New Password (leave blank to keep current):</label>
                    <input type="password" id="password" name="password" class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="role" class="block text-gray-700 text-sm font-bold mb-2">Role:</label>
                    <select id="role" name="role" class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="guest" <?php echo ($user_data['role'] === 'guest') ? 'selected' : ''; ?>>Guest</option>
                        <option value="client" <?php echo ($user_data['role'] === 'client') ? 'selected' : ''; ?>>Client</option>
                        <option value="admin" <?php echo ($user_data['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="flex justify-end space-x-4">
                    <button type="submit" class="btn-update py-2 px-6 rounded-md font-semibold transition duration-200">Update User</button>
                    <a href="dashboard.php" class="btn-cancel py-2 px-6 rounded-md font-semibold transition duration-200 flex items-center justify-center">Cancel</a>
                </div>
            </form>
        <?php else: ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative" role="alert">
                <span class="block sm:inline"><?php echo $error_message; ?></span>
            </div>
            <div class="text-center mt-6">
                <a href="dashboard.php" class="btn-cancel py-2 px-6 rounded-md font-semibold transition duration-200">Go to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
