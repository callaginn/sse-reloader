<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Option 4: Git-Based Trigger</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="app-wrapper">
        <div class="header">
            <h1>
                <div class="number-icon">
                    <i class="fas fa-4"></i>
                </div>
                Git-Based Trigger + SSE
            </h1>
            <p class="lead">Monitors Git commit hash and broadcasts updates via Server-Sent Events</p>
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
                        <i class="fas fa-code-commit"></i>
                        Current Commit:
                    </span>
                    <span id="commit" class="status-value">Unknown</span>
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
                    <li>The SSE server (sse-server.php) monitors the Git HEAD commit hash</li>
                    <li>When a new commit is detected, it broadcasts an update event</li>
                    <li>This page receives the event and reloads automatically</li>
                    <li>Test by making a git commit: <code>git commit --allow-empty -m "test"</code></li>
                    <li>Works with any git operation: pull, merge, checkout, etc.</li>
                </ul>
            </section>
        </div>
    </div>

    <script>
        const log = document.getElementById('log');
        const statusEl = document.getElementById('status');
        const commitEl = document.getElementById('commit');
        const lastUpdateEl = document.getElementById('lastUpdate');

        function addLog(message) {
            const entry = document.createElement('div');
            entry.className = 'log-entry';
            entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            log.insertBefore(entry, log.firstChild);
            console.log(message);
        }

        addLog('Attempting to connect to SSE server...');
        const eventSource = new EventSource('sse-server.php');

        eventSource.onopen = function() {
            statusEl.textContent = 'Connected';
            statusEl.style.color = '#4caf50';
            addLog('SSE connection established');
        };

        eventSource.addEventListener('connected', function(e) {
            const data = JSON.parse(e.data);
            addLog('Connected to SSE server');
            if (data.commit) {
                commitEl.textContent = data.commit.substring(0, 7);
                lastUpdateEl.textContent = new Date().toLocaleString();
            }
        });

        eventSource.addEventListener('heartbeat', function(e) {
            addLog('Heartbeat received');
        });

        eventSource.addEventListener('commit', function(e) {
            const data = JSON.parse(e.data);
            addLog(`Git commit detected: ${data.commit.substring(0, 7)}`);
            commitEl.textContent = data.commit.substring(0, 7);
            lastUpdateEl.textContent = new Date().toLocaleString();
            
            setTimeout(() => {
                addLog('Reloading page...');
                location.reload();
            }, 1000);
        });

        eventSource.onerror = function(e) {
            if (eventSource.readyState === EventSource.CLOSED) {
                statusEl.textContent = 'Disconnected';
                statusEl.style.color = '#f44336';
                addLog('SSE connection lost. Attempting to reconnect...');
            } else {
                statusEl.textContent = 'Error';
                statusEl.style.color = '#ff9800';
                addLog('SSE connection error');
            }
        };
    </script>
</body>
</html>
