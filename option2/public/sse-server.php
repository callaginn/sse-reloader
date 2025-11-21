<?php

function flushOutput() {
    // Padding to push through Nginx/Apache gzip buffers and PHP output buffering
    echo str_repeat(" ", 4096) . "\n";
    
    // Only flush if there's actually a buffer
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

// Fix 1: Prevent script from timing out
set_time_limit(0);

// Prevent any output buffering
if (ob_get_level()) ob_end_clean();

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Disable output buffering
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);

if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}

// Check if APCu is available
if (!function_exists('apcu_fetch')) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'APCu extension not installed']) . "\n\n";
    flush();
    exit;
}

// Initialize version in APCu if not set
if (apcu_fetch('app_version') === false) {
    apcu_store('app_version', 'v1.0.0');
}

$lastVersion = '';
$currentVersion = apcu_fetch('app_version');

// Send initial connection message
echo "event: connected\n";
echo "data: " . json_encode(['message' => 'SSE connection established', 'apcu_enabled' => true]) . "\n\n";
flushOutput();

// Send initial version immediately
echo "event: version\n";
echo "data: " . json_encode([
    'version' => $currentVersion,
    'timestamp' => time(),
    'trigger' => 'initial'
]) . "\n\n";
flushOutput();
$lastVersion = $currentVersion;

// Main monitoring loop
while (true) {
    if (connection_aborted()) {
        break;
    }
    
    // Fetch the current version
    $currentVersion = apcu_fetch('app_version');
    
    // Handle a missing key (e.g., expired or cleared)
    if ($currentVersion === false) {
        $currentVersion = 'v1.0.0';
        apcu_store('app_version', $currentVersion);
    }
    
    // Check for changes against the last sent version
    if ($currentVersion !== $lastVersion) {
        $trigger = apcu_fetch('app_version_trigger') ?: 'unknown';
        
        echo "event: version\n";
        echo "data: " . json_encode([
            'version' => $currentVersion,
            'timestamp' => time(),
            'trigger' => $trigger
        ]) . "\n\n";
        flushOutput();
        
        $lastVersion = $currentVersion;
    }
    
    // Send heartbeat every 5 iterations (2.5 seconds)
    static $heartbeatCounter = 0;
    if (++$heartbeatCounter >= 5) {
        echo "event: heartbeat\n";
        echo "data: " . json_encode([
            'timestamp' => time(), 
            'version' => $currentVersion,
            'lastVersion' => $lastVersion
        ]) . "\n\n";
        flushOutput();
        $heartbeatCounter = 0;
    }
    
    // Sleep for 500ms
    usleep(500000);
}