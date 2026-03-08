<?php

namespace DGLab\Controllers;

use DGLab\Core\Controller;
use DGLab\Core\Application;
use DGLab\Core\Logger;
use DGLab\Database\Job;
use DGLab\Database\Connection;

class AdminController extends Controller
{
    public function index()
    {
        $app = Application::getInstance();
        $db = $app->get(Connection::class);

        $stats = [
            'jobs_count' => Job::query()->count(),
            'pending_jobs' => Job::query()->where('status', Job::STATUS_PENDING)->count(),
            'processing_jobs' => Job::query()->where('status', Job::STATUS_PROCESSING)->count(),
            'completed_jobs' => Job::query()->where('status', Job::STATUS_COMPLETED)->count(),
            'failed_jobs' => Job::query()->where('status', Job::STATUS_FAILED)->count(),
            'disk_free' => $this->formatBytes(disk_free_space('/')),
            'disk_total' => $this->formatBytes(disk_total_space('/')),
            'memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'php_version' => PHP_VERSION,
            'server_os' => PHP_OS,
        ];

        return $this->view('admin/dashboard', [
            'stats' => $stats,
            'recent_jobs' => Job::recent(10),
        ]);
    }

    public function jobs()
    {
        $status = $this->request->get('status');
        $query = Job::query();

        if ($status) {
            $query->where('status', $status);
        }

        $jobs = $query->orderBy('created_at', 'DESC')->limit(100)->get();

        return $this->view('admin/jobs', [
            'jobs' => $jobs,
            'current_status' => $status,
        ]);
    }

    public function logs()
    {
        $logger = $this->app->get(Logger::class);
        $logs = $logger->getRecent(200);

        return $this->view('admin/logs', [
            'logs' => $logs,
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;

        while ($bytes > 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}
