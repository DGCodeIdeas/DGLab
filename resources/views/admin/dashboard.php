<?php $this->layout('layouts/master', ['title' => 'Admin Dashboard']) ?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-3">Admin Dashboard</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item active">Dashboard</li>
                    <li class="breadcrumb-item"><a href="/admin/jobs">Jobs</a></li>
                    <li class="breadcrumb-item"><a href="/admin/logs">Logs</a></li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Total Jobs</h6>
                    <h3 class="mb-0"><?= $stats['jobs_count'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 bg-warning bg-opacity-10">
                <div class="card-body">
                    <h6 class="text-warning mb-2">Processing</h6>
                    <h3 class="mb-0"><?= $stats['processing_jobs'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 bg-success bg-opacity-10">
                <div class="card-body">
                    <h6 class="text-success mb-2">Completed</h6>
                    <h3 class="mb-0"><?= $stats['completed_jobs'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 bg-danger bg-opacity-10">
                <div class="card-body">
                    <h6 class="text-danger mb-2">Failed</h6>
                    <h3 class="mb-0"><?= $stats['failed_jobs'] ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- System Info -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">System Information</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <tbody>
                            <tr>
                                <td class="ps-3 text-muted">Disk Usage</td>
                                <td class="text-end pe-3"><?= $stats['disk_free'] ?> free / <?= $stats['disk_total'] ?></td>
                            </tr>
                            <tr>
                                <td class="ps-3 text-muted">Memory Usage</td>
                                <td class="text-end pe-3"><?= $stats['memory_usage'] ?></td>
                            </tr>
                            <tr>
                                <td class="ps-3 text-muted">PHP Version</td>
                                <td class="text-end pe-3"><?= $stats['php_version'] ?></td>
                            </tr>
                            <tr>
                                <td class="ps-3 text-muted">Server OS</td>
                                <td class="text-end pe-3"><?= $stats['server_os'] ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Jobs -->
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Jobs</h5>
                    <a href="/admin/jobs" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">ID</th>
                                    <th>Service</th>
                                    <th>Status</th>
                                    <th>Progress</th>
                                    <th class="pe-3">Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_jobs as $job): ?>
                                <tr>
                                    <td class="ps-3">#<?= $job->id ?></td>
                                    <td><?= htmlspecialchars($job->service_id) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $this->getStatusColor($job->status) ?>">
                                            <?= ucfirst($job->status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar" role="progressbar" style="width: <?= $job->progress ?>%"></div>
                                        </div>
                                    </td>
                                    <td class="pe-3 text-muted small"><?= $job->created_at ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recent_jobs)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No jobs found</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper for status colors
$this->getStatusColor = function($status) {
    return [
        'pending' => 'secondary',
        'processing' => 'info',
        'completed' => 'success',
        'failed' => 'danger',
        'cancelled' => 'warning',
    ][$status] ?? 'secondary';
};
?>
