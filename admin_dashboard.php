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

$conn = getDBConnection();

try {
    // Get all rentals with user and vehicle details
    $stmt = $conn->prepare("
        SELECT 
            r.rental_id,
            r.user_id,
            r.vehicle_id,
            r.start_date,
            r.end_date,
            r.pickup_location,
            r.dropoff_location,
            r.total_days,
            r.daily_rate,
            r.total_amount,
            r.deposit_amount,
            r.status,
            r.created_at,
            u.full_name as user_name,
            v.brand as vehicle_brand,
            v.model as vehicle_model
        FROM rentals r
        JOIN users u ON r.user_id = u.user_id
        JOIN vehicles v ON r.vehicle_id = v.vehicle_id
        ORDER BY r.rental_id DESC
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rentals = [];
    while ($row = $result->fetch_assoc()) {
        $rentals[] = [
            'rentalId' => intval($row['rental_id']),
            'userId' => intval($row['user_id']),
            'vehicleId' => intval($row['vehicle_id']),
            'vehicleBrand' => $row['vehicle_brand'],
            'vehicleModel' => $row['vehicle_model'],
            'userName' => $row['user_name'],
            'startDate' => $row['start_date'],
            'endDate' => $row['end_date'],
            'pickupLocation' => $row['pickup_location'],
            'dropoffLocation' => $row['dropoff_location'],
            'totalDays' => intval($row['total_days']),
            'dailyRate' => floatval($row['daily_rate']),
            'totalAmount' => floatval($row['total_amount']),
            'depositAmount' => floatval($row['deposit_amount']),
            'status' => $row['status']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Rentals retrieved successfully',
        'rentals' => $rentals
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
