<?php
// **1. Start Session**
session_start();

// **2. Language Handling**
$supported_languages = [
    'en' => 'English',
    'es' => 'Español',
    'fr' => 'Français',
    'de' => 'Deutsch',
    'it' => 'Italiano',
    'pt' => 'Português',
    'ru' => 'Русский',
    'zh' => '中文',
    'ja' => '日本語',
    'ko' => '한국어',
];

// Determine the language to use
$lang_code = 'en'; // Default language

// Check if a language is set in the URL
if (isset($_GET['lang']) && array_key_exists($_GET['lang'], $supported_languages)) {
    $lang_code = $_GET['lang'];
    $_SESSION['lang'] = $lang_code;
} elseif (isset($_SESSION['lang']) && array_key_exists($_SESSION['lang'], $supported_languages)) {
    $lang_code = $_SESSION['lang'];
}

// Load the language file
$lang_file = __DIR__ . "/lang/{$lang_code}.php";
if (file_exists($lang_file)) {
    include $lang_file;
} else {
    include __DIR__ . "/lang/en.php";
    $lang_code = 'en';
}

// Function to get translated string
function __($key) {
    global $lang;
    return $lang[$key] ?? $key;
}

// **3. Database Connection Parameters**
// Centralize your database connection in includes/db_connection.php
// and include it here instead of defining it directly.
// For now, keeping it here as per original file, but recommendation is to centralize.
$host = 'localhost';
$db   = 'ventech_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// **4. Initialize Variables**
$errors = [];
$success_message = '';

// Variables to retain form input values
$username_val = '';
$email_val = '';
$contact_number_val = '';
$location_val = '';

// **5. Establish PDO Connection**
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log("Database connection error in client_signup.php: " . $e->getMessage());
    $errors[] = __('error_general');
}

// **6. Handle Form Submission**
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($errors)) {
    // Retain input values
    $username_val = trim($_POST['username'] ?? '');
    $email_val = trim($_POST['email'] ?? '');
    $contact_number_val = trim($_POST['contact_number'] ?? '');
    $location_val = trim($_POST['location'] ?? '');

    // Sanitize and validate input
    $username = htmlspecialchars($username_val, ENT_QUOTES, 'UTF-8');
    $email = filter_var($email_val, FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $contact_number = htmlspecialchars($contact_number_val, ENT_QUOTES, 'UTF-8');
    $location = htmlspecialchars($location_val, ENT_QUOTES, 'UTF-8');

    // Basic Validation Checks
    if (empty($username)) $errors[] = __('error_required_username');
    if (empty($email)) {
        $errors[] = __('error_required_email');
    } elseif ($email === false) {
        $errors[] = __('error_invalid_email');
    }
    if (empty($password)) $errors[] = __('error_required_password');
    if ($password !== $confirm_password) $errors[] = __('error_password_match');
    if (strlen($password) < 8) $errors[] = __('error_password_length');
    // It's good practice to add these to your language file for translation
    if (empty($contact_number)) $errors[] = __('error_required_contact_number'); // Assuming you add this to lang file
    if (empty($location)) $errors[] = __('error_required_location');             // Assuming you add this to lang file


    // Check if username or email already exists in the database
    if (empty($errors)) {
        try {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt_check->execute([$username, $email]);
            $count = $stmt_check->fetchColumn();

            if ($count > 0) {
                $errors[] = __('error_user_exists');
            }
        } catch (PDOException $e) {
            error_log("Database check error in client_signup.php: " . $e->getMessage());
            $errors[] = __('error_db_check');
        }
    }

    // **7. Insert New User if No Errors**
    if (empty($errors)) {
        // Hash the password before storing
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Include contact_number and location in the INSERT statement
            $stmt_insert = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at, contact_number, location) VALUES (?, ?, ?, 'client', NOW(), ?, ?)");
            $stmt_insert->execute([$username, $email, $hashed_password, $contact_number, $location]);

            // Redirect to client_login.php after successful signup
            header("Location: client_login.php?signup_success=true");
            exit;

        } catch (PDOException $e) {
            error_log("Database insert error in client_signup.php: " . $e->getMessage());
            $errors[] = __('error_db_insert');
        }
    }
}

// Determine login link
$loginLink = 'client_login.php';

?>
<!DOCTYPE html>
<html lang="<?= $lang_code ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= __('signup_title') ?> - <?= __('app_name') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
  />
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap');
    /* Using Poppins for headings as per index (13).html, and Open Sans for body as per original */
    body {
        font-family: "Open Sans", sans-serif;
    }
    .font-poppins {
        font-family: 'Poppins', sans-serif;
    }

    /* Custom styles for the language dropdown */
    .language-dropdown {
        position: relative;
        display: inline-block;
    }

    .language-dropdown-content {
        display: none;
        position: absolute;
        background-color: #f9f9f9;
        min-width: 120px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        z-index: 100;
        right: 0;
        border-radius: 0.25rem;
        overflow: hidden;
        padding: 0;
    }

    .language-dropdown-content a {
        color: black;
        padding: 8px 12px;
        text-decoration: none;
        display: block;
        font-size: 0.75rem;
        text-align: left;
    }

    .language-dropdown-content a:hover {
        background-color: #f1f1f1;
    }

    .language-dropdown:hover .language-dropdown-content {
        display: block;
    }
  </style>
</head>
<body>
  <div class="flex items-center justify-center bg-white p-6">
    <div class="flex flex-col md:flex-row bg-white rounded-3xl w-full max-w-4xl overflow-hidden">
      <div class="flex flex-col justify-center px-10 py-12 md:w-1/2">
        <h2 class="font-poppins font-semibold text-2xl mb-6 text-black"><?= __('signup_title') ?></h2>
        <p class="text-xs text-black mb-4"><?= __('signup_description_1') ?></p>
        <p class="text-xs text-black mb-6"><?= __('signup_description_2') ?></p>

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
                <p class="mt-2 text-sm">
                    <?= __('success_login_prompt') ?> <a href="<?= $loginLink ?>"
                                 class="font-bold underline hover:text-green-900"><?= __('success_login_link') ?></a>.
                </p>
            </div>
        <?php endif; ?>

        <form action="client_signup.php" method="POST" class="space-y-3" aria-label="<?= __('signup_title') ?> form" novalidate="">
          <input
            type="text"
            name="username"
            placeholder="<?= __('label_username') ?>"
            class="w-full bg-gray-300 text-black text-sm rounded-sm px-3 py-2 focus:outline-none"
            value="<?= htmlspecialchars($username_val) ?>"
            required
          />
          <input
            type="email"
            name="email"
            placeholder="<?= __('label_email') ?>"
            class="w-full bg-gray-300 text-black text-sm rounded-sm px-3 py-2 focus:outline-none"
            value="<?= htmlspecialchars($email_val) ?>"
            required
          />
          <input
            type="password"
            name="password"
            placeholder="<?= __('label_password') ?>"
            class="w-full bg-gray-300 text-black text-sm rounded-sm px-3 py-2 focus:outline-none"
            required
          />
          <input
            type="password"
            name="confirm_password"
            placeholder="<?= __('label_confirm_password') ?>"
            class="w-full bg-gray-300 text-black text-sm rounded-sm px-3 py-2 focus:outline-none"
            required
          />
          <input
            type="tel"
            name="contact_number"
            placeholder="Contact Number" class="w-full bg-gray-300 text-black text-sm rounded-sm px-3 py-2 focus:outline-none"
            value="<?= htmlspecialchars($contact_number_val) ?>"
            required
          />
          <input
            type="text"
            name="location"
            placeholder="Location" class="w-full bg-gray-300 text-black text-sm rounded-sm px-3 py-2 focus:outline-none"
            value="<?= htmlspecialchars($location_val) ?>"
            required
          />
          <button
            type="submit"
            class="bg-[#b5b600] text-white font-semibold text-xs rounded-sm px-6 py-2 mt-3 hover:bg-[#a0a000] transition"
          >
            <?= __('button_signup') ?>
          </button>
        </form>

        <p class="text-center text-xs mt-4">
            <?= __('login_prompt') ?>
            <a class="font-bold text-blue-700 hover:underline" href="<?= $loginLink ?>">
                <?= __('login_link') ?>
            </a>
        </p>
      </div>

      <div class="md:w-1/2 bg-[#00303f] rounded-tr-3xl rounded-br-3xl flex flex-col justify-center items-center px-10 py-12 text-white text-center">
        <h2 class="font-poppins font-semibold text-2xl mb-3">Welcome!</h2>
        <p class="text-xs mb-6">Join our community to access exclusive features and services.</p>
        <p class="text-xs mb-6">Already have an account?</p>
        <a href="<?= $loginLink ?>" class="border border-white text-white text-xs font-semibold px-6 py-2 rounded-sm hover:bg-white hover:text-[#00303f] transition">
          LOGIN
        </a>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        const languageDropdown = document.querySelector('.language-dropdown');
        if (languageDropdown) {
            languageDropdown.addEventListener('click', function(event) {
                // Toggle visibility of the dropdown content
                const content = this.querySelector('.language-dropdown-content');
                if (content) {
                    content.style.display = content.style.display === 'block' ? 'none' : 'block';
                }
                event.stopPropagation(); // Prevent click from closing immediately
            });

            // Close the dropdown if clicked outside
            window.addEventListener('click', function(event) {
                const content = languageDropdown.querySelector('.language-dropdown-content');
                if (content && event.target !== languageDropdown && !languageDropdown.contains(event.target)) {
                    content.style.display = 'none';
                }
            });
        }
    });
  </script>
</body>
</html>