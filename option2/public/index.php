<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Option 2: Instant APCu Reloader</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="app-wrapper">
        <div class="header">
            <h1>
                <div class="number-icon">
                    <i class="fas fa-2"></i>
                </div>
                Instant APCu Reloader
            </h1>
            <p class="lead">Uses APCu memory cache to store version info and trigger immediate SSE broadcasts</p>
        </div>
        
        <div class="content">
            <section class="status-card">
                <div class="status-row">
                    <span class="status-label">
                        <i class="fas fa-plug"></i>
                        Connection Status:
                    </span>
                    <span id="status" class="status-value">Connecting...</span>
                </div>
                <div class="status-row">
                    <span class="status-label">
                        <i class="fas fa-code-branch"></i>
                        Current Version:
                    </span>
                    <span id="version" class="status-value">Unknown</span>
                </div>
                <div class="status-row">
                    <span class="status-label">
                        <i class="fas fa-clock"></i>
                        Last Update:
                    </span>
                    <span id="lastUpdate" class="status-value">Never</span>
                </div>
            </section>
            
            <?php if (!extension_loaded('apcu')): ?>
            <div class="warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="warning-content">
                    <strong>Warning: APCu extension is not loaded</strong>
                    This option requires APCu to be installed and enabled.<br>
                    Install it with: <code>pecl install apcu</code>
                </div>
            </div>
            <?php endif; ?>
            
            <section>
                <h3 class="section-title">
                    <i class="fas fa-list"></i>
                    Event Log
                </h3>
                <div class="log" id="log"></div>
            </section>
            
            <section>
                <h3 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    How it works
                </h3>
                <ul class="info-list">
                    <li>The deployment script (trigger.php) updates version info in APCu memory</li>
                    <li>The SSE server (sse-server.php) monitors APCu for changes</li>
                    <li>When a change is detected, it immediately broadcasts an event</li>
                    <li>No file polling needed - updates are instant!</li>
                    <li>Test by running: <code>php trigger.php</code></li>
                </ul>
            </section>
            
            <section>
                <h3 class="section-title">
                    <i class="fas fa-rocket"></i>
                    Manual Trigger
                </h3>
                <form action="trigger.php" method="post">
                    <button type="submit" class="btn btn-lg btn-primary trigger-button">
                        <i class="fas fa-bolt"></i>
                        Trigger Update Now
                    </button>
                </form>
            </section>
        </div>
    </div>

    <script>
        const log = document.getElementById('log');
        const statusEl = document.getElementById('status');
        const versionEl = document.getElementById('version');
        const lastUpdateEl = document.getElementById('lastUpdate');

        function addLog(message) {
            const entry = document.createElement('div');
            entry.className = 'log-entry';
            entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            log.insertBefore(entry, log.firstChild);
        }

        // Initialize SSE connection
        const eventSource = new EventSource('sse-server.php');

        eventSource.onopen = function() {
            statusEl.textContent = 'Connected';
            statusEl.style.color = '#4caf50';
            addLog('SSE connection established');
        };
		
		eventSource.addEventListener('connected', function(e) {
            addLog('Received connected event from server');
        });

        eventSource.addEventListener('heartbeat', function(e) {
            addLog('Heartbeat received');
        });

        eventSource.addEventListener('version', function(e) {
            const data = JSON.parse(e.data);
            versionEl.textContent = data.version;
            lastUpdateEl.textContent = new Date().toLocaleString();
            addLog(`Version updated to: ${data.version} (via ${data.trigger || 'unknown'})`);
			
			console.log(data);
            
            // Reload page on version change (after 2 seconds to show the message)
            // if (versionEl.getAttribute('data-initial') === 'true') {
            //     addLog('Version change detected - reloading page in 2 seconds...');
            //     setTimeout(() => {
            //         window.location.reload();
            //     }, 2000);
            // } else {
            //     versionEl.setAttribute('data-initial', 'true');
            // }
        });

        eventSource.onerror = function(e) {
            statusEl.textContent = 'Connection Lost';
            statusEl.style.color = '#f44336';
            addLog('SSE connection error - will retry automatically');
        };

        addLog('Page loaded, waiting for SSE updates...');
    </script>
</body>
</html>
