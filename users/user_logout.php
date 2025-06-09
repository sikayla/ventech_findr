<?php
session_start();

$redirect_to_dashboard = "/ventech_locator/users/user_dashboard.php";
$redirect_to_login = "/ventech_locator/index.php"; // Main index/login page

// Check if a confirmation to logout has been received
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_logout']) && $_POST['confirm_logout'] === 'true') {
    // Destroy the session only if confirmed
    session_unset();
    session_destroy();
    // Redirect to the main login/index page after logout
    header("Location: " . $redirect_to_login);
    exit;
}

// If not a POST request with confirmation, or if confirmation is false,
// display the confirmation modal.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Logout</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5; /* Light grey background */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; /* Ensure it takes full viewport height */
            margin: 0;
            padding: 0;
        }

        /* Modal Overlay */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5); /* Semi-transparent black */
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0; /* Start hidden */
            visibility: hidden; /* Start hidden */
            transition: opacity 0.3s ease-out, visibility 0.3s ease-out;
        }

        .modal-overlay.visible {
            opacity: 1;
            visibility: visible;
        }

        /* Modal Content */
        .modal-content {
            background-color: white;
            padding: 24px;
            border-radius: 8px; /* Slightly rounded corners */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            text-align: center;
            max-width: 400px; /* Limit width */
            width: 90%; /* Responsive width */
            transform: scale(0.9); /* Start slightly smaller for animation */
            transition: transform 0.3s ease-out;
        }

        .modal-overlay.visible .modal-content {
            transform: scale(1); /* Scale to normal size when visible */
        }

        .modal-header {
            font-size: 1.25rem; /* text-xl */
            font-weight: 600; /* font-semibold */
            margin-bottom: 16px;
            color: #1f2937; /* gray-900 */
        }

        .modal-body {
            font-size: 1rem; /* text-base */
            color: #4b5563; /* gray-700 */
            margin-bottom: 24px;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end; /* Align buttons to the right */
            gap: 12px; /* Space between buttons */
        }

        .modal-button {
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 500; /* medium */
            cursor: pointer;
            transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
        }

        .modal-button.cancel {
            background-color: #e5e7eb; /* gray-200 */
            color: #374151; /* gray-700 */
            border: 1px solid #d1d5db; /* gray-300 */
        }
        .modal-button.cancel:hover {
            background-color: #d1d5db; /* gray-300 */
        }

        .modal-button.ok {
            background-color: #ef4444; /* red-500 */
            color: white;
            border: 1px solid #ef4444; /* red-500 */
        }
        .modal-button.ok:hover {
            background-color: #dc2626; /* red-600 */
        }
    </style>
</head>
<body>

    <div id="logoutConfirmationModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">Confirm Logout</div>
            <div class="modal-body">Are you sure you want to log out?</div>
            <div class="modal-actions">
                <button id="cancelLogout" class="modal-button cancel">CANCEL</button>
                <button id="confirmLogout" class="modal-button ok">OK</button>
            </div>
        </div>
    </div>

    <form id="logoutForm" method="POST" action="user_logout.php" style="display: none;">
        <input type="hidden" name="confirm_logout" value="true">
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const logoutConfirmationModal = document.getElementById('logoutConfirmationModal');
            const cancelLogoutButton = document.getElementById('cancelLogout');
            const confirmLogoutButton = document.getElementById('confirmLogout');
            const logoutForm = document.getElementById('logoutForm');

            // Show the modal when the page loads
            logoutConfirmationModal.classList.add('visible');

            // Handle Cancel button click
            cancelLogoutButton.addEventListener('click', function() {
                logoutConfirmationModal.classList.remove('visible');
                // Redirect back to the dashboard if user cancels
                window.location.href = '<?php echo $redirect_to_dashboard; ?>';
            });

            // Handle OK button click
            confirmLogoutButton.addEventListener('click', function() {
                // Submit the hidden form to trigger actual logout in PHP
                logoutForm.submit();
            });

            // Optional: Close modal if clicking outside (if you decide to make it clickable outside)
            // logoutConfirmationModal.addEventListener('click', function(event) {
            //     if (event.target === logoutConfirmationModal) {
            //         logoutConfirmationModal.classList.remove('visible');
            //         window.location.href = '<?php echo $redirect_to_dashboard; ?>';
            //     }
            // });
        });
    </script>

</body>
</html>
