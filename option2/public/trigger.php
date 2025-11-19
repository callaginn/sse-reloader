<?php
/**
 * Option 2: Deployment Trigger Script
 * 
 * This script simulates a deployment by updating APCu cache.
 * This would be called by your actual deployment script.
 * 
 * Usage: php trigger.php [version]
 */

// Check if APCu is available
if (!extension_loaded('apcu')) {
    die("Error: APCu extension is not loaded.\n");
}

if (!apcu_enabled()) {
    die("Error: APCu is not enabled. Check php.ini settings.\n");
}

// APCu keys
$versionKey = 'app_version';
$timestampKey = 'app_version_timestamp';
$triggerKey = 'app_version_trigger';

// Get version from command line or generate one
if (isset($argv[1])) {
    $version = $argv[1];
} else {
    // Generate version based on timestamp
    $current = apcu_fetch($versionKey) ?: '1.0.0';
    $parts = explode('.', $current);
    $parts[2] = isset($parts[2]) ? (int)$parts[2] + 1 : 1;
    $version = implode('.', $parts);
}

// Update APCu cache
apcu_store($versionKey, $version);
apcu_store($timestampKey, time());
apcu_store($triggerKey, 'deployment-script');

// If called via HTTP, redirect back
if (php_sapi_name() !== 'cli') {
    header('Location: index.php');
    exit;
}

// CLI output
echo sprintf(
    "[%s] ✓ Version updated to: %s\n",
    date('Y-m-d H:i:s'),
    $version
);
echo "APCu cache updated successfully!\n";
echo "Connected SSE clients will receive the update immediately.\n";
