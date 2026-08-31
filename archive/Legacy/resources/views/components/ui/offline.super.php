<div class="container text-center py-5">
    <div class="icon fs-1 mb-4">📡</div>
    <h1 class="fw-bold mb-3">You're Offline</h1>
    <p class="lead mb-4 text-muted">
        It looks like you've lost your internet connection.
        We'll reconnect automatically once you're back online.
    </p>
    <button class="btn btn-primary px-4 py-2" @click="location.reload()">
        Try Again
    </button>
</div>
