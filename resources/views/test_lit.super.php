~setup {
    $title = "Lit Component Test Suite";
}

@section('content')
<div class="container mt-5">
    <h1>Lit Integration Test Suite</h1>
    <p class="lead">Testing the offline Lit.dev integration with Import Maps and ESM.</p>

    <hr>

    <div class="row">
        <div class="col-md-6">
            <lit-test-list></lit-test-list>
        </div>
        <div class="col-md-6">
            <lit-test-counter></lit-test-counter>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <lit-test-fetch></lit-test-fetch>
        </div>
        <div class="col-md-6">
            <lit-test-offline></lit-test-offline>
        </div>
    </div>
</div>

<script type="module" src="/assets/js/components/LitTestList.js"></script>
<script type="module" src="/assets/js/components/LitTestCounter.js"></script>
<script type="module" src="/assets/js/components/LitTestFetch.js"></script>
<script type="module" src="/assets/js/components/LitTestOffline.js"></script>
@endsection
