~setup {
    $id = $id ?? '';
    $title = $title ?? '';
    $description = $description ?? '';
    $icon = $icon ?? 'fa-tools';
    $supports_chunking = $supports_chunking ?? false;
}

<div class="col-md-6 col-lg-4">
    <div class="card service-card h-100 border-0 shadow-sm transition-hover">
        <div class="card-body p-4">
            <div class="d-flex align-items-center mb-3">
                <div class="bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 56px; height: 56px;">
                    <i class="fas {{ $icon }} text-primary fs-4"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0">{{ $title }}</h5>
                    @if($supports_chunking)
                        <span class="badge bg-success bg-opacity-10 text-success">
                            <i class="bi bi-lightning-charge me-1"></i> Fast Upload
                        </span>
                    @endif
                </div>
            </div>

            <p class="card-text text-muted mb-4">
                {{ $description }}
            </p>

            <div class="d-flex justify-content-between align-items-center">
                <a href="/services/{{ $id }}" class="btn btn-primary" @prefetch>
                    Use Service <i class="bi bi-arrow-right ms-1"></i>
                </a>

                <button class="btn btn-link text-muted" type="button" data-bs-toggle="collapse" data-bs-target="#info-{{ $id }}" aria-expanded="false">
                    <i class="bi bi-info-circle"></i>
                </button>
            </div>

            <!-- Additional Info -->
            <div class="collapse mt-3" id="info-{{ $id }}">
                <div class="bg-light rounded-3 p-3">
                    <small class="text-muted">
                        <strong>ID:</strong> {{ $id }}<br>
                        <strong>Chunked Upload:</strong> {{ $supports_chunking ? 'Yes' : 'No' }}
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.transition-hover {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}
.transition-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
}
</style>
