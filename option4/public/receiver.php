<?php 
    // Validate input
    if (!isset($_REQUEST["d"]) || empty($_REQUEST["d"])) {
        http_response_code(400);
        echo json_encode(['error' => 'No data provided']);
        exit;
    }

    $message = $_REQUEST["d"];
    $tabId = $_REQUEST["tabId"] ?? 'unknown';
    $senderName = $_REQUEST["senderName"] ?? 'Anonymous';
    $isPresence = isset($_REQUEST["presence"]) && $_REQUEST["presence"] === 'true';
    $isNameChange = isset($_REQUEST["nameChange"]) && $_REQUEST["nameChange"] === 'true';
    $oldName = $_REQUEST["oldName"] ?? null;
    $lockFile = __DIR__ . '/data/messages.lock';
    $historyFile = __DIR__ . '/data/messages.json';
    $refreshFile = __DIR__ . '/data/refresh.trigger';
    $newMessageFile = __DIR__ . '/data/newmessage.trigger';

    // Handle name changes
    if ($isNameChange && $oldName) {
        $fp = fopen($lockFile, 'c');
        if (flock($fp, LOCK_EX)) {
            // Update all messages from old name to new name
            if (file_exists($historyFile)) {
                $historyContent = file_get_contents($historyFile);
                $history = json_decode($historyContent, true) ?: [];
                
                $updated = false;
                foreach ($history as &$msg) {
                    if ($msg['senderName'] === $oldName) {
                        $msg['senderName'] = $senderName;
                        $updated = true;
                    }
                }
                
                if ($updated) {
                    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));
                }
            }
            
            // Trigger refresh for all clients (including the one making the change)
            $refreshData = ['time' => microtime(true)];
            file_put_contents($refreshFile, json_encode($refreshData));
            
            flock($fp, LOCK_UN);
            fclose($fp);
            
            echo json_encode(['success' => true, 'type' => 'nameChange', 'refresh' => true]);
            exit;
        }
        fclose($fp);
    }

    // If this is just a presence notification, trigger refresh
    if ($isPresence || $message === '__USER_JOINED__') {
        // Trigger refresh for all clients (including the one joining)
        $refreshData = ['time' => microtime(true)];
        file_put_contents($refreshFile, json_encode($refreshData));
        echo json_encode(['success' => true, 'type' => 'presence', 'senderName' => $senderName, 'refresh' => true]);
        exit;
    }

    // Create message object
    $messageObj = [
        'id' => uniqid('msg_', true),
        'content' => $message,
        'timestamp' => time(),
        'tabId' => $tabId,
        'senderName' => $senderName
    ];

    // Use file locking to prevent race conditions
    $fp = fopen($lockFile, 'c');
    if (flock($fp, LOCK_EX)) {
        // Add to message history
        $history = [];
        if (file_exists($historyFile)) {
            $historyContent = file_get_contents($historyFile);
            $history = json_decode($historyContent, true) ?: [];
        }
        
        $history[] = $messageObj;
        
        // Keep only last 100 messages
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }
        
        file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));
        
        // Trigger newMessage event for all clients
        $newMessageData = [
            'time' => microtime(true),
            'messageId' => $messageObj['id'],
            'excludeTabId' => $tabId // Don't notify the sender via SSE (they already have it)
        ];
        file_put_contents($newMessageFile, json_encode($newMessageData));
        
        flock($fp, LOCK_UN);
        fclose($fp);
        
        echo json_encode(['success' => true, 'message' => $messageObj]);
    } else {
        fclose($fp);
        http_response_code(500);
        echo json_encode(['error' => 'Could not acquire lock']);
    }
?>
