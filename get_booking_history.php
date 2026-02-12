<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode([
        'success' => false,
        'message' => 'Not logged in',
        'rentals' => []
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get all rentals for this user (ordered by most recent first)
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
        v.brand as vehicleBrand,
        v.model as vehicleModel,
        v.category,
        u.full_name as userName
    FROM rentals r
    JOIN vehicles v ON r.vehicle_id = v.vehicle_id
    JOIN users u ON r.user_id = u.user_id
    WHERE r.user_id = ?
    ORDER BY r.rental_id DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$rentals = [];
while ($row = $result->fetch_assoc()) {
    $rentals[] = [
        'rentalId' => intval($row['rentalId']),
        'userId' => intval($row['userId']),
        'vehicleId' => intval($row['vehicleId']),
        'vehicleBrand' => $row['vehicleBrand'],
        'vehicleModel' => $row['vehicleModel'],
        'userName' => $row['userName'],
        'startDate' => $row['startDate'],
        'endDate' => $row['endDate'],
        'pickupLocation' => $row['pickupLocation'],
        'dropoffLocation' => $row['dropoffLocation'],
        'totalDays' => intval($row['totalDays']),
        'dailyRate' => floatval($row['dailyRate']),
        'totalAmount' => floatval($row['totalAmount']),
        'depositAmount' => floatval($row['depositAmount']),
        'status' => $row['status']
    ];
}

echo json_encode([
    'success' => true,
    'message' => 'Booking history retrieved',
    'rentals' => $rentals
]);

$stmt->close();
$conn->close();
?>
