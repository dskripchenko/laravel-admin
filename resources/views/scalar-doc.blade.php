{{-- Scalar UI для admin OpenAPI spec'а. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('admin.brand.name', 'Admin') }} — API Reference</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body>
    @php
        $configurationJson = json_encode([
            'theme' => $theme,
            'layout' => 'modern',
            'showSidebar' => true,
            'sources' => $sources,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    @endphp

    {{-- Scalar mounting point — атрибуты из props --}}
    <script id="api-reference"
        @if($cspNonce) nonce="{{ $cspNonce }}" @endif
        data-url="{{ $sources[0]['url'] ?? '' }}"
        data-configuration='{{ $configurationJson }}'>
    </script>

    {{-- Scalar UI bundle. CDN — jsdelivr, lazy. Хост-проект может проксировать локально. --}}
    <script
        @if($cspNonce) nonce="{{ $cspNonce }}" @endif
        src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
</body>
</html>
