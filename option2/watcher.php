<?php

$triggerUrl = 'http://option2.sse.test/trigger.php';

echo "APCu-based Auto Updater (via HTTP)\n";
echo "===================================\n";
echo "This script triggers updates via HTTP every 0.1 seconds.\n";
echo "Target: $triggerUrl\n";
echo "Press Ctrl+C to stop.\n\n";

// Prepare reusable cURL handle
$ch = curl_init($triggerUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 2,
    CURLOPT_POST => true,
]);

$counter = 1;

while (true) {
    // Pre-generate version string
    $version = "v2." . floor($counter / 10) . "." . ($counter % 10);

    // Only set POSTFIELDS (fast)
    curl_setopt($ch, CURLOPT_POSTFIELDS, "version={$version}");

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Faster timestamp
    $ts = microtime(true);

    if ($httpCode === 200 || $httpCode === 302) {
        echo "[" . $ts . "] ✓ $version (HTTP $httpCode)\n";
    } else {
        echo "[" . $ts . "] ✗ HTTP $httpCode\n";
    }

    $counter++;

    // Much lower overhead than usleep()
    time_nanosleep(0, 100_000_000); // 100ms
}

curl_close($ch);
