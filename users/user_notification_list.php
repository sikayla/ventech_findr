<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Notifications - Ventech Locator</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {  font-family : 'Roboto', sans-serif; }
        /* Add any additional custom styles here */
         /* Message Alert Styles */
        .message-alert {
             padding : 1rem;
             margin-bottom : 1.5rem;
             border-radius : 0.375rem;
             border-left-width : 4px;
             box-shadow : 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
        }
        /* Style for unread notification */
        .notification-item.unread {
            background-color: #fffbeb; /* Tailwind yellow-50 */
            border-left: 4px solid #f59e0b; /* Tailwind orange-500 */
        }
         .notification-item.read {
             background-color: #ffffff; /* White */
             border-left: 4px solid #d1d5db; /* Tailwind gray-300 */
         }
         .notification-item {
             transition: background-color 0.2s ease-in-out;
         }
         .notification-item:hover {
             background-color: #f9fafb; /* Tailwind gray-50 */
         }
         .notification-badge {
             position: absolute;
             top: -8px;
             right: -8px;
             padding: 2px 6px;
             border-radius: 9999px; /* Full rounded */
             font-size: 0.75rem; /* text-xs */
             font-weight: 700; /* font-bold */
             color: white;
             background-color: #ef4444; /* Tailwind red-500 */
             min-width: 20px;
             text-align: center;
             line-height: 1;
         }
    </style>
</head>
<body class="bg-gray-100 p-4 md:p-6 lg:p-8">
    <div class="max-w-3xl mx-auto">
        <header class="bg-white shadow-md rounded-lg p-6 mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-1">My Notifications</h1>
                <p class="text-gray-600 text-sm">Updates about your reservations and account.</p>
            </div>
             <a href="client_dashboard.php" class="bg-orange-500 hover:bg-orange-600 text-white py-2 px-4 rounded text-sm font-medium transition duration-150 ease-in-out shadow-sm">
                 <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
             </a>
        </header>

        <div class="flex justify-end mb-4">
             <form method="POST" action="#" class="inline-block">
                 <input type="hidden" name="action" value="mark_all_read">
                 <input type="hidden" name="csrf_token" value="[csrf_token]">
                 <button type="submit" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                     <i class="fas fa-check-double mr-1"></i> Mark All As Read
                 </button>
             </form>
        </div>


        <div class="space-y-4">
            <div class="notification-item unread p-4 rounded-lg shadow-sm relative">
                 <i class="fas fa-check-circle text-green-500 text-lg absolute top-4 left-4"></i>
                 <span class="notification-badge"><i class="fas fa-bell"></i></span>
                <div class="ml-8">
                    <p class="text-sm font-semibold text-gray-800 mb-1">
                        Your reservation has been accepted!
                    </p>
                    <p class="text-xs text-gray-600 mb-2">
                        For: <span class="font-medium">Public Court</span> on May 06, 2025
                    </p>
                    <p class="text-xs text-gray-500">
                        <i class="fas fa-clock mr-1"></i> Received: May 01, 2025 22:46
                    </p>
                     <a href="/ventech_locator/user_reservation_manage.php?id=123" class="mt-2 inline-block text-xs text-blue-600 hover:text-blue-800 font-medium">
                         View Details &rarr;
                     </a>
                </div>
                 <form method="POST" action="#" class="absolute top-4 right-4">
                     <input type="hidden" name="action" value="mark_read">
                     <input type="hidden" name="notification_id" value="1">
                     <input type="hidden" name="csrf_token" value="[csrf_token]">
                     <button type="submit" class="text-gray-400 hover:text-gray-600 text-sm" title="Mark as Read">
                         <i class="fas fa-eye"></i>
                     </button>
                 </form>
            </div>

            <div class="notification-item unread p-4 rounded-lg shadow-sm relative">
                 <i class="fas fa-times-circle text-red-500 text-lg absolute top-4 left-4"></i>
                 <span class="notification-badge"><i class="fas fa-bell"></i></span>
                <div class="ml-8">
                    <p class="text-sm font-semibold text-gray-800 mb-1">
                        Your reservation request was rejected.
                    </p>
                    <p class="text-xs text-gray-600 mb-2">
                        For: <span class="font-medium">Conference Room A</span> on May 10, 2025
                    </p>
                    <p class="text-xs text-gray-500">
                        <i class="fas fa-clock mr-1"></i> Received: May 01, 2025 23:00
                    </p>
                     <a href="/ventech_locator/user_reservation_manage.php?id=124" class="mt-2 inline-block text-xs text-blue-600 hover:text-blue-800 font-medium">
                         View Details &rarr;
                     </a>
                </div>
                 <form method="POST" action="#" class="absolute top-4 right-4">
                     <input type="hidden" name="action" value="mark_read">
                     <input type="hidden" name="notification_id" value="2">
                     <input type="hidden" name="csrf_token" value="[csrf_token]">
                     <button type="submit" class="text-gray-400 hover:text-gray-600 text-sm" title="Mark as Read">
                         <i class="fas fa-eye"></i>
                     </button>
                 </form>
            </div>

            <div class="notification-item read p-4 rounded-lg shadow-sm relative">
                 <i class="fas fa-info-circle text-blue-500 text-lg absolute top-4 left-4"></i>
                 <div class="ml-8">
                    <p class="text-sm font-semibold text-gray-800 mb-1">
                        Welcome to Ventech Locator!
                    </p>
                    <p class="text-xs text-gray-500">
                        <i class="fas fa-clock mr-1"></i> Received: April 30, 2025 10:00
                    </p>
                     <a href="#" class="mt-2 inline-block text-xs text-blue-600 hover:text-blue-800 font-medium">
                         Learn More &rarr;
                     </a>
                </div>
                 </div>

             </div>
    </div>
</body>
</html>


