<?php
function handle_error($message, $is_warning = false) {
    $style = 'color:red;border:1px solid red;background-color:#ffe0e0;';
    if ($is_warning) {
        $style = 'color: #856404; background-color: #fff3cd; border-color: #ffeeba;';
        echo "<div style='padding:15px; margin-bottom: 15px; border-radius: 4px; {$style}'>" . htmlspecialchars($message) . "</div>";
        return; // Don't die for warnings
    }
    // Log critical errors
    error_log("Error: " . $message);
    die("<div style='padding:15px; border-radius: 4px; {$style}'>" . htmlspecialchars($message) . "</div>");
}

function fetch_data($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database Fetch Error: " . $e->getMessage() . " Query: " . $query);
        return false; // Indicate failure
    }
}
?>