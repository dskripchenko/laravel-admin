{{-- The panel's API reference: @dskripchenko/openapi's standalone page. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('admin.brand.name', 'Admin') }} — API</title>
    <style @if($cspNonce) nonce="{{ $cspNonce }}" @endif>
        body { margin: 0; font-family: system-ui, -apple-system, sans-serif; }
        .api-doc-fallback { max-width: 640px; margin: 64px auto; padding: 0 24px; color: #374151; line-height: 1.6; }
        .api-doc-fallback h1 { font-size: 20px; }
        .api-doc-fallback ul { padding-left: 20px; }
    </style>
</head>
<body>
    {{-- Fallback: when the script does not load (no CDN, offline), the page
         does not stay blank — the raw OpenAPI specs are listed by the server. --}}
    <div id="api-doc-fallback" class="api-doc-fallback">
        <h1>{{ config('admin.brand.name', 'Admin') }} — API</h1>
        <p>{{ __('The interactive reference is loading… If it does not show up, its script is unavailable. The raw OpenAPI specs:') }}</p>
        <ul>
            @foreach($sources as $src)
                <li><a href="{{ $src['url'] }}">{{ $src['title'] ?: $src['slug'] }}</a></li>
            @endforeach
        </ul>
    </div>

    <div id="app"></div>
    <script @if($cspNonce) nonce="{{ $cspNonce }}" @endif src="{{ $script }}"></script>
    <script @if($cspNonce) nonce="{{ $cspNonce }}" @endif>
        // A JS literal from the Blade json directive: quotes and apostrophes in
        // a version's title cannot break the script.
        var options = @json($options);
        if (typeof DskOpenApi !== 'undefined' && DskOpenApi.createApiReference) {
            var fallback = document.getElementById('api-doc-fallback');
            if (fallback) { fallback.remove(); }
            DskOpenApi.createApiReference('#app', options);
        }
    </script>
</body>
</html>
