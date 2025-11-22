<?php

class GitTriggerServer {
	private $config;
	private $lastCommit = null;
	
	public function __construct(array $options = []) {
		$defaults = [
			'git_dir' => realpath(__DIR__ . '/../..'),
			'check_interval' => 500,    // MS between checks
			'heartbeat_rate' => 15000,  // MS between heartbeats
			'retry_timeout'  => 2000,   // MS for browser to reconnect
		];
		
		$this->config = array_merge($defaults, $options);
	}
	
	// Starts the SSE Stream
	public function run() {
		$this->prepareEnvironment();
		$this->sendHeaders();
		
		// Get initial commit
		$this->lastCommit = $this->getCurrentCommit();
		
		$lastHeartbeat = microtime(true);
		
		// Send connection success
		$this->sendEvent('connected', [
			'message' => 'Stream active',
			'commit' => $this->lastCommit
		]);
		
		// Main Loop
		while (true) {
			if (connection_aborted()) {
				break;
			}
			
			// Check for new commits
			$this->checkForCommitChange();
			
			// Send Heartbeat
			$now = microtime(true);
			if (($now - $lastHeartbeat) * 1000 >= $this->config['heartbeat_rate']) {
				$this->sendEvent('heartbeat', ['time' => time()]);
				$lastHeartbeat = $now;
			}
			
			usleep($this->config['check_interval'] * 1000);
		}
	}
	
	// Check if git HEAD has changed
	private function checkForCommitChange() {
		$currentCommit = $this->getCurrentCommit();
		
		if ($currentCommit && $currentCommit !== $this->lastCommit) {
			$this->lastCommit = $currentCommit;
			
			$this->sendEvent('commit', [
				'commit' => $currentCommit,
				'timestamp' => time()
			]);
		}
	}
	
	// Get current git commit hash
	private function getCurrentCommit() {
		$gitDir = $this->config['git_dir'];
		$headFile = $gitDir . '/.git/HEAD';
		
		if (!file_exists($headFile)) {
			return null;
		}
		
		// Read HEAD file
		$head = trim(file_get_contents($headFile));
		
		// HEAD can be either:
		// 1. A direct commit hash: "abc123..."
		// 2. A reference: "ref: refs/heads/main"
		if (strpos($head, 'ref:') === 0) {
			// Extract the reference path
			$ref = trim(substr($head, 4));
			$refFile = $gitDir . '/.git/' . $ref;
			
			if (file_exists($refFile)) {
				return trim(file_get_contents($refFile));
			}
		} else {
			// Direct commit hash
			return $head;
		}
		
		return null;
	}
	
	// Send SSE event
	private function sendEvent($event, $data) {
		echo "event: {$event}\n";
		echo "retry: {$this->config['retry_timeout']}\n";
		echo "data: " . json_encode($data) . "\n\n";
		
		$this->flushOutput();
	}
	
	// Force output to client
	private function flushOutput() {
		echo str_repeat(" ", 4096) . "\n";
		
		if (ob_get_level() > 0) {
			ob_flush();
		}
		flush();
	}
	
	// Prepare PHP environment for streaming
	private function prepareEnvironment() {
		set_time_limit(0);
		
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_write_close();
		}
		
		ini_set('output_buffering', 'off');
		ini_set('zlib.output_compression', false);
		
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
	}
	
	// Send HTTP headers
	private function sendHeaders() {
		header('Content-Type: text/event-stream');
		header('Cache-Control: no-cache');
		header('Connection: keep-alive');
		header('X-Accel-Buffering: no');
	}
}

// Start the stream
$server = new GitTriggerServer();
$server->run();
