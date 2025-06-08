<?php
require_once 'includes/db_connection.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$reservation_id = $_POST['reservation_id'] ?? null;
$user_id = $_SESSION['user_id'];

if ($reservation_id) {
    // Only allow cancel if status is exactly 'pending'
    $stmt = $pdo->prepare("SELECT * FROM venue_reservations WHERE id = :id AND user_id = :user_id AND status = 'pending'");
    $stmt->execute([':id' => $reservation_id, ':user_id' => $user_id]);
    $reservation = $stmt->fetch();

    if ($reservation) {
        // Update status to cancelled
        $update = $pdo->prepare("UPDATE venue_reservations SET status = 'cancelled' WHERE id = :id");
        $update->execute([':id' => $reservation_id]);
    }
}

header('Location: user_reservation_manage.php');
exit;
