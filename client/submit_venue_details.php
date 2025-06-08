<?php
include('db_connection.php');

// Get form data
$venue_id = $_POST['venue_id'];
$amenities = $_POST['amenities'];
$reviews = $_POST['reviews'];
$additional_info = $_POST['additional_info'];
$availability_dates = $_POST['availability_dates']; // Unavailable dates

// Process image upload
$image_url = '';
if (isset($_FILES['venue_image']) && $_FILES['venue_image']['error'] == 0) {
    $image_name = $_FILES['venue_image']['name'];
    $image_tmp_name = $_FILES['venue_image']['tmp_name'];
    $image_extension = pathinfo($image_name, PATHINFO_EXTENSION);
    $image_new_name = uniqid() . '.' . $image_extension;
    $image_destination = 'uploads/' . $image_new_name;

    if (move_uploaded_file($image_tmp_name, $image_destination)) {
        $image_url = $image_destination;
    }
}

// Update venue details
$sql = "UPDATE venue SET amenities = ?, reviews = ?, additional_info = ?, image_path = ? WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ssssi', $amenities, $reviews, $additional_info, $image_url, $venue_id);
mysqli_stmt_execute($stmt);

// Insert new image into venue_images table if a new image is uploaded
if (!empty($image_url)) {
    $image_sql = "INSERT INTO venue_images (venue_id, image_path) VALUES (?, ?)";
    $image_stmt = mysqli_prepare($conn, $image_sql);
    mysqli_stmt_bind_param($image_stmt, 'is', $venue_id, $image_url);
    mysqli_stmt_execute($image_stmt);
}

// Update unavailable dates
if (!empty($availability_dates)) {
    // Delete existing unavailable dates for the venue
    $delete_sql = "DELETE FROM unavailable_dates WHERE venue_id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($delete_stmt, 'i', $venue_id);
    mysqli_stmt_execute($delete_stmt);

    // Insert new unavailable dates
    foreach ($availability_dates as $date) {
        $availability_sql = "INSERT INTO unavailable_dates (venue_id, date) VALUES (?, ?)";
        $availability_stmt = mysqli_prepare($conn, $availability_sql);
        mysqli_stmt_bind_param($availability_stmt, 'is', $venue_id, $date);
        mysqli_stmt_execute($availability_stmt);
    }
}

// Redirect to the same page with a success message
header("Location: venue_details.php?id=$venue_id&message=Venue details saved successfully");
exit;
?>
