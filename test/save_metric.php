<?php
// Path: /test/save_metric.php

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $version = isset($_POST['version']) ? htmlspecialchars($_POST['version']) : 'Unknown';
    $time = isset($_POST['time']) ? (float)$_POST['time'] : 0.0;
    
    $timestamp = date('Y-m-d H:i:s');
    
    // CSV Format: Timestamp, Version, Time (Seconds)
    $data_line = "$timestamp,$version,$time\n";
    
    // Save file in the root of the KitchenLink project
    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/ab_test_results.txt';
    
    if (file_put_contents($file_path, $data_line, FILE_APPEND | LOCK_EX) !== false) {
        echo json_encode(['status' => 'success', 'message' => 'Metric saved successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to write to file']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
?>
