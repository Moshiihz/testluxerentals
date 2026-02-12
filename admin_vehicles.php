<?php
require_once 'config.php';

header('Content-Type: application/json');

$conn = getDBConnection();

try {
    // Get ALL vehicles (not just available ones, since admin needs to see all)
    $stmt = $conn->prepare("
        SELECT 
            vehicle_id as vehicleId,
            brand,
            model,
            year,
            category,
            daily_rate as dailyRate,
            seats,
            transmission,
            fuel_type as fuelType,
            plate_number as plateNumber,
            color,
            status,
            image_url as imageUrl
        FROM vehicles 
        ORDER BY vehicle_id DESC
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $vehicles = [];
    while ($row = $result->fetch_assoc()) {
        $vehicles[] = [
            'vehicleId' => intval($row['vehicleId']),
            'brand' => $row['brand'],
            'model' => $row['model'],
            'year' => intval($row['year']),
            'category' => $row['category'],
            'dailyRate' => floatval($row['dailyRate']),
            'seats' => intval($row['seats']),
            'transmission' => $row['transmission'],
            'fuelType' => $row['fuelType'],
            'plateNumber' => $row['plateNumber'],
            'color' => $row['color'],
            'status' => $row['status'],
            'imageUrl' => $row['imageUrl']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    // Return vehicles array directly
    echo json_encode($vehicles);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>
