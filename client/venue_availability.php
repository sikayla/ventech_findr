<?php
// venue_availability.php

require 'db_connection.php'; // make sure this connects to your ventech_db

$venue_id = $_GET['venue_id'] ?? null;

if (!$venue_id) {
    die("Venue ID not provided.");
}

// Fetch venue info
$stmt = $pdo->prepare("SELECT * FROM venue WHERE id = ?");
$stmt->execute([$venue_id]);
$venue = $stmt->fetch();

if (!$venue) {
    die("Venue not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Venue Availability - <?= htmlspecialchars($venue['venue_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        h2 { margin-bottom: 10px; }
        #calendar { max-width: 900px; margin: 0 auto; }
    </style>
</head>
<body>

<h2>Manage Availability for: <?= htmlspecialchars($venue['venue_name']) ?></h2>
<div id="calendar"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    const venueId = <?= (int)$venue_id ?>;

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        selectable: true,
        events: `/get_availability.php?venue_id=${venueId}`,
        dateClick: function (info) {
            const date = info.dateStr;
            // Toggle availability on click
            axios.post('/toggle_availability.php', {
                venue_id: venueId,
                date: date
            }).then(() => {
                calendar.refetchEvents(); // refresh calendar after change
            });
        },
        eventColor: '#ff4d4d',
        eventTextColor: 'white',
        eventDisplay: 'background'
    });

    calendar.render();
});
</script>

</body>
</html>
