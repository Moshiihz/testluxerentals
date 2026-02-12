<?php
// Simple connection test
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Connection Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-box {
            background: white;
            padding: 20px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 5px solid;
        }
        .success { border-color: #22c55e; background: #f0fdf4; }
        .error { border-color: #ef4444; background: #fef2f2; }
        h2 { margin-top: 0; }
        code { background: #e5e7eb; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>🔧 Luxe Rentals - Connection Diagnostic</h1>
    
    <?php
    // Test 1: PHP is working
    echo '<div class="test-box success">';
    echo '<h2>✓ Test 1: PHP is Working</h2>';
    echo '<p>PHP Version: <code>' . phpversion() . '</code></p>';
    echo '</div>';
    
    // Test 2: MySQLi Extension
    if (extension_loaded('mysqli')) {
        echo '<div class="test-box success">';
        echo '<h2>✓ Test 2: MySQLi Extension Loaded</h2>';
        echo '<p>The MySQLi extension is available.</p>';
        echo '</div>';
    } else {
        echo '<div class="test-box error">';
        echo '<h2>✗ Test 2: MySQLi Extension Missing</h2>';
        echo '<p>Please enable MySQLi in php.ini</p>';
        echo '</div>';
    }
    
    // Test 3: Database Connection
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $dbname = 'luxe_rentals';
    
    $conn = @new mysqli($host, $user, $pass, $dbname);
    
    if ($conn->connect_error) {
        echo '<div class="test-box error">';
        echo '<h2>✗ Test 3: Database Connection Failed</h2>';
        echo '<p><strong>Error:</strong> ' . $conn->connect_error . '</p>';
        echo '<p><strong>Error Code:</strong> ' . $conn->connect_errno . '</p>';
        
        if ($conn->connect_errno == 1049) {
            echo '<p><strong>Solution:</strong> Database "luxe_rentals" does not exist. Please create it in phpMyAdmin.</p>';
        } elseif ($conn->connect_errno == 2002) {
            echo '<p><strong>Solution:</strong> MySQL is not running. Start it in XAMPP Control Panel.</p>';
        } elseif ($conn->connect_errno == 1045) {
            echo '<p><strong>Solution:</strong> Wrong username or password. Check config.php settings.</p>';
        }
        echo '</div>';
    } else {
        echo '<div class="test-box success">';
        echo '<h2>✓ Test 3: Database Connection Successful</h2>';
        echo '<p>Successfully connected to database: <code>' . $dbname . '</code></p>';
        echo '</div>';
        
        // Test 4: Check tables
        $tables = ['users', 'vehicles', 'rentals', 'payments'];
        $allTablesExist = true;
        $tableStatus = [];
        
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows > 0) {
                $count = $conn->query("SELECT COUNT(*) as cnt FROM $table")->fetch_assoc()['cnt'];
                $tableStatus[$table] = ['exists' => true, 'count' => $count];
            } else {
                $tableStatus[$table] = ['exists' => false];
                $allTablesExist = false;
            }
        }
        
        if ($allTablesExist) {
            echo '<div class="test-box success">';
            echo '<h2>✓ Test 4: Database Tables</h2>';
            echo '<ul>';
            foreach ($tableStatus as $table => $status) {
                echo '<li><code>' . $table . '</code>: ' . $status['count'] . ' records</li>';
            }
            echo '</ul>';
            echo '</div>';
        } else {
            echo '<div class="test-box error">';
            echo '<h2>✗ Test 4: Some Tables Missing</h2>';
            echo '<ul>';
            foreach ($tableStatus as $table => $status) {
                if (!$status['exists']) {
                    echo '<li><code>' . $table . '</code>: Missing ✗</li>';
                }
            }
            echo '</ul>';
            echo '</div>';
        }
        
        $conn->close();
    }
    
    // Test 5: Test AJAX Endpoint
    echo '<div class="test-box success">';
    echo '<h2>✓ Test 5: AJAX Test</h2>';
    echo '<button onclick="testAjax()" style="padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer;">Click to Test AJAX Connection</button>';
    echo '<div id="ajax-result" style="margin-top: 10px;"></div>';
    echo '</div>';
    ?>
    
    <div class="test-box" style="border-color: #3b82f6; background: #eff6ff;">
        <h2>📋 Next Steps</h2>
        <ol>
            <li>If all tests pass above, the issue is in your HTML/JavaScript files</li>
            <li>Make sure you access via <code>http://localhost/your-folder-name/signin.html</code></li>
            <li>Don't open HTML files directly (file:/// URLs won't work)</li>
            <li>Clear browser cache and try again</li>
            <li>Check browser console (F12) for JavaScript errors</li>
        </ol>
    </div>
    
    <script>
    async function testAjax() {
        const resultDiv = document.getElementById('ajax-result');
        resultDiv.innerHTML = '<p style="color: #3b82f6;">Testing AJAX connection...</p>';
        
        try {
            const response = await fetch('login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: 'test@test.com',
                    password: 'test123'
                })
            });
            
            const data = await response.json();
            
            resultDiv.innerHTML = `
                <p style="color: #22c55e; font-weight: bold;">✓ AJAX Request Successful!</p>
                <p>Response: <code>${JSON.stringify(data)}</code></p>
                <p><em>This means your PHP backend is working! The issue is likely with your login credentials or database data.</em></p>
            `;
        } catch (error) {
            resultDiv.innerHTML = `
                <p style="color: #ef4444; font-weight: bold;">✗ AJAX Request Failed!</p>
                <p>Error: <code>${error.message}</code></p>
                <p><strong>This is your problem!</strong> JavaScript cannot reach PHP file.</p>
                <p><strong>Solution:</strong> Make sure you're accessing this page via <code>http://localhost/</code> not <code>file:///</code></p>
            `;
        }
    }
    </script>
</body>
</html>
