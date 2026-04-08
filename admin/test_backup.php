<?php
// Simple test to check if backup system works
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Backup System Test</h1>";

// Check required extensions
echo "<h2>Checking Extensions:</h2>";
echo "ZipArchive: " . (class_exists('ZipArchive') ? "✓ Available" : "✗ Not Available") . "<br>";
echo "MySQLi: " . (class_exists('mysqli') ? "✓ Available" : "✗ Not Available") . "<br>";

// Test database connection
echo "<h2>Database Connection:</h2>";
try {
    require_once __DIR__ . '/../config/config.php';
    $mysqli = db_connect();
    echo "✓ Database connected successfully<br>";
    
    // Test query
    $result = $mysqli->query("SELECT COUNT(*) as count FROM bookings WHERE payment_proof IS NOT NULL AND payment_proof != ''");
    $row = $result->fetch_assoc();
    echo "✓ Found " . $row['count'] . " bookings with payment proofs<br>";
    
    $mysqli->close();
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}

// Test directory creation
echo "<h2>Directory Creation:</h2>";
$backup_dir = '../backups';
if (!file_exists($backup_dir)) {
    if (mkdir($backup_dir, 0777, true)) {
        echo "✓ Created backup directory<br>";
    } else {
        echo "✗ Failed to create backup directory<br>";
    }
} else {
    echo "✓ Backup directory exists<br>";
}

// Test ZIP creation
echo "<h2>ZIP Creation Test:</h2>";
try {
    $test_zip = '../backups/test.zip';
    $zip = new ZipArchive();
    if ($zip->open($test_zip, ZipArchive::CREATE) === TRUE) {
        $zip->addFromString('test.txt', 'This is a test file');
        $zip->close();
        echo "✓ ZIP file created successfully<br>";
        unlink($test_zip); // Clean up
    } else {
        echo "✗ Failed to create ZIP file<br>";
    }
} catch (Exception $e) {
    echo "✗ ZIP Error: " . $e->getMessage() . "<br>";
}

echo "<h2>Test Complete</h2>";
?>
