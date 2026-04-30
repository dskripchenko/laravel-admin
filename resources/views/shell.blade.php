{{-- SPA-оболочка. Один Blade на все admin-роуты. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="{{ $bootstrap['theme'] ?? 'light' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $brand['name'] ?? config('admin.brand.name') }}</title>

    @if($brand['favicon'] ?? false)
        <link rel="icon" href="{{ $brand['favicon'] }}">
    @endif

    {{-- Стили SPA --}}
    @foreach($assets['css'] ?? [] as $css)
        <link rel="stylesheet" href="{{ $css }}">
    @endforeach

    {{-- Bootstrap data --}}
    @if($strategy === 'inline')
        <script @if($cspNonce) nonce="{{ $cspNonce }}" @endif>
            window.__ADMIN_BOOTSTRAP__ = @json($bootstrap);
        </script>
    @endif

    {{-- Скрипты SPA --}}
    @foreach($assets['js'] ?? [] as $js)
        <script type="module" src="{{ $js }}" @if($cspNonce) nonce="{{ $cspNonce }}" @endif></script>
    @endforeach
</head>
<body>
    <div id="admin-app"></div>

    @if($strategy === 'xhr')
        {{-- SPA сама дёрнет /api/admin/system/bootstrap при старте --}}
    @endif
</body>
</html>
