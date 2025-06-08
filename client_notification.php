<?php
// **1. Start Session**
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// **2. Include Database Connection**
require_once 'includes/db_connection.php'; // Adjust path as necessary

// Ensure PDO connection is available
if (!isset($pdo) || !$pdo instanceof PDO) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// **3. Check User Authentication**
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit;
}
$loggedInOwnerUserId = $_SESSION['user_id'];

// Get action from GET request
$action = $_GET['action'] ?? '';

$response = ['success' => false, 'message' => 'Invalid action.'];

switch ($action) {
    case 'count_pending_reservations':
        // Fetch venue IDs owned by the logged-in user
        $venue_ids_owned = [];
        try {
            $stmtVenueIds = $pdo->prepare("SELECT id FROM venue WHERE user_id = ?");
            $stmtVenueIds->execute([$loggedInOwnerUserId]);
            $venue_ids_owned = $stmtVenueIds->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($venue_ids_owned)) {
                $in_placeholders = implode(',', array_fill(0, count($venue_ids_owned), '?'));
                $stmtPendingBookings = $pdo->prepare("SELECT COUNT(*) FROM venue_reservations WHERE venue_id IN ($in_placeholders) AND status = 'pending'");
                $stmtPendingBookings->execute($venue_ids_owned);
                $pending_count = $stmtPendingBookings->fetchColumn();
                $response = ['success' => true, 'pending_count' => (int)$pending_count];
            } else {
                $response = ['success' => true, 'pending_count' => 0];
            }
        } catch (PDOException $e) {
            error_log("Error fetching pending reservation count: " . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error fetching count.'];
        }
        break;

    case 'get_pending_reservations':
        // Fetch detailed pending reservations for the owner's venues
        $pending_reservations = [];
        try {
            $stmtVenueIds = $pdo->prepare("SELECT id FROM venue WHERE user_id = ?");
            $stmtVenueIds->execute([$loggedInOwnerUserId]);
            $venue_ids_owned = $stmtVenueIds->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($venue_ids_owned)) {
                $in_placeholders = implode(',', array_fill(0, count($venue_ids_owned), '?'));
                $sql = "SELECT
                            r.id, r.event_date, r.start_time, r.end_time, r.status, r.created_at,
                            v.title AS venue_title, v.image_path,
                            u.username AS booker_username, u.email AS booker_email
                        FROM venue_reservations r
                        JOIN venue v ON r.venue_id = v.id
                        LEFT JOIN users u ON r.user_id = u.id
                        WHERE r.venue_id IN ($in_placeholders) AND r.status = 'pending'
                        ORDER BY r.created_at DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($venue_ids_owned);
                $pending_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response = ['success' => true, 'reservations' => $pending_reservations];
            } else {
                $response = ['success' => true, 'reservations' => []];
            }
        } catch (PDOException $e) {
            error_log("Error fetching pending reservations: " . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error fetching reservations.'];
        }
        break;

    // You can add more actions here, e.g., 'mark_as_read', 'get_all_notifications'
    // For now, focusing on new reservations for the owner.

    default:
        $response = ['success' => false, 'message' => 'Unknown action.'];
        break;
}

echo json_encode($response);
exit;
?>
