<?php
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Please login.'
    ]);
    exit;
}

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo json_encode([
        'success' => false,
        'message' => 'Admin access required.'
    ]);
    exit;
}

$conn = getDBConnection();

// Get action from query parameter
$action = $_GET['action'] ?? '';

// Get JSON body
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($action) {
        case 'create':
            // Validate required fields
            $required = ['brand', 'model', 'year', 'category', 'daily_rate', 'seats', 'transmission', 'fuel_type', 'plate_number', 'status'];
            foreach ($required as $field) {
                if (!isset($input[$field]) || $input[$field] === '') {
                    throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' is required');
                }
            }
            
            // Check if plate number already exists
            $checkStmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE plate_number = ?");
            $checkStmt->bind_param("s", $input['plate_number']);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $checkStmt->close();
                throw new Exception('A vehicle with this plate number already exists');
            }
            $checkStmt->close();
            
            $stmt = $conn->prepare("
                INSERT INTO vehicles (brand, model, year, category, daily_rate, seats, transmission, fuel_type, plate_number, color, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $color = $input['color'] ?? 'Not specified';
            
            $stmt->bind_param("ssississsss",
                $input['brand'],
                $input['model'],
                $input['year'],
                $input['category'],
                $input['daily_rate'],
                $input['seats'],
                $input['transmission'],
                $input['fuel_type'],
                $input['plate_number'],
                $color,
                $input['status']
            );
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Vehicle added successfully!']);
            } else {
                throw new Exception('Failed to add vehicle');
            }
            $stmt->close();
            break;
            
        case 'update':
            if (empty($input['vehicle_id'])) {
                throw new Exception('Vehicle ID is required');
            }
            
            // Check if plate number exists for another vehicle
            $checkStmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE plate_number = ? AND vehicle_id != ?");
            $checkStmt->bind_param("si", $input['plate_number'], $input['vehicle_id']);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $checkStmt->close();
                throw new Exception('Another vehicle with this plate number already exists');
            }
            $checkStmt->close();
            
            $stmt = $conn->prepare("
                UPDATE vehicles 
                SET brand=?, model=?, year=?, category=?, daily_rate=?, seats=?, transmission=?, fuel_type=?, plate_number=?, color=?, status=?
                WHERE vehicle_id = ?
            ");
            
            $color = $input['color'] ?? 'Not specified';
            
            $stmt->bind_param("ssississsssi",
                $input['brand'],
                $input['model'],
                $input['year'],
                $input['category'],
                $input['daily_rate'],
                $input['seats'],
                $input['transmission'],
                $input['fuel_type'],
                $input['plate_number'],
                $color,
                $input['status'],
                $input['vehicle_id']
            );
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Vehicle updated successfully!']);
            } else {
                throw new Exception('Failed to update vehicle');
            }
            $stmt->close();
            break;
            
        case 'delete':
            $vehicle_id = $_GET['vehicle_id'] ?? null;
            
            if (empty($vehicle_id)) {
                throw new Exception('Vehicle ID is required');
            }
            
            // Check if vehicle has active rentals
            $checkStmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM rentals 
                WHERE vehicle_id = ? AND status IN ('pending', 'confirmed', 'active', 'approved')
            ");
            $checkStmt->bind_param("i", $vehicle_id);
            $checkStmt->execute();
            $result = $checkStmt->get_result()->fetch_assoc();
            
            if ($result['count'] > 0) {
                $checkStmt->close();
                throw new Exception('Cannot delete vehicle with active rentals');
            }
            $checkStmt->close();
            
            $stmt = $conn->prepare("DELETE FROM vehicles WHERE vehicle_id = ?");
            $stmt->bind_param("i", $vehicle_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Vehicle deleted successfully!']);
            } else {
                throw new Exception('Failed to delete vehicle');
            }
            $stmt->close();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
