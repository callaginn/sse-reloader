<?php
// Prevent timeout
set_time_limit(0);
ignore_user_abort(true);

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Close session to prevent blocking
session_write_close();

// Ensure output is sent immediately
if (ob_get_level()) ob_end_clean();

$historyFile = __DIR__ . '/messages.json';
$refreshFile = __DIR__ . '/refresh.trigger';

// Get client's tab ID from query string
$clientTabId = $_GET['tabId'] ?? null;

// Track last message ID sent to this client
$lastMessageId = null;
$lastRefreshTime = 0;

// Send message history first
if (file_exists($historyFile)) {
    $history = json_decode(file_get_contents($historyFile), true) ?: [];
    echo "data: " . json_encode(['type' => 'history', 'messages' => $history]) . "\n\n";
    flush();
    
    // Set last message ID to the most recent in history
    if (!empty($history)) {
        $lastMessageId = end($history)['id'];
    }
}

// Send initial connection message
echo "data: " . json_encode(['type' => 'connected', 'message' => 'Connection established']) . "\n\n";
flush();

while (true) {
    // Check if client is still connected
    if (connection_aborted()) {
        break;
    }
    
    // Check for refresh trigger
    if (file_exists($refreshFile)) {
        clearstatcache(true, $refreshFile);
        $refreshContent = file_get_contents($refreshFile);
        $refreshData = json_decode($refreshContent, true);
        
        if ($refreshData && isset($refreshData['time'])) {
            $refreshTime = $refreshData['time'];
            $excludeTabId = $refreshData['excludeTabId'] ?? null;
            
            // Only refresh if this is a new trigger
            if ($refreshTime > $lastRefreshTime) {
                // If no excludeTabId is set, refresh all clients
                // If excludeTabId is set, only refresh if this client is not the excluded one
                if ($excludeTabId === null || $clientTabId !== $excludeTabId) {
                    echo "data: " . json_encode(['type' => 'refresh', 'message' => 'User list updated']) . "\n\n";
                    flush();
                }
                // Always update lastRefreshTime to avoid repeated triggers
                $lastRefreshTime = $refreshTime;
            }
        }
    }
    
    // Check for new messages in history
    if (file_exists($historyFile)) {
        clearstatcache(true, $historyFile);
        $history = json_decode(file_get_contents($historyFile), true) ?: [];
        
        // Find messages newer than what we've sent
        $foundLast = ($lastMessageId === null);
        $newMessages = [];
        
        foreach ($history as $message) {
            if ($foundLast) {
                $newMessages[] = $message;
            } elseif ($message['id'] === $lastMessageId) {
                $foundLast = true;
            }
        }
        
        // Send any new messages
        foreach ($newMessages as $message) {
            echo "data: " . json_encode(['type' => 'newMessage', 'message' => $message]) . "\n\n";
            flush();
            $lastMessageId = $message['id'];
        }
    }
    
    // Send keepalive comment every 15 seconds
    static $lastKeepalive = 0;
    if (time() - $lastKeepalive > 15) {
        echo ": keepalive\n\n";
        flush();
        $lastKeepalive = time();
    }
    
    usleep(100000); // 0.1 second - faster polling
}
?>
