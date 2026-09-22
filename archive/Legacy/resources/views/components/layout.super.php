<div class="layout">
    <header>{!! $header ?? 'Default Header' !!}</header>
    <main>{!! $slot !!}</main>
    <footer>{!! $footer ?? 'Default Footer' !!}</footer>
</div>