<?php
	// SSEMessenger - Server-Sent Events Message Broadcasting System
	class SSEMessenger {
		private string $historyFile;
		private string $refreshFile;
		private string $newMessageFile;
		private string $dataDir;
		
		private ?string $clientTabId;
		private ?string $lastMessageId = null;
		private float $lastRefreshTime = 0;
		private float $lastNewMessageTime = 0;
		private float $lastKeepalive = 0;
		
		private int $pollInterval;
		private int $keepaliveInterval;
		private int $bufferPadding;
		
		private array $history = [];
		private array $historyById = [];
		private int $historyMTime = 0;
		
		// Initialize the SSE messenger with configurable options
		public function __construct(string $dataDir = null, ?string $clientTabId = null, array $config = []) {
			$defaults = [
				'pollInterval' => 100000,
				'keepaliveInterval' => 15,
				'bufferPadding' => 4096,
			];
			$config = array_merge($defaults, $config);
			
			$this->dataDir = $dataDir ?? __DIR__ . '/data';
			$this->clientTabId = $clientTabId;
			$this->pollInterval = (int) $config['pollInterval'];
			$this->keepaliveInterval = (int) $config['keepaliveInterval'];
			$this->bufferPadding = (int) $config['bufferPadding'];
			
			$this->historyFile = $this->dataDir . '/messages.json';
			$this->refreshFile = $this->dataDir . '/refresh.trigger';
			$this->newMessageFile = $this->dataDir . '/newmessage.trigger';
			
			$this->setupEnvironment();
		}
		
		// Configure PHP environment for SSE streaming
		private function setupEnvironment(): void {
			set_time_limit(0);
			ignore_user_abort(true);
			session_write_close();
			
			while (ob_get_level() > 0) {
				ob_end_clean();
			}
		}
		
		// Send SSE headers to client
		public function sendHeaders(): void {
			header('Content-Type: text/event-stream');
			header('Cache-Control: no-cache');
			header('Connection: keep-alive');
			header('X-Accel-Buffering: no');
		}
		
		// Send data to client with proper flushing
		private function sendEvent(array $data, ?string $eventType = null): void {
			if ($eventType) {
				echo "event: {$eventType}\n";
			}
			echo "data: " . json_encode($data) . "\n\n";
			$this->flushOutput();
		}
		
		// Force output through all buffers
		private function flushOutput(): void {
			echo str_repeat(" ", $this->bufferPadding) . "\n";
			
			if (ob_get_level() > 0) {
				ob_flush();
			}
			flush();
		}
		
		// Send keepalive comment to maintain connection
		private function sendKeepalive(): void {
			echo ": keepalive\n\n";
			$this->flushOutput();
			$this->lastKeepalive = time();
		}
		
		// Load message history from disk with memoization
		private function loadHistory(): array {
			if (!is_file($this->historyFile)) {
				$this->history = [];
				$this->historyById = [];
				$this->historyMTime = 0;
				return [];
			}
			
			$mtime = (int) filemtime($this->historyFile);
			if ($mtime === $this->historyMTime) {
				return $this->history;
			}
			
			$history = $this->readJson($this->historyFile);
			$this->history = $history;
			$this->historyById = [];
			foreach ($history as $message) {
				if (isset($message['id'])) {
					$this->historyById[$message['id']] = $message;
				}
			}
			$this->historyMTime = $mtime;
			
			return $this->history;
		}
		
		// Helper to read and decode JSON files
		private function readJson(string $path): array {
			clearstatcache(true, $path);
			$content = file_get_contents($path);
			if ($content === false) {
				return [];
			}
			$data = json_decode($content, true);
			return is_array($data) ? $data : [];
		}
		
		// Send initial connection setup
		public function sendInitialData(): void {
			$history = $this->loadHistory();
			$this->sendHistoryEvent($history);
			
			if (!empty($history)) {
				$lastMessage = end($history);
				$this->lastMessageId = $lastMessage['id'];
			}
			
			$this->sendConnectedEvent();
		}
		
		// Send history payload
		private function sendHistoryEvent(array $messages, string $type = 'history'): void {
			$this->sendEvent(['type' => $type, 'messages' => $messages]);
		}
		
		// Send connected notification
		private function sendConnectedEvent(): void {
			$this->sendEvent(['type' => 'connected', 'message' => 'Connection established']);
		}
		
		// Send new message payload
		private function sendNewMessageEvent(array $message): void {
			$this->sendEvent(['type' => 'newMessage', 'message' => $message]);
		}
		
		// Check and handle refresh triggers
		private function checkRefreshTrigger(): bool {
			return $this->processTrigger($this->refreshFile, $this->lastRefreshTime, function (): bool {
				$history = $this->loadHistory();
				$this->sendHistoryEvent($history, 'refresh');
				
				if (!empty($history)) {
					$lastMessage = end($history);
					$this->lastMessageId = $lastMessage['id'];
				}
				
				return true;
			});
		}
		
		// Check and handle new message triggers
		private function checkNewMessageTrigger(): bool {
			return $this->processTrigger($this->newMessageFile, $this->lastNewMessageTime, function (array $data): bool {
				$messageId = $data['messageId'] ?? null;
				if (!$messageId) {
					return false;
				}
				
				$message = $this->getMessageById($messageId);
				if (!$message) {
					return false;
				}
				
				$this->sendNewMessageEvent($message);
				$this->lastMessageId = $message['id'];
				return true;
			});
		}
		
		// Generic trigger processor with memoized timestamps
		private function processTrigger(string $file, float &$lastProcessedTime, callable $handler, string $excludeKey = 'excludeTabId'): bool {
			if (!is_file($file)) {
				return false;
			}
			
			$data = $this->readJson($file);
			if (!$data || !isset($data['time'])) {
				return false;
			}
			
			$triggerTime = (float) $data['time'];
			if ($triggerTime <= $lastProcessedTime) {
				return false;
			}
			
			$lastProcessedTime = $triggerTime;
			$excludeTabId = $data[$excludeKey] ?? null;
			if ($excludeTabId !== null && $this->clientTabId === $excludeTabId) {
				return false;
			}
			
			return (bool) $handler($data);
		}
		
		// Retrieve a single message via cached lookup
		private function getMessageById(string $messageId): ?array {
			$this->loadHistory();
			return $this->historyById[$messageId] ?? null;
		}
		
		// Check for new messages in history (fallback)
		private function checkHistoryForNewMessages(): int {
			$history = $this->loadHistory();
			if (empty($history)) {
				return 0;
			}
			
			$foundLast = ($this->lastMessageId === null);
			$newMessages = [];
			
			foreach ($history as $message) {
				if ($foundLast) {
					$newMessages[] = $message;
				} elseif ($message['id'] === $this->lastMessageId) {
					$foundLast = true;
				}
			}
			
			foreach ($newMessages as $message) {
				$this->sendNewMessageEvent($message);
				$this->lastMessageId = $message['id'];
			}
			
			return count($newMessages);
		}
		
		// Main event loop - monitors for changes and sends updates
		public function startEventLoop(): void {
			while (!connection_aborted()) {
				$handled = $this->checkRefreshTrigger();
				$handled = $this->checkNewMessageTrigger() || $handled;
				
				if (!$handled) {
					$this->checkHistoryForNewMessages();
				}
				
				if (time() - $this->lastKeepalive > $this->keepaliveInterval) {
					$this->sendKeepalive();
				}
				
				usleep($this->pollInterval);
			}
		}
		
		// Static helper to quickly start SSE server
		public static function serve(?string $dataDir = null, array $config = []): void {
			$clientTabId = $_GET['tabId'] ?? null;
			
			$messenger = new self($dataDir, $clientTabId, $config);
			$messenger->sendHeaders();
			$messenger->sendInitialData();
			$messenger->startEventLoop();
		}
	}

	SSEMessenger::serve();
?>
