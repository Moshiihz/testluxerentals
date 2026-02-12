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

if (empty($input['rental_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Rental ID is required'
    ]);
    exit;
}

$rental_id = intval($input['rental_id']);

$conn = getDBConnection();
$conn->begin_transaction();

try {
    // Get rental details before deleting
    $stmt = $conn->prepare("SELECT vehicle_id, status FROM rentals WHERE rental_id = ?");
    $stmt->bind_param("i", $rental_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Rental not found');
    }
    
    $rental = $result->fetch_assoc();
    $vehicle_id = $rental['vehicle_id'];
    $stmt->close();
    
    // Delete related payments first
    $deletePayments = $conn->prepare("DELETE FROM payments WHERE rental_id = ?");
    $deletePayments->bind_param("i", $rental_id);
    $deletePayments->execute();
    $deletePayments->close();
    
    // Delete the rental
    $deleteRental = $conn->prepare("DELETE FROM rentals WHERE rental_id = ?");
    $deleteRental->bind_param("i", $rental_id);
    $deleteRental->execute();
    $deleteRental->close();
    
    // If rental was active/confirmed, set vehicle back to available
    if (in_array($rental['status'], ['pending', 'confirmed', 'active'])) {
        $updateVehicle = $conn->prepare("UPDATE vehicles SET status = 'available' WHERE vehicle_id = ?");
        $updateVehicle->bind_param("i", $vehicle_id);
        $updateVehicle->execute();
        $updateVehicle->close();
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Booking deleted successfully'
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
