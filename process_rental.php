<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Please login.'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request data'
    ]);
    exit;
}

function validateRentalData($data) {
    $errors = [];
    
    if (empty($data['vehicle_id']) || !is_numeric($data['vehicle_id'])) {
        $errors[] = 'Invalid vehicle ID';
    }
    
    if (empty($data['start_date']) || empty($data['end_date'])) {
        $errors[] = 'Start date and end date are required';
    }
    
    $startDate = strtotime($data['start_date']);
    $endDate = strtotime($data['end_date']);
    $today = strtotime(date('Y-m-d'));
    
    if ($startDate === false || $endDate === false) {
        $errors[] = 'Invalid date format';
    } elseif ($startDate < $today) {
        $errors[] = 'Start date cannot be in the past';
    } elseif ($endDate < $startDate) {
        $errors[] = 'End date must be after start date';
    }
    
    if (empty($data['pickup_location']) || strlen($data['pickup_location']) > 100) {
        $errors[] = 'Valid pickup location is required';
    }
    
    if (empty($data['dropoff_location']) || strlen($data['dropoff_location']) > 100) {
        $errors[] = 'Valid dropoff location is required';
    }
    
    $validPaymentMethods = ['credit_card', 'debit_card', 'paypal', 'cash'];
    if (empty($data['payment_method']) || !in_array($data['payment_method'], $validPaymentMethods)) {
        $errors[] = 'Invalid payment method';
    }
    
    return $errors;
}

$errors = validateRentalData($input);
if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'message' => implode(', ', $errors)
    ]);
    exit;
}

$conn = getDBConnection();

$conn->begin_transaction();

try {
    $user_id = $_SESSION['user_id'];
    $vehicle_id = intval($input['vehicle_id']);
    $start_date = $input['start_date'];
    $end_date = $input['end_date'];
    $pickup_location = $input['pickup_location'];
    $dropoff_location = $input['dropoff_location'];
    $payment_method = $input['payment_method'];
    
    // STEP 1: Check if vehicle exists and is available
    $vehicleStmt = $conn->prepare("SELECT vehicle_id, brand, model, daily_rate, status FROM vehicles WHERE vehicle_id = ? FOR UPDATE");
    $vehicleStmt->bind_param("i", $vehicle_id);
    $vehicleStmt->execute();
    $vehicleResult = $vehicleStmt->get_result();
    
    if ($vehicleResult->num_rows === 0) {
        throw new Exception('Vehicle not found');
    }
    
    $vehicle = $vehicleResult->fetch_assoc();
    
    if ($vehicle['status'] !== 'available') {
        throw new Exception('Vehicle is not available for rent. Current status: ' . $vehicle['status']);
    }
    
    $vehicleStmt->close();
    
    // STEP 2: Check for overlapping rentals
    $overlapStmt = $conn->prepare("
        SELECT rental_id FROM rentals 
        WHERE vehicle_id = ? 
        AND status IN ('pending', 'confirmed', 'active')
        AND (
            (start_date <= ? AND end_date >= ?) OR
            (start_date <= ? AND end_date >= ?) OR
            (start_date >= ? AND end_date <= ?)
        )
        FOR UPDATE
    ");
    $overlapStmt->bind_param("issssss", 
        $vehicle_id, 
        $start_date, $start_date,
        $end_date, $end_date,
        $start_date, $end_date
    );
    $overlapStmt->execute();
    $overlapResult = $overlapStmt->get_result();
    
    if ($overlapResult->num_rows > 0) {
        throw new Exception('Vehicle is already booked for the selected dates. Please choose different dates.');
    }
    
    $overlapStmt->close();
    
    // STEP 3: Calculate rental details
    $startTimestamp = strtotime($start_date);
    $endTimestamp = strtotime($end_date);
    $totalDays = ceil(($endTimestamp - $startTimestamp) / (60 * 60 * 24));
    
    if ($totalDays <= 0) {
        throw new Exception('Invalid rental duration');
    }
    
    $daily_rate = floatval($vehicle['daily_rate']);
    $total_amount = $totalDays * $daily_rate;
    $deposit_amount = $total_amount * 0.30;
    
    // STEP 4: Create rental record
    $rentalStmt = $conn->prepare("
        INSERT INTO rentals (
            user_id, vehicle_id, start_date, end_date, 
            pickup_location, dropoff_location, 
            total_days, daily_rate, total_amount, deposit_amount,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')
    ");
    $rentalStmt->bind_param(
        "iissssiddd",
        $user_id,
        $vehicle_id,
        $start_date,
        $end_date,
        $pickup_location,
        $dropoff_location,
        $totalDays,
        $daily_rate,
        $total_amount,
        $deposit_amount
    );
    
    if (!$rentalStmt->execute()) {
        throw new Exception('Failed to create rental record');
    }
    
    $rental_id = $rentalStmt->insert_id;
    $rentalStmt->close();
    
    // STEP 5: Create payment record
    $transaction_id = 'TXN' . time() . rand(1000, 9999);
    $paymentStmt = $conn->prepare("
        INSERT INTO payments (
            rental_id, user_id, amount, payment_method, 
            payment_status, transaction_id, payment_date
        ) VALUES (?, ?, ?, ?, 'completed', ?, CURRENT_TIMESTAMP)
    ");
    $paymentStmt->bind_param(
        "iidss",
        $rental_id,
        $user_id,
        $deposit_amount,
        $payment_method,
        $transaction_id
    );
    
    if (!$paymentStmt->execute()) {
        throw new Exception('Failed to process payment');
    }
    
    $payment_id = $paymentStmt->insert_id;
    $paymentStmt->close();
    
    // STEP 6: Update vehicle status to rented (NO updated_at column)
    $updateStmt = $conn->prepare("UPDATE vehicles SET status = 'rented' WHERE vehicle_id = ?");
    $updateStmt->bind_param("i", $vehicle_id);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update vehicle status');
    }
    
    $updateStmt->close();
    
    // COMMIT TRANSACTION - All steps successful
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Booking confirmed successfully! Your rental ID is: ' . $rental_id,
        'data' => [
            'rental_id' => $rental_id,
            'payment_id' => $payment_id,
            'transaction_id' => $transaction_id,
            'vehicle' => $vehicle['brand'] . ' ' . $vehicle['model'],
            'total_days' => $totalDays,
            'total_amount' => $total_amount,
            'deposit_paid' => $deposit_amount,
            'start_date' => $start_date,
            'end_date' => $end_date
        ]
    ]);
    
} catch (Exception $e) {
    // ROLLBACK TRANSACTION - Something went wrong
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Booking failed: ' . $e->getMessage(),
        'error_type' => 'transaction_rollback'
    ]);
}

$conn->close();
?>
