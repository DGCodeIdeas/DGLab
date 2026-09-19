@extends('layouts.shell')

@section('content')
<div class="container mt-5">
    <h1>Morph Test</h1>

    <div id="morph-target" @fragment('morph-target')>
        <p>Current count: {{$count}}</p>
        <button s-on:click="increment" class="btn btn-primary" id="increment-btn">Increment</button>
    </div>

    <div id="static-area">
        <p>This should not change: <span id="static-time">{{time()}}</span></p>
    </div>
</div>
@endsection

~setup {
    $count = 0;
    @persist($count);

    $increment = function() use (&$count) {
        $count++;
    };
}
