<?php
require_once 'config.php';

header('Content-Type: application/json');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    // Fallback to $_POST if JSON decode fails
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
} else {
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
}

// Validate input
if (empty($email) || empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter both email and password'
    ]);
    exit;
}

// Get database connection
$conn = getDBConnection();

// Prepare statement to prevent SQL injection - ADDED is_admin column
$stmt = $conn->prepare("SELECT user_id, full_name, email, password_hash, account_status, is_admin FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Email not found. Please check your email or sign up.'
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

$user = $result->fetch_assoc();

// Check if account is active
if ($user['account_status'] !== 'active') {
    echo json_encode([
        'success' => false,
        'message' => 'Your account is ' . $user['account_status'] . '. Please contact support.'
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

// Verify password
if (password_verify($password, $user['password_hash'])) {
    // Password correct - create session
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['logged_in'] = true;
    $_SESSION['is_admin'] = (int)($user['is_admin'] ?? 0) === 1; // ADD ADMIN FLAG
    
    // Update last login
    $update_stmt = $conn->prepare("UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
    $update_stmt->bind_param("i", $user['user_id']);
    $update_stmt->execute();
    $update_stmt->close();
    
    // REDIRECT URL based on admin status
    $redirect_url = $_SESSION['is_admin'] ? 'admin_dashboard.php' : 'home.html';
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful! Redirecting...',
        'redirect' => $redirect_url,  // Send redirect URL to frontend
        'user' => [
            'name' => $user['full_name'],
            'email' => $user['email'],
            'is_admin' => $_SESSION['is_admin']
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Incorrect password. Please try again.'
    ]);
}

$stmt->close();
$conn->close();
?>
