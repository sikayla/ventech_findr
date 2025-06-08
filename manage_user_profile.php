<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Start session to manage user ID and flash messages
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json'); // Respond with JSON

$response = [
    'success' => false,
    'message' => 'An unknown error occurred.',
    'errors' => []
];

// Database connection parameters (replace with your actual credentials)
$host = 'localhost';
$db   = 'ventech_db';
$user_db = 'root';
$pass = ''; // IMPORTANT: Use environment variables or a secure config for production!
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user_db, $pass, $options);

    // Get the action from GET parameters (add or update)
    $action = $_GET['action'] ?? '';
    $user_id = null; // Initialize user_id

    // --- Input Validation ---
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile_country_code = trim($_POST['mobile_country_code'] ?? '+63');
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // General validation for all operations
    if (empty($full_name)) {
        $response['errors']['full_name'] = 'Full Name is required.';
    } elseif (strlen($full_name) > 255) {
        $response['errors']['full_name'] = 'Full Name cannot exceed 255 characters.';
    }

    if (empty($email)) {
        $response['errors']['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['errors']['email'] = 'Invalid email format.';
    } elseif (strlen($email) > 255) {
        $response['errors']['email'] = 'Email cannot exceed 255 characters.';
    } else {
        // Check for email uniqueness only during ADD or if email changes during UPDATE
        $check_email_stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email" . ($action == 'update' ? " AND id != :user_id" : ""));
        $check_email_stmt->bindParam(':email', $email);
        if ($action == 'update') {
             $user_id = intval($_POST['user_id'] ?? 0); // Get user_id for update
             $check_email_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        }
        $check_email_stmt->execute();
        if ($check_email_stmt->fetch()) {
            $response['errors']['email'] = 'This email is already registered.';
        }
    }

    if (!empty($mobile_number) && !preg_match('/^[0-9\s\-\(\)\+]+$/', $mobile_number)) {
        $response['errors']['mobile_number'] = 'Invalid mobile number format.';
    } elseif (strlen($mobile_number) > 50) {
        $response['errors']['mobile_number'] = 'Mobile number cannot exceed 50 characters.';
    }

    if (strlen($address) > 255) {
        $response['errors']['address'] = 'Address cannot exceed 255 characters.';
    }

    if (strlen($country) > 100) {
        $response['errors']['country'] = 'Country cannot exceed 100 characters.';
    }

    // --- Action-specific logic ---
    if ($action === 'add') {
        // Password validation for new account creation
        if (empty($password)) {
            $response['errors']['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $response['errors']['password'] = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirm_password) {
            $response['errors']['confirm_password'] = 'Passwords do not match.';
        } elseif (strlen($password) > 255) {
            $response['errors']['password'] = 'Password cannot exceed 255 characters.';
        }

        // Proceed if no validation errors for 'add'
        if (empty($response['errors'])) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (client_name, email, password, contact_number, client_address, location) VALUES (:full_name, :email, :password, :contact_number, :client_address, :location)");
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':contact_number', $mobile_number);
            $stmt->bindParam(':client_address', $address);
            $stmt->bindParam(':location', $country); // Assuming 'location' field stores country/city
            $stmt->execute();

            $response['success'] = true;
            $response['message'] = 'Account created successfully!';
            $_SESSION['success_message'] = $response['message']; // Set flash message
        } else {
            $response['message'] = 'Validation errors occurred.';
            $_SESSION['errors'] = $response['errors']; // Set flash errors
        }

    } elseif ($action === 'update') {
        $user_id = intval($_POST['user_id'] ?? 0); // Get user ID from POST data
        if ($user_id <= 0) {
            $response['errors']['general'] = 'Invalid user ID for update.';
        }

        // Only update password if new password fields are provided
        if (!empty($password) || !empty($confirm_password)) {
            if (empty($password)) {
                $response['errors']['password'] = 'Password is required to change password.';
            } elseif (strlen($password) < 8) {
                $response['errors']['password'] = 'Password must be at least 8 characters long.';
            } elseif ($password !== $confirm_password) {
                $response['errors']['confirm_password'] = 'Passwords do not match.';
            } elseif (strlen($password) > 255) {
                $response['errors']['password'] = 'Password cannot exceed 255 characters.';
            }
        }

        // Proceed if no validation errors for 'update'
        if (empty($response['errors'])) {
            $update_fields = [];
            $bind_params = [
                ':full_name' => $full_name,
                ':email' => $email,
                ':contact_number' => $mobile_number,
                ':client_address' => $address,
                ':location' => $country,
                ':user_id' => $user_id
            ];

            $update_fields[] = 'client_name = :full_name';
            $update_fields[] = 'email = :email';
            $update_fields[] = 'contact_number = :contact_number';
            $update_fields[] = 'client_address = :client_address';
            $update_fields[] = 'location = :location';

            if (!empty($password) && empty($response['errors']['password'])) { // Only include password if valid and provided
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_fields[] = 'password = :password';
                $bind_params[':password'] = $hashed_password;
            }

            $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = :user_id");
            $stmt->execute($bind_params);

            $response['success'] = true;
            $response['message'] = 'Profile updated successfully!';
            $_SESSION['success_message'] = $response['message']; // Set flash message
        } else {
            $response['message'] = 'Validation errors occurred.';
            $_SESSION['errors'] = $response['errors']; // Set flash errors
        }

    } else {
        $response['message'] = 'Invalid action specified.';
        $response['errors']['general'] = 'Invalid action.';
    }

} catch (PDOException $e) {
    error_log("Database Error in manage_user_profile.php: " . $e->getMessage());
    $response['message'] = 'Database error: ' . $e->getMessage();
    $response['errors']['general'] = 'A database error occurred. Please try again later.';
} catch (Exception $e) {
    error_log("General Error in manage_user_profile.php: " . $e->getMessage());
    $response['message'] = 'Server error: ' . $e->getMessage();
    $response['errors']['general'] = 'An unexpected server error occurred.';
}

echo json_encode($response);
exit();
