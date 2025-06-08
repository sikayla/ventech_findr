<?php
// add_user.php
// This file handles adding a new user from the admin panel.

session_start();
require_once 'config.php';

// Check if the user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'guest'; // Default role to guest

    // Basic validation
    if (empty($username) || empty($email) || empty($password)) {
        $_SESSION['message'] = "Please fill in all required fields.";
        $_SESSION['message_type'] = "error";
        header('Location: dashboard.php');
        exit();
    }

    // Hash the password before storing (IMPORTANT for security)
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Prepare an INSERT statement
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);

    if ($stmt->execute()) {
        $_SESSION['message'] = "User added successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        // Check for duplicate entry error (e.g., unique username/email)
        if ($conn->errno == 1062) { // MySQL error code for duplicate entry
            $_SESSION['message'] = "Error: Username or Email already exists.";
        } else {
            $_SESSION['message'] = "Error adding user: " . $stmt->error;
        }
        $_SESSION['message_type'] = "error";
    }
    $stmt->close();
    $conn->close();
    header('Location: dashboard.php');
    exit();
} else {
    // If accessed directly without POST, redirect to dashboard
    header('Location: dashboard.php');
    exit();
}
?>
