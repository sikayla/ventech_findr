<?php
// Include the database connection and config files from the 'includes' folder
// Adjust paths as needed based on your file structure
include_once('../includes/db_connection.php'); // Assuming includes folder is one level up
// include_once('../includes/config.php'); // Uncomment if you have a config file and need it

// Start session for login
session_start();

// Initialize variables for errors and success messages
$errors = [];
$success = ""; // This variable is not used in the provided PHP login logic, but kept for consistency

// Check if the form was submitted via POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize email and password input
    // Using filter_input is generally preferred for sanitization and validation
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST["password"] ?? ''; // Use null coalescing to avoid undefined index notice

    // Validate email and password presence
    if (empty($email)) {
        $errors[] = "Email is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    // If no initial validation errors, proceed to authenticate against the database
    if (empty($errors)) {
        // Check if $pdo connection is available from db_connection.php
        if (!isset($pdo) || !$pdo instanceof PDO) {
             error_log("PDO connection not available in client_login.php");
             $errors[] = "Database connection error. Please try again later.";
        } else {
            try {
                // Prepare SQL query to find user by email and ensure their role is 'client'
                // Using prepared statements prevents SQL injection
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'client'");
                $stmt->execute([$email]);
                $user = $stmt->fetch(); // Fetch the user row

                // Check if a user was found
                if ($user) {
                    // Verify the submitted password against the hashed password in the database
                    if (password_verify($password, $user['password'])) {
                        // Password is correct, set session variables for the logged-in user
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email']; // Optionally store email in session
                        $_SESSION['user_role'] = 'client'; // Set the user's role in the session

                        // Regenerate session ID to prevent session fixation
                        session_regenerate_id(true);

                        // Instead of header() redirect, use JavaScript to redirect the parent window
                        // This is necessary because client_login.php is loaded in an iframe.
                        echo '<script type="text/javascript">';
                        echo 'if (window.parent && window.parent.location) {';
                        echo '    window.parent.location.href = "/ventech_locator/client_dashboard.php";'; // Correct path to dashboard
                        echo '} else {';
                        echo '    window.location.href = "/ventech_locator/client_dashboard.php";'; // Fallback if not in iframe
                        echo '}';
                        echo '</script>';
                        exit; // Stop script execution after redirection
                    } else {
                        // Password does not match
                        $errors[] = "Incorrect password.";
                    }
                } else {
                    // No user found with the provided email and 'client' role
                    $errors[] = "No client account found with this email.";
                }
            } catch (PDOException $e) {
                // Log database errors
                error_log("Database login error in client_login.php: " . $e->getMessage());
                $errors[] = "An error occurred during login. Please try again.";
            }
        }
    }
}

// Determine the path to the client signup page
// Adjust this path based on where client_signup.php is located relative to client_login.php
$clientSignupLink = '/ventech_locator/client/client_signup.php'; // Assuming client_signup.php is in the same directory
// If client_signup.php is in the parent directory, you might use:
// $clientSignupLink = '../client_signup.php';

// Determine the path to the forgot password page
// Adjust this path based on where forgot_password.php is located relative to client_login.php
$forgotPasswordLink = 'forgot_password.php'; // Assuming forgot_password.php is in the same directory
// If forgot_password.php is in a different directory, e.g., 'users', you might use:
// $forgotPasswordLink = '../users/forgot_password.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Client Login - Ventech Locator</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
  />
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap');
    body {
        font-family: 'Poppins', sans-serif;
        background-color: #f0f2f5; /* Light grey background */
    }
    /* Custom styles for the login form */
    .login-container {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .login-box {
        background-color: white;
        border-radius: 1rem; /* rounded-2xl */
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); /* shadow-xl */
        overflow: hidden;
        max-width: 800px;
        width: 100%;
    }
    .left-panel {
        background-color: #00303f; /* Dark blue-grey */
        color: white;
        padding: 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
    }
    .right-panel {
        padding: 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .social-buttons button {
        border: 1px solid #00303f;
        color: #00303f;
        width: 40px;
        height: 40px;
        border-radius: 0.25rem; /* rounded-md */
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem; /* text-xs */
        font-weight: 600; /* font-semibold */
        transition: background-color 0.2s, color 0.2s;
    }
    .social-buttons button:hover {
        background-color: #00303f;
        color: white;
    }
    input[type="email"],
    input[type="password"] {
        background-color: #e2e8f0; /* gray-300 */
        color: #1a202c; /* black */
        font-size: 0.875rem; /* text-sm */
        border-radius: 0.125rem; /* rounded-sm */
        padding: 0.5rem 0.75rem; /* px-3 py-2 */
        width: 100%;
        border: none;
    }
    input:focus, textarea:focus, select:focus {
        outline: 2px solid transparent;
        outline-offset: 2px;
        box-shadow: ring-2 ring-blue-500; /* Example focus ring with blue */
    }
    .form-button {
        background-color: #b5b600; /* Yellow-green */
        color: white;
        font-weight: 600; /* font-semibold */
        font-size: 0.75rem; /* text-xs */
        border-radius: 0.125rem; /* rounded-sm */
        padding: 0.5rem 1.5rem; /* px-6 py-2 */
        margin-top: 0.75rem; /* mt-3 */
        transition: background-color 0.2s;
    }
    .form-button:hover {
        background-color: #a0a000; /* Darker yellow-green */
    }

    /* Loading Overlay Styles (from user_login.php, adjusted for consistency) */
    #loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: white; /* White background as seen in the image */
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 10000; /* Ensure it's on top of everything */
        opacity: 1; /* Start visible */
        visibility: visible;
        transition: opacity 0.5s ease-out, visibility 0.5s ease-out;
    }

    #loading-overlay.hidden {
        opacity: 0;
        visibility: hidden;
    }

    .loader-container {
        display: flex;
        flex-direction: column; /* Pin above bar */
        align-items: center;
        justify-content: center;
        position: relative;
        width: 150px; /* Adjust as needed for container size */
        height: 150px; /* Adjust as needed for container size */
    }

    .loader-pin {
        color: #ff5722; /* Orange color from image */
        font-size: 3.5rem; /* Large pin size */
        margin-bottom: 15px; /* Space between pin and bar */
        animation: bounce 1.5s infinite; /* Animation for bouncing */
    }

    @keyframes bounce {
        0%, 20%, 50%, 80%, 100% {
            transform: translateY(0);
        }
        40% {
            transform: translateY(-20px);
        }
        60% {
            transform: translateY(-10px);
        }
    }

    .loader-bar {
        width: 80px; /* Length of the bar */
        height: 4px;
        background-color: #f0f0f0; /* Light gray background for the bar */
        border-radius: 2px;
        position: relative;
        overflow: hidden; /* Ensure indicator stays within bounds */
    }

    .loader-indicator {
        position: absolute;
        top: 0;
        left: -20px; /* Start off-screen to the left */
        width: 20px; /* Size of the moving dot/line */
        height: 100%;
        background-color: #ff5722; /* Orange color for the moving indicator */
        border-radius: 2px;
        animation: moveIndicator 2s linear infinite; /* Animation for moving */
    }

    @keyframes moveIndicator {
        0% {
            left: -20px;
        }
        50% {
            left: 100%;
        }
        100% {
            left: -20px;
        }
    }
  </style>
</head>
<body>
  <!-- Loading Overlay -->
  <div id="loading-overlay">
      <div class="loader-container">
          <i class="fas fa-map-marker-alt loader-pin"></i>
          <div class="loader-bar">
              <div class="loader-indicator"></div>
          </div>
      </div>
  </div>

  <div class="flex items-center justify-center bg-white p-6">
    <div class="flex flex-col md:flex-row bg-white rounded-3xl w-full max-w-4xl overflow-hidden">
      <div class="flex flex-col justify-center px-10 py-12 md:w-1/2">
        <h2 class="font-poppins font-semibold text-2xl mb-3">Client Login</h2>
        
        <div class="flex space-x-3 mb-6 social-buttons">
          <button aria-label="Login with Google">G+</button>
          <button aria-label="Login with Facebook"><i class="fab fa-facebook-f"></i></button>
          <button aria-label="Login with GitHub"><i class="fab fa-github"></i></button>
          <button aria-label="Login with LinkedIn"><i class="fab fa-linkedin-in"></i></button>
        </div>
        <div class="text-center mb-4">
          <span class="font-poppins font-semibold text-lg text-black">OR</span>
          <p class="text-xs text-black">Login With Your Email & Password</p>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 mb-4 rounded text-sm" role="alert">
            <p class="font-bold">Login Error:</p>
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form id="loginForm" action="client_login.php" method="POST" class="space-y-3" aria-label="Client Login form" novalidate="">
          <div class="relative">
            <input
              type="email"
              name="email"
              placeholder="Email"
              class="w-full bg-gray-300 text-black text-sm rounded-sm px-3 py-2 focus:outline-none"
              value="<?= isset($email) ? htmlspecialchars($email) : '' ?>"
              required
            />
          </div>
          <div class="relative">
            <input
              type="password"
              name="password"
              placeholder="Password"
              class="w-full bg-gray-300 text-black text-sm rounded-sm px-3 py-2 focus:outline-none"
              required
            />
          </div>
          <button
            type="submit"
            class="bg-[#b5b600] text-white font-semibold text-xs rounded-sm px-6 py-2 mt-3 hover:bg-[#a0a000] transition"
          >
            LOGIN
          </button>
        </form>

        <p class="text-center text-xs mt-4">
            <a class="font-bold text-blue-700 hover:underline" href="<?= $forgotPasswordLink ?>">Forgot your password?</a>
        </p>
        <p class="text-center text-xs">
            Don't have an account? <a class="font-bold text-blue-700 hover:underline" href="javascript:void(0);" onclick="if (window.parent && window.parent.openClientSignupModal) { window.parent.closeClientLoginModal(); window.parent.openClientSignupModal(); } else { window.location.href='<?= $clientSignupLink ?>'; }">Register here</a>
        </p>
      </div>

      <div class="md:w-1/2 bg-[#00303f] rounded-tr-3xl rounded-br-3xl flex flex-col justify-center items-center px-10 py-12 text-white text-center">
        <h2 class="font-poppins font-semibold text-2xl mb-3">Hello</h2>
        <p class="text-xs mb-6">Register to use all features in our site</p>
        <a href="javascript:void(0);" onclick="if (window.parent && window.parent.openClientSignupModal) { window.parent.closeClientLoginModal(); window.parent.openClientSignupModal(); } else { window.location.href='<?= $clientSignupLink ?>'; }" class="border border-white text-white text-xs font-semibold px-6 py-2 rounded-sm hover:bg-white hover:text-[#00303f] transition">
          SIGN UP
        </a>
      </div>
    </div>
  </div>

  <script>
        // JavaScript to show the loading overlay on form submission and hide on page load
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loadingOverlay = document.getElementById('loading-overlay');

            // Show loading overlay immediately when the page starts loading
            if (loadingOverlay) {
                loadingOverlay.classList.add('visible');
            }

            if (loginForm && loadingOverlay) {
                // Show loading overlay when the form is submitted
                loginForm.addEventListener('submit', function() {
                    loadingOverlay.classList.add('visible');
                });
            }
        });

        // Hide loading overlay with minimum display time
        window.addEventListener('load', function() {
            const loadingOverlay = document.getElementById('loading-overlay');
            let minLoadTimePassed = false;
            let pageFullyLoaded = false;

            // Set a timeout for the minimum 3-second display
            setTimeout(() => {
                minLoadTimePassed = true;
                // If page has already fully loaded AND minimum time has passed, hide it.
                if (pageFullyLoaded && loadingOverlay) {
                    loadingOverlay.classList.add('hidden');
                    loadingOverlay.addEventListener('transitionend', function handler() {
                        if (loadingOverlay.classList.contains('hidden')) {
                            loadingOverlay.remove();
                            loadingOverlay.removeEventListener('transitionend', handler);
                        }
                    });
                }
            }, 3000); // 3000 milliseconds = 3 seconds

            pageFullyLoaded = true;
            // If minimum time has already passed, hide it.
            if (minLoadTimePassed && loadingOverlay) {
                loadingOverlay.classList.add('hidden');
                loadingOverlay.addEventListener('transitionend', function handler() {
                    if (loadingOverlay.classList.contains('hidden')) {
                        loadingOverlay.remove();
                        loadingOverlay.removeEventListener('transitionend', handler);
                    }
                });
            }
        });
    </script>
</body>
</html>