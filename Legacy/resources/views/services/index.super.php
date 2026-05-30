@extends('layouts.shell')

@section('content')
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
                    <i class="bi bi-tools me-1"></i> {{ count($services) }} Available
                </span>
            </div>
        </div>
    </div>
</section>

<!-- Services Grid -->
<section class="py-5">
    <div class="container">
        @if(empty($services))
            <!-- Empty State -->
            <div class="text-center py-5">
                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 120px; height: 120px;">
                    <i class="bi bi-tools text-muted display-4"></i>
                </div>
                <h3 class="h4 mb-2">No Services Available</h3>
                <p class="text-muted mb-4">Services will appear here once they're configured.</p>
            </div>
        @else
            <div class="row g-4">
                @foreach($services as $svc)
                    <s:ui.service-card
                        :id="$svc['id']"
                        :title="$svc['name']"
                        :description="$svc['description']"
                        :icon="$svc['icon']"
                        :supports_chunking="$svc['supports_chunking']"
                    />
                @endforeach
            </div>
        @endif
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
                <a href="/docs/api" class="btn btn-outline-primary" @prefetch>
                    <i class="bi bi-code-slash me-2"></i> API Documentation
                </a>
            </div>
        </div>
    </div>
</section>
@endsection

~setup {
    $title = 'Services - DGLab';
    $services = $services ?? [];
}
