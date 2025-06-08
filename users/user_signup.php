<?php
// **1. Start Session**
session_start();

// **2. Language Handling (Removed)**
// The language handling logic has been removed as per your request.
// All strings will now be hardcoded in English or directly in the HTML.

// Function to get translated string (removed, as language handling is removed)
// function __($key) {
//     global $lang;
//     return $lang[$key] ?? $key;
// }

// **3. Database Connection Parameters**
// IMPORTANT: It's highly recommended to centralize this into a shared db_connection.php file
// and include it here instead of defining it directly.
$host = 'localhost';
$db   = 'ventech_db'; // Assuming the user table is in this database
$user = 'root'; // Your database username
$pass = ''; // Your database password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // Use native prepared statements
];

// **4. Initialize Variables**
$errors = [];
$success_message = ''; // For success message after registration

// Variables to retain form input values on error
$username_val = '';
$email_val = '';
$contact_number_val = '';
$location_val = '';
// Passwords are not retained for security

// **5. Establish PDO Connection**
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Log the error and display a user-friendly message
    error_log("Database connection error in user_signup.php: " . $e->getMessage());
    $errors[] = 'An unexpected error occurred. Please try again later.'; // Hardcoded error message
}

// **6. Handle Form Submission**
// Only process if DB connection was successful and form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST" && empty($errors)) {
    // Retain input values (except passwords)
    $username_val = trim($_POST["username"] ?? '');
    $email_val = trim($_POST["email"] ?? '');
    $contact_number_val = trim($_POST["contact_number"] ?? '');
    $location_val = trim($_POST["location"] ?? '');

    // Sanitize and validate input
    $username = htmlspecialchars($username_val, ENT_QUOTES, 'UTF-8');
    $email = filter_var($email_val, FILTER_VALIDATE_EMAIL);
    $password = $_POST["password"] ?? '';
    $repeat_password = $_POST["repeat_password"] ?? '';
    $contact_number = htmlspecialchars($contact_number_val, ENT_QUOTES, 'UTF-8');
    $location = htmlspecialchars($location_val, ENT_QUOTES, 'UTF-8');

    // Basic Validation Checks
    if (empty($username)) $errors[] = 'Name is required.';
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif ($email === false) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (empty($password)) $errors[] = 'Password is required.';
    if (empty($repeat_password)) $errors[] = 'Please repeat your password.';
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if ($password !== $repeat_password) {
        $errors[] = 'Passwords do not match.';
    }
    if (empty($contact_number)) $errors[] = 'Contact number is required.';
    // Location is optional based on previous review, so no required check here.

    // Check for terms acceptance (client-side 'required' is not enough)
    if (!isset($_POST['terms'])) {
        $errors[] = 'You must accept the terms and conditions.';
    }

    // Check if username or email already exists
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Username or email already in use.';
            }
        } catch (PDOException $e) {
            error_log("Database check error in user_signup.php: " . $e->getMessage());
            $errors[] = 'Database error during user check. Please try again.';
        }
    }

    // **7. Insert New User if No Errors**
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Assuming 'user' role by default for this signup
            $insert = $pdo->prepare("INSERT INTO users (username, email, password, contact_number, location, role, created_at) VALUES (?, ?, ?, ?, ?, 'user', NOW())");
            if ($insert->execute([$username, $email, $hashed_password, $contact_number, $location])) {
                // Redirect on success
                header("Location: user_login.php?registered=1");
                exit;
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
        } catch (PDOException $e) {
            error_log("Database insert error in user_signup.php: " . $e->getMessage());
            $errors[] = 'Database error during registration. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en"> <head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register Page</title> <script src="https://cdn.tailwindcss.com"></script>
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>
    <link rel="stylesheet" href="/ventech_locator/css/user/user_signup.css">

  <style>
    
  </style>
</head>
<body class="min-h-screen flex items-center justify-center"> <!-- Removed background style -->
  <div class="max-w-4xl w-full bg-white rounded-2xl flex flex-col md:flex-row overflow-hidden shadow-lg">
    <div class="bg-[#003a44] flex flex-col justify-center items-center text-white p-12 md:w-1/2 rounded-t-2xl md:rounded-tr-none md:rounded-l-2xl relative">
      <div class="absolute top-0 left-0 w-48 h-48 bg-[#003a44] rounded-full -translate-x-1/2 -translate-y-1/2"></div>
      <div class="absolute bottom-0 left-0 w-48 h-48 bg-[#003a44] rounded-full -translate-x-1/2 translate-y-1/2"></div>
      <h2 class="text-3xl font-bold mb-3">Welcome Back!</h2>
      <p class="text-sm mb-8 text-center max-w-xs">Provide your personal details to use all features</p>
      <a href="user_login.php" class="border border-white px-6 py-2 text-xs font-semibold hover:bg-white hover:text-[#003a44] transition rounded">SIGN IN</a>
    </div>

    <div class="flex flex-col p-10 md:w-1/2">
      <h2 class="text-2xl font-bold mb-6 text-center md:text-left">Register With</h2>
      <div class="flex justify-center md:justify-start space-x-4 mb-4">
        <button aria-label="Register with Google" class="border border-gray-300 rounded-md w-10 h-10 flex items-center justify-center text-gray-700 hover:bg-gray-100">
          <i class="fab fa-google text-base"></i>
        </button>
        <button aria-label="Register with Facebook" class="border border-gray-300 rounded-md w-10 h-10 flex items-center justify-center text-gray-700 hover:bg-gray-100">
          <i class="fab fa-facebook-f text-base"></i>
        </button>
        <button aria-label="Register with GitHub" class="border border-gray-300 rounded-md w-10 h-10 flex items-center justify-center text-gray-700 hover:bg-gray-100">
          <i class="fab fa-github text-base"></i>
        </button>
        <button aria-label="Register with LinkedIn" class="border border-gray-300 rounded-md w-10 h-10 flex items-center justify-center text-gray-700 hover:bg-gray-100">
          <i class="fab fa-linkedin-in text-base"></i>
        </button>
      </div>
      <p class="text-center font-semibold mb-2">OR</p>
      <p class="text-xs text-center mb-4">Fill Out The Following Info For Registeration</p>

      <?php if (!empty($errors)): ?>
          <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 mb-4 rounded text-sm" role="alert">
              <p class="font-bold">Error:</p>
              <ul class="list-disc list-inside">
                  <?php foreach ($errors as $error): ?>
                      <li><?= htmlspecialchars($error) ?></li>
                  <?php endforeach; ?>
              </ul>
          </div>
      <?php endif; ?>

      <?php if (!empty($success_message)): ?>
          <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-3 mb-4 rounded text-sm" role="alert">
              <p class="font-bold">Success!</p>
              <p><?= htmlspecialchars($success_message) ?></p>
          </div>
      <?php endif; ?>

      <form method="POST" action="" aria-label="User Signup Form" class="flex flex-col space-y-3">
        <input type="text" name="username" placeholder="Name" class="bg-gray-300 text-gray-700 text-xs px-3 py-2 rounded focus:outline-none" value="<?= htmlspecialchars($username_val) ?>" required />
        <input type="email" name="email" placeholder="Email" class="bg-gray-300 text-gray-700 text-xs px-3 py-2 rounded focus:outline-none" value="<?= htmlspecialchars($email_val) ?>" required />
        <input type="password" name="password" placeholder="Password" class="bg-gray-300 text-gray-700 text-xs px-3 py-2 rounded focus:outline-none" required />
        <input type="password" name="repeat_password" placeholder="Repeat password" class="bg-gray-300 text-gray-700 text-xs px-3 py-2 rounded focus:outline-none" required />
        <input type="text" name="contact_number" placeholder="Contact Number" class="bg-gray-300 text-gray-700 text-xs px-3 py-2 rounded focus:outline-none" value="<?= htmlspecialchars($contact_number_val) ?>" required />
        <input type="text" name="location" placeholder="Location" class="bg-gray-300 text-gray-700 text-xs px-3 py-2 rounded focus:outline-none" value="<?= htmlspecialchars($location_val) ?>" />

        <div class="mt-2">
            <input type="checkbox" id="terms" name="terms" class="mr-2" required>
            <label for="terms" class="text-sm text-gray-700">
                I accept the <a href="#" class="text-blue-700 hover:underline">Terms of Use</a>, <a href="#" class="text-blue-700 hover:underline">Privacy Policy</a> and <a href="#" class="text-blue-700 hover:underline">Cookie Policy</a>.
                <span class="text-red-500">*</span>
            </label>
        </div>
        <div class="mb-6">
            <input type="checkbox" id="newsletter" name="newsletter" class="mr-2">
            <label for="newsletter" class="text-sm text-gray-700">I want to receive news and updates via email.</label>
        </div>
        <p class="text-sm text-gray-700 mb-4"><span class="text-red-500">*</span> Indicates mandatory fields.</p>

        <button type="submit" class="bg-[#b9b900] text-white text-xs font-semibold py-2 rounded mt-2 hover:bg-yellow-600 transition">
            SIGN UP
        </button>
      </form>

      <p class="text-center text-sm text-gray-700 mt-4">
          Already have an account?
          <a class="font-bold text-blue-700 hover:underline" href="user_login.php">
              Log in
          </a>
      </p>
    </div>
  </div>
</body>
</html>