<?php
require 'db_connection.php';

$venue_id = $_GET['venue_id'] ?? null;

if (!$venue_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing venue_id']);
    exit;
}

$stmt = $pdo->prepare("SELECT date FROM venue_availability WHERE venue_id = ? AND available = 0");
$stmt->execute([$venue_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$events = array_map(function ($row) {
    return [
        'start' => $row['date'],
        'allDay' => true,
        'title' => 'Unavailable'
    ];
}, $rows);

header('Content-Type: application/json');
echo json_encode($events);
