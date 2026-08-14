@extends('layouts.shell')

@section('content')
<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center min-vh-50">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">
                    Digital Lab Tools
                </h1>
                <p class="lead mb-4 opacity-90">
                    A collection of powerful web-based utilities for processing files and manipulating digital content. Fast, secure, and privacy-focused.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="/services" class="btn btn-light btn-lg px-4" @prefetch>
                        <i class="bi bi-rocket-takeoff me-2"></i> Get Started
                    </a>
                    <a href="/docs" class="btn btn-outline-light btn-lg px-4" @prefetch>
                        <i class="bi bi-book me-2"></i> Documentation
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

~setup {
    $title = 'DGLab - Digital Lab Tools';
}
