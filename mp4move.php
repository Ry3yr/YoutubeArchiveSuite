<?php
$sourceDir = __DIR__;       // current directory
$targetDir = $sourceDir . '/fin';

// Create the target directory if it doesn't exist
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

// Scan the source directory for mp4 files
$files = glob($sourceDir . '/*.mp4');

foreach ($files as $file) {
    $filename = basename($file);
    $newPath = $targetDir . '/' . $filename;

    // Move the file
    if (rename($file, $newPath)) {
        echo "Moved $filename to fin/\n";
    } else {
        echo "Failed to move $filename\n";
    }
}
