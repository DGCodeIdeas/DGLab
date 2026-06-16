<?php

/**
 * DGLab Cleanup CLI Tool
 *
 * Runs background maintenance tasks to clean up old files, tokens, jobs, and chunks.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use DGLab\Core\Application;
use DGLab\Services\Download\CleanupService;
use DGLab\Database\Job;
use DGLab\Database\UploadChunk;
use DGLab\Database\Connection;

// Bootstrap application
$app = Application::getInstance();

echo "DGLab System Cleanup Tool\n";
echo "=========================\n\n";

$dryRun = in_array('--dry-run', $argv);

if ($dryRun) {
    echo "⚠ RUNNING IN DRY-RUN MODE (No files or records will be deleted)\n\n";
}

try {
    // 1. Download Service Cleanup
    echo "Cleaning up Download Service...\n";
    $cleanupService = new CleanupService();
    $stats = $cleanupService->run($dryRun);

    echo "  ✓ Tokens deleted: {$stats['tokens_deleted']}\n";
    echo "  ✓ Files deleted: {$stats['files_deleted']}\n";
    echo "  ✓ Orphaned files deleted: {$stats['orphaned_files_deleted']}\n";
    echo "  ✓ Space reclaimed: " . format_bytes($stats['space_reclaimed']) . "\n";

    if (!empty($stats['errors'])) {
        foreach ($stats['errors'] as $error) {
            echo "  ✗ Error: {$error}\n";
        }
    }
    echo "\n";

    // 2. Job Cleanup
    echo "Cleaning up processing jobs (older than 30 days)...\n";
    if (!$dryRun) {
        $jobsDeleted = Job::cleanup(30);
        echo "  ✓ Jobs deleted: {$jobsDeleted}\n";
    } else {
        echo "  (Skipped in dry-run)\n";
    }
    echo "\n";

    // 3. Upload Chunk Cleanup
    echo "Cleaning up expired upload chunks...\n";
    if (!$dryRun) {
        $chunksDeleted = UploadChunk::cleanupExpired();
        echo "  ✓ Chunk sessions cleaned: {$chunksDeleted}\n";
    } else {
        echo "  (Skipped in dry-run)\n";
    }
    echo "\n";

    echo "Done.\n";
} catch (Exception $e) {
    echo "✗ Fatal Error: " . $e->getMessage() . "\n";
    exit(1);
}
