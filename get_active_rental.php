<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode([
        'success' => false,
        'hasRental' => false,
        'message' => 'Not logged in'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get most recent rental for this user
$stmt = $conn->prepare("
    SELECT 
        r.rental_id as rentalId,
        r.user_id as userId,
        r.vehicle_id as vehicleId,
        r.start_date as startDate,
        r.end_date as endDate,
        r.total_days as totalDays,
        r.total_amount as totalAmount,
        r.deposit_amount as depositAmount,
        r.status,
        r.pickup_location as pickupLocation,
        r.dropoff_location as dropoffLocation,
        r.daily_rate as dailyRate,
        r.created_at,
        r.updated_at,
        v.brand as vehicleBrand,
        v.model as vehicleModel,
        v.category,
        v.image_url as imageUrl,
        u.full_name as userName
    FROM rentals r
    JOIN vehicles v ON r.vehicle_id = v.vehicle_id
    JOIN users u ON r.user_id = u.user_id
    WHERE r.user_id = ?
    ORDER BY r.rental_id DESC
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $rental = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'hasRental' => true,
        'rental' => [
            'rentalId' => intval($rental['rentalId']),
            'userId' => intval($rental['userId']),
            'vehicleId' => intval($rental['vehicleId']),
            'vehicleBrand' => $rental['vehicleBrand'],
            'vehicleModel' => $rental['vehicleModel'],
            'userName' => $rental['userName'],
            'startDate' => $rental['startDate'],
            'endDate' => $rental['endDate'],
            'pickupLocation' => $rental['pickupLocation'],
            'dropoffLocation' => $rental['dropoffLocation'],
            'totalDays' => intval($rental['totalDays']),
            'dailyRate' => floatval($rental['dailyRate']),
            'totalAmount' => floatval($rental['totalAmount']),
            'depositAmount' => floatval($rental['depositAmount']),
            'status' => $rental['status']
        ]
    ]);
} else {
    echo json_encode([
        'success' => true,
        'hasRental' => false,
        'message' => 'No active rental found'
    ]);
}

$stmt->close();
$conn->close();
?>
