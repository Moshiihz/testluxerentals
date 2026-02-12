<?php
// NO WHITESPACE BEFORE THIS LINE!
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

ob_start();

try {
    require_once 'config.php';
    
    // Get JSON input
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    
    // Log the received data for debugging
    error_log("Received booking data: " . print_r($data, true));
    
    // Validate ALL required fields
    $required = ['user_id', 'vehicle_id', 'start_date', 'end_date', 'pickup_location', 'dropoff_location'];
    $missing = [];
    
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Missing required field: ' . implode(', ', $missing)
        ]);
        exit;
    }
    
    $user_id = intval($data['user_id']);
    $vehicle_id = intval($data['vehicle_id']);
    $start_date = trim($data['start_date']);
    $end_date = trim($data['end_date']);
    $pickup_location = trim($data['pickup_location']);
    $dropoff_location = trim($data['dropoff_location']);
    $payment_method = isset($data['payment_method']) ? trim($data['payment_method']) : 'cash';
    
    // Validate IDs
    if ($user_id <= 0) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Invalid user ID'
        ]);
        exit;
    }
    
    if ($vehicle_id <= 0) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Invalid vehicle ID'
        ]);
        exit;
    }
    
    // Get vehicle details
    $vehicle_sql = "SELECT brand, model, daily_rate, status FROM vehicles WHERE vehicle_id = ?";
    $vehicle_stmt = $conn->prepare($vehicle_sql);
    
    if (!$vehicle_stmt) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Database error'
        ]);
        exit;
    }
    
    $vehicle_stmt->bind_param("i", $vehicle_id);
    $vehicle_stmt->execute();
    $vehicle_result = $vehicle_stmt->get_result();
    
    if ($vehicle_result->num_rows === 0) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Vehicle not found'
        ]);
        $vehicle_stmt->close();
        $conn->close();
        exit;
    }
    
    $vehicle = $vehicle_result->fetch_assoc();
    
    if ($vehicle['status'] !== 'available') {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Vehicle is not available'
        ]);
        $vehicle_stmt->close();
        $conn->close();
        exit;
    }
    
    $daily_rate = floatval($vehicle['daily_rate']);
    $vehicle_stmt->close();
    
    // Calculate days and amounts
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $total_days = $interval->days + 1;
    $total_amount = $total_days * $daily_rate;
    $deposit_amount = $total_amount * 0.30;
    
    // Insert rental
    $insert_sql = "INSERT INTO rentals (user_id, vehicle_id, start_date, end_date, pickup_location, dropoff_location, total_days, daily_rate, total_amount, deposit_amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    $insert_stmt = $conn->prepare($insert_sql);
    
    if (!$insert_stmt) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Database error'
        ]);
        exit;
    }
    
    $insert_stmt->bind_param(
        "iissssiddd",
        $user_id,
        $vehicle_id,
        $start_date,
        $end_date,
        $pickup_location,
        $dropoff_location,
        $total_days,
        $daily_rate,
        $total_amount,
        $deposit_amount
    );
    
    if ($insert_stmt->execute()) {
        $rental_id = $insert_stmt->insert_id;
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Booking created successfully',
            'data' => [
                'rentalId' => $rental_id,
                'vehicleName' => $vehicle['brand'] . ' ' . $vehicle['model'],
                'startDate' => $start_date,
                'endDate' => $end_date,
                'totalDays' => $total_days,
                'totalAmount' => $total_amount
            ]
        ]);
    } else {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create booking'
        ]);
    }
    
    $insert_stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

ob_end_flush();
?>
