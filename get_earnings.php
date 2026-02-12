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
    // Get today's date
    $today = date('Y-m-d');
    
    // Calculate daily earnings from completed and active rentals
    $stmt = $conn->prepare("
        SELECT 
            DATE(created_at) as rental_date,
            SUM(total_amount) as daily_total,
            COUNT(*) as booking_count
        FROM rentals 
        WHERE status IN ('approved', 'active', 'completed')
        AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY rental_date DESC
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $earnings = [];
    $todayEarnings = 0;
    $totalEarnings = 0;
    
    while ($row = $result->fetch_assoc()) {
        $earnings[] = [
            'date' => $row['rental_date'],
            'amount' => floatval($row['daily_total']),
            'bookings' => intval($row['booking_count'])
        ];
        
        $totalEarnings += floatval($row['daily_total']);
        
        if ($row['rental_date'] === $today) {
            $todayEarnings = floatval($row['daily_total']);
        }
    }
    
    $stmt->close();
    
    // Get total statistics
    $statsStmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_bookings,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_bookings,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
            SUM(CASE WHEN status IN ('approved', 'active', 'completed') THEN total_amount ELSE 0 END) as total_revenue
        FROM rentals
    ");
    
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    $stats = $statsResult->fetch_assoc();
    $statsStmt->close();
    
    echo json_encode([
        'success' => true,
        'today_earnings' => $todayEarnings,
        'total_earnings' => $totalEarnings,
        'earnings_data' => $earnings,
        'statistics' => [
            'total_bookings' => intval($stats['total_bookings']),
            'pending_bookings' => intval($stats['pending_bookings']),
            'approved_bookings' => intval($stats['approved_bookings']),
            'active_bookings' => intval($stats['active_bookings']),
            'completed_bookings' => intval($stats['completed_bookings']),
            'total_revenue' => floatval($stats['total_revenue'])
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
