<?php $this->section('content') ?>

<!-- Page Header -->
<section class="bg-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="display-5 fw-bold mb-2">Services</h1>
                <p class="lead mb-0 opacity-90">
                    Browse and use our collection of digital processing tools
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <span class="badge bg-white bg-opacity-25 fs-6">
                    <i class="fas fa-tools me-1"></i> <?= count($services) ?> Available
                </span>
            </div>
        </div>
    </div>
</section>

<!-- Services Grid -->
<section class="py-5">
    <div class="container">
        <?php if (empty($services)): ?>
            <!-- Empty State -->
            <div class="text-center py-5">
                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 120px; height: 120px;">
                    <i class="fas fa-tools text-muted display-4"></i>
                </div>
                <h3 class="h4 mb-2">No Services Available</h3>
                <p class="text-muted mb-4">Services will appear here once they're configured.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($services as $svc): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card service-card h-100 border-0 shadow-sm">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 56px; height: 56px;">
                                        <i class="fas <?= htmlspecialchars($svc['icon']) ?> text-primary fa-lg"></i>
                                    </div>
                                    <div>
                                        <h5 class="card-title mb-0"><?= htmlspecialchars($svc['name']) ?></h5>
                                        <?php if ($svc['supports_chunking']): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success">
                                                <i class="fas fa-bolt me-1"></i> Fast Upload
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <p class="card-text text-muted mb-4">
                                    <?= htmlspecialchars($svc['description']) ?>
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="/services/<?= htmlspecialchars($svc['id']) ?>" class="btn btn-primary">
                                        Use Service <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                    
                                    <button class="btn btn-link text-muted" type="button" data-bs-toggle="collapse" data-bs-target="#info-<?= htmlspecialchars($svc['id']) ?>" aria-expanded="false">
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                </div>
                                
                                <!-- Additional Info -->
                                <div class="collapse mt-3" id="info-<?= htmlspecialchars($svc['id']) ?>">
                                    <div class="bg-light rounded-3 p-3">
                                        <small class="text-muted">
                                            <strong>ID:</strong> <?= htmlspecialchars($svc['id']) ?><br>
                                            <strong>Chunked Upload:</strong> <?= $svc['supports_chunking'] ? 'Yes' : 'No' ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- API Access CTA -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h2 class="h4 fw-bold mb-2">Build Your Own Integration</h2>
                <p class="text-muted mb-lg-0">
                    Access all services programmatically through our RESTful API.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="/docs/api" class="btn btn-outline-primary">
                    <i class="fas fa-code me-2"></i> API Documentation
                </a>
            </div>
        </div>
    </div>
</section>

<?php $this->endSection() ?>
