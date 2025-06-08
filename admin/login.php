<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
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
        .login-card {
            max-width: 400px;
        }
        .btn-login {
            background-color: #4f46e5; /* Indigo 600 */
            color: white;
        }
        .btn-login:hover {
            background-color: #4338ca; /* Indigo 700 */
        }
    </style>
</head>
<body>
    <?php
    // login.php
    // This file handles the admin login process.

    // Start session
    session_start();

    // Include database configuration
    require_once 'config.php';

    $error_message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        // Prepare a SELECT statement to fetch user by username
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Verify password using password_verify
            // The provided SQL uses '$2y$10$4ULv/NJcXUyCZBkFQyDtr.0g6IxE5ZBlAi4pbxv2.67xdWamNEoqC'
            // which is a bcrypt hash. The corresponding plain-text password is 'password123'.
            if (password_verify($password, $user['password'])) {
                if ($user['role'] === 'admin') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    header('Location: dashboard.php'); // Redirect to admin dashboard
                    exit();
                } else {
                    $error_message = "You do not have administrative privileges.";
                }
            } else {
                $error_message = "Invalid username or password.";
            }
        } else {
            $error_message = "Invalid username or password.";
        }

        $stmt->close();
        $conn->close();
    }
    ?>

    <div class="login-card bg-white p-8 rounded-lg shadow-lg w-full">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Admin Login</h2>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="space-y-4">
            <div>
                <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
                <input type="text" id="username" name="username" required class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div>
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
                <input type="password" id="password" name="password" required class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <button type="submit" class="btn-login w-full py-2 px-4 rounded-md font-semibold transition duration-200">Login</button>
        </form>
    </div>
</body>
</html>
