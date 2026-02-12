<?php
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['rental_id']) || empty($input['action'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Rental ID and action are required'
    ]);
    exit;
}

$rental_id = intval($input['rental_id']);
$action = $input['action']; // 'approve' or 'deny'

if (!in_array($action, ['approve', 'deny'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);
    exit;
}

$conn = getDBConnection();
$conn->begin_transaction();

try {
    // Get rental details
    $stmt = $conn->prepare("SELECT vehicle_id, status FROM rentals WHERE rental_id = ?");
    $stmt->bind_param("i", $rental_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Rental not found');
    }
    
    $rental = $result->fetch_assoc();
    $vehicle_id = $rental['vehicle_id'];
    $current_status = $rental['status'];
    $stmt->close();
    
    // Only pending rentals can be approved/denied
    if ($current_status !== 'pending' && $current_status !== 'confirmed') {
        throw new Exception('Only pending bookings can be approved or denied');
    }
    
    if ($action === 'approve') {
        // Update rental status to approved
        $updateRental = $conn->prepare("UPDATE rentals SET status = 'approved' WHERE rental_id = ?");
        $updateRental->bind_param("i", $rental_id);
        $updateRental->execute();
        $updateRental->close();
        
        $message = 'Booking approved successfully';
    } else {
        // Deny - update rental status and free the vehicle
        $updateRental = $conn->prepare("UPDATE rentals SET status = 'denied' WHERE rental_id = ?");
        $updateRental->bind_param("i", $rental_id);
        $updateRental->execute();
        $updateRental->close();
        
        // Set vehicle back to available
        $updateVehicle = $conn->prepare("UPDATE vehicles SET status = 'available' WHERE vehicle_id = ?");
        $updateVehicle->bind_param("i", $vehicle_id);
        $updateVehicle->execute();
        $updateVehicle->close();
        
        $message = 'Booking denied and vehicle released';
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
