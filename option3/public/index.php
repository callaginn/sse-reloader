<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Option 3: Directory Watcher</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="app-wrapper">
        <div class="header">
            <h1>
                <div class="number-icon">
                    <i class="fas fa-3"></i>
                </div>
                Directory Watcher
            </h1>
            <p class="lead">Maintains an in-memory snapshot of file modification times and detects changes</p>
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

            <div class="stats">
                <div class="stat-box">
                    <div class="stat-icon">
                        <i class="fas fa-file-code"></i>
                    </div>
                    <div class="stat-number" id="filesMonitored">0</div>
                    <div class="stat-label">Files Monitored</div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <div class="stat-number" id="changesDetected">0</div>
                    <div class="stat-label">Changes Detected</div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <div class="stat-number" id="scanTime">0ms</div>
                    <div class="stat-label">Last Scan Time</div>
                </div>
            </div>
            
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
                    <li>The SSE server keeps an in-memory snapshot of all PHP file modification times</li>
                    <li>It periodically rescans the watched directories for changes</li>
                    <li>When changes are detected, it updates the version and broadcasts an event</li>
                    <li>Selective rescanning keeps performance high even with many files</li>
                    <li>Test by editing any PHP file in the content/ directory</li>
                    <li>Or run: <code>yarn watch3</code></li>
                </ul>
            </section>
        </div>
    </div>

    <script>
        const log = document.getElementById('log');
        const statusEl = document.getElementById('status');
        const versionEl = document.getElementById('version');
        const lastUpdateEl = document.getElementById('lastUpdate');
        const filesMonitoredEl = document.getElementById('filesMonitored');
        const changesDetectedEl = document.getElementById('changesDetected');
        const scanTimeEl = document.getElementById('scanTime');

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

        eventSource.addEventListener('version', function(e) {
            const data = JSON.parse(e.data);
            versionEl.textContent = data.version;
            lastUpdateEl.textContent = new Date().toLocaleString();
            
            if (data.changedFiles) {
                addLog(`Version ${data.version} - ${data.changedFiles.length} file(s) changed`);
                data.changedFiles.forEach(file => {
                    addLog(`  → ${file}`);
                });
            } else {
                addLog(`Version updated to: ${data.version}`);
            }
            
            // Update stats
            if (data.stats) {
                filesMonitoredEl.textContent = data.stats.filesMonitored || 0;
                changesDetectedEl.textContent = (parseInt(changesDetectedEl.textContent) || 0) + (data.changedFiles?.length || 0);
                scanTimeEl.textContent = (data.stats.scanTime || 0) + 'ms';
            }
            
            // Reload page on version change (after 2 seconds to show the message)
            // if (versionEl.getAttribute('data-initial') === 'true') {
            //     addLog('Changes detected - reloading page in 2 seconds...');
            //     setTimeout(() => {
            //         window.location.reload();
            //     }, 2000);
            // } else {
            //     versionEl.setAttribute('data-initial', 'true');
            // }
        });

        eventSource.addEventListener('stats', function(e) {
            const data = JSON.parse(e.data);
            filesMonitoredEl.textContent = data.filesMonitored || 0;
            scanTimeEl.textContent = (data.scanTime || 0) + 'ms';
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
