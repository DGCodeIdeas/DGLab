<?php $this->layout('layouts/master', ['title' => 'Admin - Logs']) ?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-1">Application Logs</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="/admin">Dashboard</a></li>
                    <li class="breadcrumb-item active">Logs</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card border-0 shadow-sm bg-dark text-light">
        <div class="card-header bg-dark border-secondary py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-secondary">Recent Log Entries</h5>
            <button onclick="window.location.reload()" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-sync-alt me-1"></i> Refresh
            </button>
        </div>
        <div class="card-body p-0">
            <div class="log-container p-3" style="max-height: 600px; overflow-y: auto; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-size: 0.85rem; line-height: 1.5;">
                <?php foreach ($logs as $log): ?>
                    <div class="log-line mb-2 border-bottom border-secondary pb-1 opacity-75 hover-opacity-100">
                        <?= $this->formatLogLine($log) ?>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                    <div class="text-center py-5 text-muted italic">No logs found for today</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.log-line:hover { opacity: 1 !important; }
.log-timestamp { color: #6c757d; }
.log-level-error, .log-level-critical { color: #ff6b6b; font-weight: bold; }
.log-level-warning { color: #ffd93d; }
.log-level-info { color: #4dabf7; }
.log-level-debug { color: #adb5bd; }
.log-message { color: #e9ecef; }
</style>

<?php
$this->formatLogLine = function($line) {
    // Basic parser for "[timestamp] app.LEVEL: message {"context"}"
    if (preg_match('/^\[(.*?)\] (.*?)\.(.*?): (.*?) (.*)$/', trim($line), $matches)) {
        $timestamp = $matches[1];
        $channel = $matches[2];
        $level = $matches[3];
        $message = $matches[4];
        $context = $matches[5];

        $levelClass = 'log-level-' . strtolower($level);

        return sprintf(
            '<span class="log-timestamp">[%s]</span> <span class="text-secondary">%s.</span><span class="%s">%s</span>: <span class="log-message">%s</span> <span class="text-muted small">%s</span>',
            htmlspecialchars($timestamp),
            htmlspecialchars($channel),
            $levelClass,
            htmlspecialchars($level),
            htmlspecialchars($message),
            htmlspecialchars($context)
        );
    }
    return htmlspecialchars($line);
};
?>
