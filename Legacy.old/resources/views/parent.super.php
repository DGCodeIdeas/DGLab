~setup { $name = 'Parent'; }
<div>
    <h1>In Parent: {{ $name }}</h1>
    <s:child />
    <h1>Still in Parent: {{ $name }}</h1>
</div>