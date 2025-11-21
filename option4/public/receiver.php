<?php
	class MessageReceiver {
		private const MESSAGE_LIMIT = 100;

		private array $input;
		private string $lockFile;
		private string $historyFile;
		private string $eventFile;

		public function __construct(array $input, ?string $dataDir = null) {
			$this->input = $input;
			$dataDir = $dataDir ?? __DIR__ . '/data';
			$this->lockFile = $dataDir . '/messages.lock';
			$this->historyFile = $dataDir . '/messages.json';
			$this->eventFile = $dataDir . '/events.triggers';
		}

		public function handle(): void {
			if (empty($this->input['d'])) {
				$this->respond(400, ['error' => 'No data provided']);
			}

			if ($this->handleNameChange()) {
				return;
			}

			if ($this->handlePresence()) {
				return;
			}

			$this->handleMessage();
		}

		private function handleNameChange(): bool {
			// Detects when the client is attempting to rename themselves.
			if (($this->input['nameChange'] ?? 'false') !== 'true') {
				return false;
			}

			$oldName = trim((string)($this->input['oldName'] ?? ''));
			if ($oldName === '') {
				$this->respond(400, ['error' => 'Old name required']);
			}

			$this->withLock(function () use ($oldName): void {
				$history = $this->loadHistory();
				$updated = false;
				foreach ($history as &$msg) {
					if (($msg['senderName'] ?? null) === $oldName) {
						$msg['senderName'] = $this->senderName();
						$updated = true;
					}
				}
				unset($msg);

				if ($updated) {
					$this->saveHistory($history);
				}
			});

			$this->emitEvent('refresh');
			$this->respond(200, ['success' => true, 'type' => 'nameChange', 'refresh' => true]);
			return true;
		}

		private function handlePresence(): bool {
			// Detects presence pings or synthetic join messages from clients.
			if (($this->input['presence'] ?? 'false') !== 'true' && ($this->input['d'] ?? '') !== '__USER_JOINED__') {
				return false;
			}

			$this->emitEvent('refresh');
			$this->respond(200, [
				'success' => true,
				'type' => 'presence',
				'senderName' => $this->senderName(),
				'refresh' => true
			]);
			return true;
		}

		private function handleMessage(): void {
			$message = $this->createMessage();
			$this->withLock(function () use ($message): void {
				$history = $this->loadHistory();
				$history[] = $message;
				if (count($history) > self::MESSAGE_LIMIT) {
					$history = array_slice($history, -self::MESSAGE_LIMIT);
				}
				$this->saveHistory($history);
			});

			$this->emitEvent('newMessage', [
				'messageId' => $message['id'],
				'excludeTabId' => $message['tabId']
			]);

			$this->respond(200, ['success' => true, 'message' => $message]);
		}

		private function createMessage(): array {
			return [
				'id' => uniqid('msg_', true),
				'content' => (string)$this->input['d'],
				'timestamp' => time(),
				'tabId' => $this->tabId(),
				'senderName' => $this->senderName()
			];
		}

		private function senderName(): string {
			return $this->input['senderName'] ?? 'Anonymous';
		}

		private function tabId(): string {
			return $this->input['tabId'] ?? 'unknown';
		}

		private function loadHistory(): array {
			if (!is_file($this->historyFile)) {
				return [];
			}
			$content = file_get_contents($this->historyFile);
			return json_decode($content, true) ?: [];
		}

		private function saveHistory(array $history): void {
			file_put_contents($this->historyFile, json_encode($history, JSON_PRETTY_PRINT));
		}

		private function emitEvent(string $type, array $payload = []): void {
			$events = [];
			if (is_file($this->eventFile)) {
				$content = file_get_contents($this->eventFile);
				$decoded = json_decode($content, true);
				if (is_array($decoded)) {
					$events = $decoded;
				}
			}

			$events[$type] = array_merge([
				'time' => microtime(true)
			], $payload);

			file_put_contents(
				$this->eventFile,
				json_encode($events, JSON_PRETTY_PRINT),
				LOCK_EX
			);
		}

		private function withLock(callable $callback) {
			$fp = fopen($this->lockFile, 'c+');
			if (!$fp) {
				$this->respond(500, ['error' => 'Cannot open lock']);
			}
			if (!flock($fp, LOCK_EX)) {
				fclose($fp);
				$this->respond(500, ['error' => 'Could not acquire lock']);
			}

			try {
				return $callback($fp);
			} finally {
				flock($fp, LOCK_UN);
				fclose($fp);
			}
		}

		private function respond(int $status, array $payload): void {
			http_response_code($status);
			header('Content-Type: application/json');
			echo json_encode($payload);
			exit;
		}
	}
	(new MessageReceiver($_REQUEST))->handle();
?>
