<?php
require_once 'config.php';

header('Content-Type: application/json');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $country = $_POST['country'] ?? '';
    $street_address = $_POST['street_address'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $postal_code = $_POST['postal_code'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
} else {
    $full_name = $input['full_name'] ?? '';
    $email = $input['email'] ?? '';
    $phone = $input['phone'] ?? '';
    $country = $input['country'] ?? '';
    $street_address = $input['street_address'] ?? '';
    $city = $input['city'] ?? '';
    $state = $input['state'] ?? '';
    $postal_code = $input['postal_code'] ?? '';
    $password = $input['password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';
}

// Validate required fields
if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please fill in all required fields'
    ]);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid email address'
    ]);
    exit;
}

// Validate password match
if ($password !== $confirm_password) {
    echo json_encode([
        'success' => false,
        'message' => 'Passwords do not match'
    ]);
    exit;
}

// Validate password strength
if (strlen($password) < 6) {
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 6 characters long'
    ]);
    exit;
}

// Get database connection
$conn = getDBConnection();

// Check if email already exists
$check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$check_stmt->bind_param("s", $email);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'This email is already registered. Please sign in or use a different email.'
    ]);
    $check_stmt->close();
    $conn->close();
    exit;
}
$check_stmt->close();

// Hash password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Prepare insert statement
$stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, country, password_hash, account_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
$stmt->bind_param("sssss", $full_name, $email, $phone, $country, $password_hash);

if ($stmt->execute()) {
    $user_id = $stmt->insert_id;
    
    // Create session for auto-login
    $_SESSION['user_id'] = $user_id;
    $_SESSION['full_name'] = $full_name;
    $_SESSION['email'] = $email;
    $_SESSION['logged_in'] = true;
    
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully! Welcome to Luxe Rentals, ' . $full_name . '!',
        'user' => [
            'name' => $full_name,
            'email' => $email
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Registration failed. Please try again. Error: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
