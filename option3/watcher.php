<?php
/**
 * Option 3: Deployment Simulator
 * 
 * This script simulates a deployment by creating/modifying files
 * in the content directory.
 * 
 * Usage: yarn watch3
 */

$contentDir = __DIR__ . '/content';

// Ensure content directory exists
if (!is_dir($contentDir)) {
    mkdir($contentDir, 0755, true);
}

echo "Deployment Simulator\n";
echo "====================\n";
echo "This script will simulate deployments by modifying files.\n";
echo "Watch your browser to see automatic updates!\n";
echo "Press Ctrl+C to stop.\n\n";

$counter = 1;

while (true) {
    // Simulate different types of changes
    $action = rand(1, 3);
    
    switch ($action) {
        case 1:
            // Create or update a file
            $filename = "file" . rand(1, 5) . ".php";
            $filepath = $contentDir . '/' . $filename;
            $content = "<?php\n// Updated at: " . date('Y-m-d H:i:s') . "\n// Deployment #" . $counter . "\n";
            file_put_contents($filepath, $content);
            echo sprintf(
                "[%s] ✓ Updated: %s\n",
                date('Y-m-d H:i:s'),
                $filename
            );
            break;
            
        case 2:
            // Modify existing file
            $files = glob($contentDir . '/*.php');
            if (!empty($files)) {
                $file = $files[array_rand($files)];
                $content = file_get_contents($file);
                $content .= "// Modified at: " . date('Y-m-d H:i:s') . "\n";
                file_put_contents($file, $content);
                echo sprintf(
                    "[%s] ✓ Modified: %s\n",
                    date('Y-m-d H:i:s'),
                    basename($file)
                );
            }
            break;
            
        case 3:
            // Create a new versioned file
            $filename = "deploy_v" . $counter . ".php";
            $filepath = $contentDir . '/' . $filename;
            $content = "<?php\n// Deployment version " . $counter . "\n// Created at: " . date('Y-m-d H:i:s') . "\n";
            file_put_contents($filepath, $content);
            echo sprintf(
                "[%s] ✓ Created: %s\n",
                date('Y-m-d H:i:s'),
                $filename
            );
            break;
    }
    
    $counter++;
    
    // Sleep for 0.1 seconds
    usleep(100000);
}
