<?php
/**
 * Option 3: Directory Snapshot SSE Server
 * 
 * This server maintains an in-memory snapshot of file modification times
 * and broadcasts SSE events when changes are detected.
 */

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Disable default PHP output buffering
if (ob_get_level()) ob_end_clean();

// Configuration
$watchDirs = [
    __DIR__ . '/../content',
];

$versionFile = __DIR__ . '/../version.txt';
$extensions = ['php', 'html', 'css', 'js']; // File extensions to monitor

// Initialize version file
if (!file_exists($versionFile)) {
    file_put_contents($versionFile, '1.0.0');
}

// Create content directory if it doesn't exist
foreach ($watchDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Function to send SSE event
function sendSSE($event, $data) {
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    flush();
}

// Function to scan directory and get file snapshots
function getDirectorySnapshot($dirs, $extensions) {
    $snapshot = [];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) continue;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION));
                if (in_array($ext, $extensions)) {
                    $snapshot[$file->getPathname()] = $file->getMTime();
                }
            }
        }
    }
    
    return $snapshot;
}

// Initialize snapshot
$startTime = microtime(true);
$snapshot = getDirectorySnapshot($watchDirs, $extensions);
$scanTime = round((microtime(true) - $startTime) * 1000, 2);

// Send initial connection message
sendSSE('message', [
    'status' => 'connected',
    'time' => date('Y-m-d H:i:s'),
    'filesMonitored' => count($snapshot)
]);

sendSSE('stats', [
    'filesMonitored' => count($snapshot),
    'scanTime' => $scanTime
]);

// Track heartbeat
$lastHeartbeat = 0;
$scanCounter = 0;

// Main monitoring loop
while (true) {
    // Check if connection is still alive
    if (connection_aborted()) {
        break;
    }

    // Scan for changes
    $startTime = microtime(true);
    clearstatcache();
    $newSnapshot = getDirectorySnapshot($watchDirs, $extensions);
    $scanTime = round((microtime(true) - $startTime) * 1000, 2);
    
    // Detect changes
    $changedFiles = [];
    
    // Check for modified or new files
    foreach ($newSnapshot as $file => $mtime) {
        if (!isset($snapshot[$file]) || $snapshot[$file] !== $mtime) {
            $changedFiles[] = basename(dirname($file)) . '/' . basename($file);
        }
    }
    
    // Check for deleted files
    foreach ($snapshot as $file => $mtime) {
        if (!isset($newSnapshot[$file])) {
            $changedFiles[] = basename(dirname($file)) . '/' . basename($file) . ' (deleted)';
        }
    }
    
    // If changes detected, update version and broadcast
    if (!empty($changedFiles)) {
        // Update snapshot
        $snapshot = $newSnapshot;
        
        // Increment version
        $version = trim(file_get_contents($versionFile));
        $parts = explode('.', $version);
        $parts[2] = isset($parts[2]) ? (int)$parts[2] + 1 : 1;
        $newVersion = implode('.', $parts);
        file_put_contents($versionFile, $newVersion);
        
        // Broadcast update
        sendSSE('version', [
            'version' => $newVersion,
            'timestamp' => time(),
            'time' => date('Y-m-d H:i:s'),
            'changedFiles' => $changedFiles,
            'stats' => [
                'filesMonitored' => count($snapshot),
                'scanTime' => $scanTime
            ]
        ]);
    }
    
    // Send periodic stats update (every 30 seconds)
    $scanCounter++;
    if ($scanCounter % 30 === 0) {
        sendSSE('stats', [
            'filesMonitored' => count($snapshot),
            'scanTime' => $scanTime
        ]);
    }
    
    // Send heartbeat every 15 seconds
    if (time() - $lastHeartbeat > 15) {
        sendSSE('heartbeat', [
            'time' => date('Y-m-d H:i:s'),
            'filesMonitored' => count($snapshot)
        ]);
        $lastHeartbeat = time();
    }

    // Sleep for 500ms before next check
    usleep(500000);
}
