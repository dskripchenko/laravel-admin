{{-- Scalar UI для admin OpenAPI spec'а. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('admin.brand.name', 'Admin') }} — API Reference</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, sans-serif; }
        .api-doc-fallback {
            max-width: 640px; margin: 64px auto; padding: 0 24px;
            color: #374151; line-height: 1.6;
        }
        .api-doc-fallback h1 { font-size: 20px; }
        .api-doc-fallback code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; }
        .api-doc-fallback ul { padding-left: 20px; }
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
        // URL Scalar-бандла конфигурируем: host-проект может self-host'ить его
        // локально (напр. для окружений без доступа к внешнему CDN).
        $scalarScript = (string) config('admin.openapi.scalar_script', 'https://cdn.jsdelivr.net/npm/@scalar/api-reference');
    @endphp

    {{-- Fallback: если Scalar-бандл не загрузился (нет CDN / офлайн), страница
         не остаётся белой — показываем прямые ссылки на сырые OpenAPI-спеки.
         Убирается, как только Scalar смонтировал reference. --}}
    <div id="api-doc-fallback" class="api-doc-fallback">
        <h1>{{ config('admin.brand.name', 'Admin') }} — API Reference</h1>
        <p>Интерактивная документация загружается… Если она не появилась —
           рендерер недоступен (нет соединения с CDN). Сырые OpenAPI-спеки:</p>
        <ul>
            @foreach($sources as $src)
                <li><a href="{{ $src['url'] }}">{{ $src['title'] ?? $src['slug'] ?? $src['url'] }}</a></li>
            @endforeach
        </ul>
    </div>

    {{-- Scalar mounting point — атрибуты из props --}}
    <script id="api-reference"
        @if($cspNonce) nonce="{{ $cspNonce }}" @endif
        data-url="{{ $sources[0]['url'] ?? '' }}"
        data-configuration='{{ $configurationJson }}'>
    </script>

    {{-- Scalar UI bundle. URL конфигурируем (admin.openapi.scalar_script). --}}
    <script
        @if($cspNonce) nonce="{{ $cspNonce }}" @endif
        src="{{ $scalarScript }}"
        onload="setTimeout(function(){ var f=document.getElementById('api-doc-fallback'); if(f && document.querySelector('.scalar-app, [class*=scalar]')) f.remove(); }, 1500)"></script>
</body>
</html>
