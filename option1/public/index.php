<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Option 1: Version.txt Polling</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="app-wrapper">
        <div class="header">
            <h1>
                <div class="number-icon">
                    <i class="fas fa-1"></i>
                </div>
                Version.txt Polling + SSE
            </h1>
            <p class="lead">Polls a version.txt file and broadcasts updates via Server-Sent Events</p>
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
                    <li>The SSE server (sse-server.php) polls version.txt every 0.5 seconds</li>
                    <li>When version.txt modification time changes, it broadcasts an event</li>
                    <li>This page receives the event and reloads automatically</li>
                    <li>Test by running: <code>yarn watch1</code> and editing version.txt</li>
                </ul>
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
            console.log(message); // Also log to console for debugging
        }

        // Initialize SSE connection
        addLog('Attempting to connect to SSE server...');
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
            addLog(`Version updated to: ${data.version}`);
            
            // Reload page on version change (after 2 seconds to show the message)
            // if (versionEl.getAttribute('data-initial') === 'true') {
            //     addLog('Version change detected - reloading page in 2 seconds...');
            //     setTimeout(() => {
            //         window.location.reload();
            //     }, 2000);
				
			// 	window.location.reload();
            // } else {
            //     versionEl.setAttribute('data-initial', 'true');
            // }
        });

        eventSource.onerror = function(e) {
            statusEl.textContent = 'Connection Lost';
            statusEl.style.color = '#f44336';
            addLog('SSE connection error - will retry automatically');
            console.error('SSE Error:', e);
        };

        addLog('Page loaded, waiting for SSE updates...');
    </script>
</body>
</html>
