<?php
// NO WHITESPACE BEFORE THIS LINE!
// Disable all error output to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);

// Set headers FIRST
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Start output buffering to catch any accidental output
ob_start();

try {
    require_once 'config.php';
    
    // Get vehicle ID
    $vehicle_id = isset($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : 0;
    
    if ($vehicle_id <= 0) {
        // Clear any buffered output
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Invalid vehicle ID'
        ]);
        exit;
    }
    
    // Check for active rentals
    $check_sql = "SELECT COUNT(*) as count FROM rentals WHERE vehicle_id = ? AND status IN ('pending', 'confirmed', 'active')";
    $check_stmt = $conn->prepare($check_sql);
    
    if (!$check_stmt) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Database error'
        ]);
        exit;
    }
    
    $check_stmt->bind_param("i", $vehicle_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_row = $check_result->fetch_assoc();
    
    if ($check_row['count'] > 0) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Cannot delete vehicle with active rentals'
        ]);
        $check_stmt->close();
        $conn->close();
        exit;
    }
    
    $check_stmt->close();
    
    // Delete the vehicle
    $delete_sql = "DELETE FROM vehicles WHERE vehicle_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    
    if (!$delete_stmt) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Database error'
        ]);
        exit;
    }
    
    $delete_stmt->bind_param("i", $vehicle_id);
    
    if ($delete_stmt->execute()) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Vehicle deleted successfully'
        ]);
    } else {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete vehicle'
        ]);
    }
    
    $delete_stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}

// Clean the buffer and end
ob_end_flush();
?>
