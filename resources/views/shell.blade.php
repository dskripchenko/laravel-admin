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
    @if(($notice['text'] ?? '') !== '')
        {{-- Плашка установки. Печатается до #admin-app и вне его: она про
             установку, а не про экран, и обязана оставаться на месте, если
             SPA не поднялась.

             Стили инлайном, без обращения к теме панели: таблица стилей SPA
             приезжает отдельным файлом, и плашка, ждущая её, в этот самый
             момент невидима. --}}
        <div id="admin-notice" role="status" style="
            display:flex;gap:.75rem;align-items:center;justify-content:center;flex-wrap:wrap;
            padding:.5rem 1rem;background:#fef3c7;color:#78350f;
            font:500 14px/1.4 system-ui,-apple-system,'Segoe UI',sans-serif;
            border-bottom:1px solid #fcd34d">
            <span>{{ $notice['text'] }}</span>

            @if(($notice['countdown_to'] ?? null))
                <span>
                    @if(($notice['countdown_label'] ?? '') !== ''){{ $notice['countdown_label'] }} @endif
                    {{-- Отсчёт считает браузер от серверной метки, а не от
                         своих часов: они у посетителей расходятся, и чужое
                         «осталось 40 минут» на деле означало бы что угодно. --}}
                    <b id="admin-notice-left" data-until="{{ $notice['countdown_to'] }}">—</b>
                </span>
            @endif

            @if(($notice['href'] ?? '') !== '')
                <a href="{{ $notice['href'] }}" style="color:inherit">{{ __('Подробнее') }}</a>
            @endif
        </div>

        @if(($notice['countdown_to'] ?? null))
            <script @if($cspNonce) nonce="{{ $cspNonce }}" @endif>
                (function () {
                    var el = document.getElementById('admin-notice-left');
                    if (!el) return;
                    var until = Date.parse(el.dataset.until);
                    if (isNaN(until)) { el.textContent = ''; return; }
                    function tick() {
                        var left = Math.max(0, Math.floor((until - Date.now()) / 1000));
                        var m = Math.floor(left / 60), s = left % 60;
                        el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
                        // Дойдя до нуля, отсчёт останавливается и НЕ уходит в
                        // минус: момент наступил, а что дальше — решает не эта
                        // страница.
                        if (left > 0) setTimeout(tick, 1000);
                    }
                    tick();
                })();
            </script>
        @endif
    @endif

    <div id="admin-app"></div>

    @if($strategy === 'xhr')
        {{-- SPA сама дёрнет /api/admin/system/bootstrap при старте --}}
    @endif
</body>
</html>
