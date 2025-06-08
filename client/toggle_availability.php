<?php
require 'db_connection.php';

$data = json_decode(file_get_contents("php://input"), true);
$venue_id = $data['venue_id'] ?? null;
$date = $data['date'] ?? null;

if (!$venue_id || !$date) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing venue_id or date']);
    exit;
}

// Check if date already marked
$stmt = $pdo->prepare("SELECT * FROM venue_availability WHERE venue_id = ? AND date = ?");
$stmt->execute([$venue_id, $date]);
$existing = $stmt->fetch();

if ($existing) {
    // Toggle availability
    $newAvailability = $existing['available'] ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE venue_availability SET available = ? WHERE id = ?");
    $stmt->execute([$newAvailability, $existing['id']]);
} else {
    // Insert new unavailable date
    $stmt = $pdo->prepare("INSERT INTO venue_availability (venue_id, date, available) VALUES (?, ?, 0)");
    $stmt->execute([$venue_id, $date]);
}

echo json_encode(['success' => true]);
