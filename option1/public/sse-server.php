<?php
/**
 * Option 1: Version.txt Polling SSE Server
 * 
 * This server polls version.txt file periodically and sends SSE events
 * when the file's modification time changes.
 */


// Prevent any output buffering
if (ob_get_level()) ob_end_clean();

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Disable output buffering for this script
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);

// Flush output immediately
if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}

// Path to version file (one level up from public/)
$versionFile = __DIR__ . '/../version.txt';

// Create version file if it doesn't exist
if (!file_exists($versionFile)) {
    file_put_contents($versionFile, '1.0.0');
}

$lastMtime = 0;
$lastVersion = '';

// Send initial connection message
echo "event: connected\n";
echo "data: " . json_encode(['message' => 'SSE connection established']) . "\n\n";
echo ":\n\n"; // Comment to help with buffering
flush();
ob_flush();

// Main loop - check for updates every 500ms
while (true) {
    // Check if client is still connected
    if (connection_aborted()) {
        break;
    }
    
    // Get current modification time
    clearstatcache();
    $currentMtime = @filemtime($versionFile);
    
    if ($currentMtime === false) {
        // File doesn't exist, recreate it
        file_put_contents($versionFile, '1.0.0');
        $currentMtime = filemtime($versionFile);
    }
    
    // Check if file has been modified
    if ($currentMtime !== $lastMtime) {
        $lastMtime = $currentMtime;
        $version = trim(file_get_contents($versionFile));
        
        // Only send update if version actually changed
        if ($version !== $lastVersion) {
            $lastVersion = $version;
            
            // Send version update event
            echo "event: version\n";
            echo "data: " . json_encode([
                'version' => $version,
                'timestamp' => time(),
                'mtime' => $currentMtime
            ]) . "\n\n";
            echo ":\n\n"; // Comment to help with buffering
            flush();
            ob_flush();
        }
    }
    
    // Send heartbeat every 5 iterations (2.5 seconds) to keep connection alive
    static $heartbeatCounter = 0;
    if (++$heartbeatCounter >= 5) {
        echo "event: heartbeat\n";
        echo "data: " . json_encode(['timestamp' => time()]) . "\n\n";
        echo ":\n\n"; // Comment to help with buffering
        flush();
        ob_flush();
        $heartbeatCounter = 0;
    }
    
    // Sleep for 500ms before next check
    usleep(500000);
}
