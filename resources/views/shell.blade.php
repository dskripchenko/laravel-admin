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
    @else
        {{-- The LAdmin mark, inline, when the host sets no favicon of its own. --}}
        <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='5.3' fill='%2318181b'/%3E%3Cpath d='M5.5 11V5.5H11M18.5 13v5.5H13' fill='none' stroke='%23fff' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'/%3E%3Crect x='9.4' y='9.4' width='5.2' height='5.2' rx='1.6' fill='%232dd4bf'/%3E%3C/svg%3E">
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
                        // Having reached zero the countdown stops and does NOT
                        // go negative: the moment has come, and what happens
                        // next is not for this page to decide.
                        if (left > 0) setTimeout(tick, 1000);
                    }
                    tick();
                })();
            </script>
        @endif
    @endif

    @if($assets['stale'] ?? false)
        {{-- The package was updated without republishing its frontend: the
             SPA may not match the API it talks to. --}}
        <div id="admin-assets-stale" role="status" style="
            padding:.5rem 1rem;background:#fee2e2;color:#7f1d1d;
            font:500 14px/1.4 system-ui,-apple-system,'Segoe UI',sans-serif;
            border-bottom:1px solid #fca5a5;text-align:center">
            {{ __('Файлы админки устарели: выполните') }} <code>php artisan admin:publish</code>
        </div>
    @endif

    @if(($notice['text'] ?? '') !== '' || ($assets['stale'] ?? false))
        {{-- The banners above sit outside the SPA, and the shell fills the
             viewport below them: it learns their height through
             --admin-banner-height, or its bottom (the footer, the last rows of
             a page) would be pushed off screen. --}}
        <script @if($cspNonce) nonce="{{ $cspNonce }}" @endif>
            (function () {
                var ids = ['admin-notice', 'admin-assets-stale'];
                function measure() {
                    var h = 0;
                    ids.forEach(function (id) {
                        var el = document.getElementById(id);
                        if (el) h += el.getBoundingClientRect().height;
                    });
                    document.documentElement.style.setProperty('--admin-banner-height', h + 'px');
                }
                measure();
                if (typeof ResizeObserver !== 'undefined') {
                    var ro = new ResizeObserver(measure);
                    ids.forEach(function (id) {
                        var el = document.getElementById(id);
                        if (el) ro.observe(el);
                    });
                } else {
                    window.addEventListener('resize', measure);
                }
            })();
        </script>
    @endif

    <div id="admin-app">
        @if($assets['missing'] ?? false)
            {{-- No frontend to load: say what to do instead of a blank page. --}}
            <div style="max-width:36rem;margin:15vh auto;padding:0 1rem;
                font:15px/1.5 system-ui,-apple-system,'Segoe UI',sans-serif;color:#1f2937">
                <h1 style="font-size:1.25rem;margin:0 0 .5rem">{{ __('Фронтенд админки не найден') }}</h1>
                <p style="margin:0 0 .5rem">{{ __('Опубликуйте готовую сборку:') }}</p>
                <pre style="background:#f3f4f6;padding:.5rem .75rem;border-radius:.375rem">php artisan admin:publish</pre>
                <p style="margin:.5rem 0 0">{{ __('Или подключите свою Vite-сборку через config(\'admin.assets\').') }}</p>
            </div>
        @endif
    </div>

    @if($strategy === 'xhr')
        {{-- SPA сама дёрнет /api/admin/system/bootstrap при старте --}}
    @endif
</body>
</html>
