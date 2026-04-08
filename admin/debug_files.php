<?php
require_once __DIR__ . '/../config/config.php';

echo "<h1>Payment Proof Files Debug</h1>";

$mysqli = db_connect();
$result = $mysqli->query("
    SELECT booking_reference, payment_proof 
    FROM bookings 
    WHERE payment_proof IS NOT NULL 
    AND payment_proof != ''
    ORDER BY created_at DESC
    LIMIT 5
");

echo "<h2>Latest 5 Payment Proof Paths:</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Booking Ref</th><th>DB Path</th><th>Corrected Path</th><th>DB Exists?</th><th>Corrected Exists?</th></tr>";

while ($row = $result->fetch_assoc()) {
    $db_path = $row['payment_proof'];
    $corrected_path = '../uploads/payment_proof/' . basename($row['payment_proof']);
    $db_exists = file_exists($db_path);
    $corrected_exists = file_exists($corrected_path);
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['booking_reference']) . "</td>";
    echo "<td><small>" . htmlspecialchars($db_path) . "</small></td>";
    echo "<td><small>" . htmlspecialchars($corrected_path) . "</small></td>";
    echo "<td>" . ($db_exists ? "✅ YES" : "❌ NO") . "</td>";
    echo "<td>" . ($corrected_exists ? "✅ YES" : "❌ NO") . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Current Working Directory:</h2>";
echo "<p>" . getcwd() . "</p>";

echo "<h2>Looking for files in common locations:</h2>";
$paths_to_check = [
    '../uploads/payment_proof/',
    '../uploads/',
    '../payment_proof/',
    './uploads/',
    './payment_proof/',
    '../',
    ''
];

foreach ($paths_to_check as $path) {
    if (is_dir($path)) {
        echo "<p>✅ Directory exists: <strong>$path</strong></p>";
        $files = glob($path . '*');
        echo "<p>Files found: " . count($files) . "</p>";
        if (count($files) > 0) {
            echo "<ul>";
            foreach (array_slice($files, 0, 5) as $file) {
                echo "<li>" . htmlspecialchars(basename($file)) . "</li>";
            }
            if (count($files) > 5) {
                echo "<li>... and " . (count($files) - 5) . " more</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p>❌ Directory not found: <strong>$path</strong></p>";
    }
}

$mysqli->close();
?>
