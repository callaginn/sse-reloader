<?php

class UpdateStreamServer {
	private $config;
	private $lastVersion = null;
	private $lastMtime = 0;
	
	public function __construct(array $options = []) {
		$defaults = [
			'file_path' => __DIR__ . '/../version.txt',
			'check_interval' => 100,    // MS between checks
			'heartbeat_rate' => 15000,  // MS between heartbeats
			'retry_timeout'  => 2000,   // MS for browser to reconnect
		];
		
		$this->config = array_merge($defaults, $options);
		$this->ensureFileExists();
	}
	
	// Starts the SSE Stream. This method blocks execution indefinitely.
	public function run() {
		$this->prepareEnvironment();
		$this->sendHeaders();
		
		// Initial State
		$this->lastVersion = $this->readVersionSafe();
		$filePath = $this->config['file_path'];
		$this->lastMtime = file_exists($filePath) ? filemtime($filePath) : 0;
		
		$lastHeartbeat = microtime(true);
		
		// Send connection success
		$this->sendEvent('connected', [
			'message' => 'Stream active',
			'version' => $this->lastVersion
		]);
		
		// Main Loop
		while (true) {
			// 1. Check connection health
			if (connection_aborted()) {
				break; // Exit loop to kill script
			}
			
			// 2. Check for Version Updates
			$this->checkForUpdates();
			
			// 3. Send Heartbeat (Keep-alive)
			$now = microtime(true);
			if (($now - $lastHeartbeat) * 1000 >= $this->config['heartbeat_rate']) {
				$this->sendEvent('heartbeat', ['time' => time()]);
				$lastHeartbeat = $now;
			}
			
			// 4. Sleep (Prevent CPU spikes)
			usleep($this->config['check_interval'] * 1000);
		}
	}
	
	// Checks file stats and sends event if changed.
	private function checkForUpdates() {
		// Clear PHP's internal file stat cache
		clearstatcache(false, $this->config['file_path']);
		
		$currentMtime = @filemtime($this->config['file_path']);
		
		// Check modification time first (fast)
		if ($currentMtime && $currentMtime !== $this->lastMtime) {
			$this->lastMtime = $currentMtime;
			
			// Check actual content (slow but accurate)
			$currentVersion = $this->readVersionSafe();
			
			if ($currentVersion !== null && $currentVersion !== $this->lastVersion) {
				$this->lastVersion = $currentVersion;
				
				$this->sendEvent('version', [
					'version'   => $currentVersion,
					'timestamp' => time()
				], $currentMtime);
			}
		}
	}
	
	// Outputs a Server-Sent Event
	private function sendEvent($event, $data, $id = null) {
		echo "event: {$event}\n";
		if ($id) {
			echo "id: {$id}\n";
		}
		echo "retry: {$this->config['retry_timeout']}\n";
		echo "data: " . json_encode($data) . "\n\n";
		
		$this->flushOutput();
	}
	
	// Flushes PHP and Server buffers to force output to client
	private function flushOutput() {
		// Padding to push through Nginx/Apache gzip buffers and PHP output buffering
		echo str_repeat(" ", 4096) . "\n";
		
		// Only flush if there's actually a buffer
		if (ob_get_level() > 0) {
			ob_flush();
		}
		flush();
	}
	
	// Reads file with a shared lock to prevent reading partial writes
	private function readVersionSafe() {
		$path = $this->config['file_path'];
		if (!file_exists($path)) return null;
		
		$content = null;
		$fp = fopen($path, 'r');
		
		if ($fp && flock($fp, LOCK_SH)) { // Acquire Shared Lock
			$content = stream_get_contents($fp);
			flock($fp, LOCK_UN); // Release Lock
		}
		
		if ($fp) fclose($fp);
		return $content ? trim($content) : null;
	}
	
	// Sets up PHP runtime environment for streaming
	private function prepareEnvironment() {
		set_time_limit(0); // Disable timeout
		
		// Close session to prevent blocking other requests from same user
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_write_close();
		}
		
		// Disable output buffering
		ini_set('output_buffering', 'off');
		ini_set('zlib.output_compression', false);
		
		// Clear existing buffers
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
	}
	
	// Sends HTTP Headers
	private function sendHeaders() {
		header('Content-Type: text/event-stream');
		header('Cache-Control: no-cache');
		header('Connection: keep-alive');
		header('X-Accel-Buffering: no'); // Nginx specific
		
		// If you are using Apache, sometimes this helper is needed
		if (function_exists('apache_setenv')) {
			apache_setenv('no-gzip', '1');
		}
	}
	
	// Creates the version file if missing
	private function ensureFileExists() {
		if (!file_exists($this->config['file_path'])) {
			file_put_contents($this->config['file_path'], '1.0.0');
		}
	}
}

$server = new UpdateStreamServer([
	'file_path' => __DIR__ . '/../version.txt',
	'check_interval' => 1000,  // Check every 1 second
	'heartbeat_rate' => 10000  // Heartbeat every 10 seconds
]);

$server->run();
