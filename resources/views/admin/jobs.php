<?php $this->layout('layouts/master', ['title' => 'Admin - Jobs']) ?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1">Service Jobs</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/admin">Dashboard</a></li>
                        <li class="breadcrumb-item active">Jobs</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="/admin/jobs" class="btn btn-<?= !$current_status ? 'primary' : 'outline-primary' ?> btn-sm">All</a>
                <a href="/admin/jobs?status=processing" class="btn btn-<?= $current_status === 'processing' ? 'primary' : 'outline-primary' ?> btn-sm">Processing</a>
                <a href="/admin/jobs?status=failed" class="btn btn-<?= $current_status === 'failed' ? 'primary' : 'outline-primary' ?> btn-sm">Failed</a>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">ID</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Message</th>
                            <th>Started At</th>
                            <th class="pe-3">Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td class="ps-3">#<?= $job->id ?></td>
                            <td><span class="fw-medium"><?= htmlspecialchars($job->service_id) ?></span></td>
                            <td>
                                <span class="badge bg-<?= $this->getStatusColor($job->status) ?>">
                                    <?= ucfirst($job->status) ?>
                                </span>
                            </td>
                            <td style="width: 150px;">
                                <div class="d-flex align-items-center">
                                    <div class="progress flex-grow-1" style="height: 6px;">
                                        <div class="progress-bar" role="progressbar" style="width: <?= $job->progress ?>%"></div>
                                    </div>
                                    <span class="ms-2 small text-muted"><?= $job->progress ?>%</span>
                                </div>
                            </td>
                            <td class="small text-muted text-truncate" style="max-width: 200px;">
                                <?= htmlspecialchars($job->message ?? '') ?>
                            </td>
                            <td class="small"><?= $job->started_at ?? '-' ?></td>
                            <td class="pe-3 text-muted small"><?= $job->created_at ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($jobs)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-search mb-3 d-block fa-2x opacity-25"></i>
                                No jobs found
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
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
