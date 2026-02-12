<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['rental_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Rental ID is required'
    ]);
    exit;
}

$rental_id = intval($input['rental_id']);
$user_id = $_SESSION['user_id'];

$conn = getDBConnection();
$conn->begin_transaction();

try {
    // Verify rental belongs to user
    $stmt = $conn->prepare("SELECT vehicle_id, status FROM rentals WHERE rental_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $rental_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Rental not found or unauthorized');
    }
    
    $rental = $result->fetch_assoc();
    $vehicle_id = $rental['vehicle_id'];
    $rental_status = $rental['status'];
    $stmt->close();
    
    // IMPORTANT: Cannot cancel if already approved by admin
    if ($rental_status === 'approved' || $rental_status === 'active') {
        throw new Exception('Cannot cancel: Booking has been approved by admin. Please contact support for assistance.');
    }
    
    // Can only cancel pending or confirmed bookings
    if (!in_array($rental_status, ['pending', 'confirmed'])) {
        throw new Exception('This booking cannot be cancelled (Status: ' . $rental_status . ')');
    }
    
    // Update rental status to cancelled
    $updateRental = $conn->prepare("UPDATE rentals SET status = 'cancelled' WHERE rental_id = ?");
    $updateRental->bind_param("i", $rental_id);
    $updateRental->execute();
    $updateRental->close();
    
    // Set vehicle back to available
    $updateVehicle = $conn->prepare("UPDATE vehicles SET status = 'available' WHERE vehicle_id = ?");
    $updateVehicle->bind_param("i", $vehicle_id);
    $updateVehicle->execute();
    $updateVehicle->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Booking cancelled successfully'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
