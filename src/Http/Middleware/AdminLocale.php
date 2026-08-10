<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Middleware;

use Closure;
use Dskripchenko\LaravelAdmin\Theme\LocaleResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Резолвер локали admin-панели.
 *
 * Делегирует к LocaleResolver, устанавливает app()->setLocale() для текущего
 * request'а. Приоритезация: query?locale → X-Admin-Locale → user.locale →
 * cookie admin_locale → Accept-Language → config('admin.ui.default_locale').
 */
final class AdminLocale
{
    /**
     * Источники локали, о которых обязан знать кэширующий посредник.
     *
     * `Cookie` здесь не перестраховка: два из пяти источников —
     * куки (сессия, из которой берётся user.locale, и admin_locale).
     */
    private const VARY = ['Accept-Language', LocaleResolver::HEADER, 'Cookie'];

    public function __construct(private readonly LocaleResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolver->resolve($request);
        app()->setLocale($locale);

        $response = $next($request);

        // Ответ зависит от языка: шелл несёт инлайн-bootstrap со строками,
        // манифест — подписи ресурсов и меню. Без Vary любой обратный прокси
        // или CDN перед панелью раздаст язык одного посетителя другому — тот
        // же класс ошибки, что кэш манифеста под чужим ключом, только этажом
        // выше. У самого сервиса прокси нет, но коробку и кластер клиент
        // ставит за своим.
        // Одной строкой, а не тремя заголовками: три значения через
        // setVary(..., replace: false) — валидный HTTP, но простые прокси
        // и часть CDN читают только первый. Чужой Vary при этом сохраняем.
        $existing = array_filter(array_map(
            'trim',
            explode(',', (string) $response->headers->get('Vary', '')),
        ));
        $merged = array_values(array_unique([...$existing, ...self::VARY]));
        $response->headers->set('Vary', implode(', ', $merged));

        return $response;
    }
}
