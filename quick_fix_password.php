<?php
require_once 'config.php';

$conn = getDBConnection();

// Generate fresh password hash for 'admin123'
$newPassword = password_hash('admin123', PASSWORD_DEFAULT);

// Update admin password
$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = 'admin@luxerentals.com'");
$stmt->bind_param("s", $newPassword);
$stmt->execute();

// Verify it works
$check = $conn->query("SELECT password_hash FROM users WHERE email = 'admin@luxerentals.com'");
$user = $check->fetch_assoc();

if (password_verify('admin123', $user['password_hash'])) {
    echo "✅ PASSWORD FIXED!<br><br>";
    echo "Login with:<br>";
    echo "Email: admin@luxerentals.com<br>";
    echo "Password: admin123<br><br>";
    echo "<a href='signin.html' style='background: #ffb800; color: #0a0e27; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>GO TO LOGIN</a>";
} else {
    echo "❌ Still not working!";
}

$stmt->close();
$conn->close();
?>
