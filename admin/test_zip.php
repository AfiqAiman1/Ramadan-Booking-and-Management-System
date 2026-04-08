<?php
// Minimal test for ZIP creation
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors
ini_set('log_errors', 1);

header('Content-Type: application/json');

try {
    // Test basic ZIP creation
    $zip = new ZipArchive();
    $test_file = '../backups/test_minimal.zip';
    
    if ($zip->open($test_file, ZipArchive::CREATE) === TRUE) {
        $zip->addFromString('test.txt', 'Hello World');
        $zip->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'ZIP created successfully',
            'file' => $test_file
        ]);
        
        // Clean up
        if (file_exists($test_file)) {
            unlink($test_file);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create ZIP',
            'status' => $zip->status
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
