<?php
/**
 * Option 1: Version.txt Polling
 * 
 * This script simulates a deployment by updating version.txt periodically.
 * Run this to test the SSE update mechanism.
 * 
 * Usage: yarn watch1
 */

$versionFile = __DIR__ . '/version.txt';

echo "Version File Watcher\n";
echo "====================\n";
echo "This script will update version.txt every 10 seconds.\n";
echo "Watch your browser to see automatic updates!\n";
echo "Press Ctrl+C to stop.\n\n";

$counter = 1;

while (true) {
    // Generate new version
    $version = sprintf("1.%d.%d", floor($counter / 10), $counter % 10);
    
    // Update version file
    file_put_contents($versionFile, $version);
    
    // Update modification time to current time
    touch($versionFile);
    
    echo sprintf(
        "[%s] Updated version to: %s\n",
        date('Y-m-d H:i:s'),
        $version
    );
    
    $counter++;
    
    // Sleep for 0.1 seconds
    usleep(100000);
}
