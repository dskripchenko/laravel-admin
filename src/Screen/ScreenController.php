<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Screen;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Универсальный controller для произвольных Screen.
 *
 * URL: `/api/admin/{slug}/{action}` где `slug` — Screen::slug().
 *
 * Реализует два action'а:
 *   - GET  /state           → compile()
 *   - POST /runMethod       → диспатч command-методов Screen
 *
 * Per-Screen middleware/permission привязываются через ScreenCompiler.
 */
final class ScreenController extends ApiController
{
    public function __construct(private readonly ScreenRegistry $registry) {}

    /**
     * Скомпилировать снапшот Screen'а: state + layout + commandBar + meta.
     *
     * Принимает произвольные query-параметры — они проксируются в Screen::query()
     * как именованные аргументы (whitelist'а нет, Screen сам валидирует).
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ScreenStateResponse}
     */
    public function state(Request $request): JsonResponse
    {
        $screen = $this->currentScreen();
        if ($screen instanceof JsonResponse) {
            return $screen;
        }
        $params = self::extractQueryParams($request, $screen);

        return $this->success($screen->compile(...$params));
    }

    /**
     * Вызвать command-метод Screen'а.
     *
     * Формат body:
     *   {
     *     "method": "send",
     *     "payload": {...form-state...},  // опц.
     *     "parameters": [..]              // опц., если метод принимает позиционные аргументы
     *   }
     *
     * Метод должен быть public, не static, не входить в RESERVED_METHODS.
     * Возвращаемое значение метода:
     *   - JsonResponse  → проксируется как есть
     *   - array         → завернётся в `success(...)`
     *   - null/void     → success(['ok' => true])
     *
     * @input string $method
     * @input object|null $payload
     * @input array|null $parameters
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ScreenMethodResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function runMethod(Request $request): JsonResponse
    {
        $screen = $this->currentScreen();
        if ($screen instanceof JsonResponse) {
            return $screen;
        }

        $method = (string) $request->input('method', '');
        if ($method === '') {
            return $this->error([
                'errorKey' => 'screen_method_missing',
                'message' => '`method` is required',
            ], Response::HTTP_BAD_REQUEST);
        }
        if (! $screen->isCallableMethod($method)) {
            return $this->error([
                'errorKey' => 'screen_method_not_callable',
                'message' => "Method `{$method}` is not callable on screen `".$screen::slug().'`',
            ], Response::HTTP_NOT_FOUND);
        }

        $args = self::resolveArguments($request);

        /** @var mixed $result */
        $result = $screen->{$method}(...$args);

        if ($result instanceof JsonResponse) {
            return $result;
        }
        if (is_array($result)) {
            return $this->success(self::normalizeMethodPayload($result));
        }

        return $this->success(self::normalizeMethodPayload([]));
    }

    /**
     * Дефолтный shape payload'а ответа runMethod (см. ScreenMethodPayload schema).
     *
     * Разрешённые ключи: state, layouts, alerts, redirect_url, refresh,
     * download_url, message. Всё остальное складывается в `extra`, чтобы
     * Screen-методы могли возвращать произвольные данные без потери совместимости.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private static function normalizeMethodPayload(array $result): array
    {
        $known = ['state', 'layouts', 'alerts', 'redirect_url', 'refresh', 'download_url', 'message'];

        $payload = [
            'state' => (object) ($result['state'] ?? []),
            'layouts' => (object) ($result['layouts'] ?? []),
            'alerts' => $result['alerts'] ?? [],
            'redirect_url' => $result['redirect_url'] ?? null,
            'refresh' => (bool) ($result['refresh'] ?? false),
            'download_url' => $result['download_url'] ?? null,
            'message' => (string) ($result['message'] ?? 'OK'),
        ];

        $extra = array_diff_key($result, array_flip($known));
        if ($extra !== []) {
            $payload['extra'] = $extra;
        }

        return $payload;
    }

    private function currentScreen(): Screen|JsonResponse
    {
        /** @var string|null $key */
        $key = ApiRequest::getApiControllerKey();
        $key = (string) ($key ?? '');

        $class = $this->registry->get($key);
        if ($class === null) {
            return $this->error([
                'errorKey' => 'screen_not_registered',
                'message' => "Screen `{$key}` is not registered",
            ], Response::HTTP_NOT_FOUND);
        }

        /** @var Screen $screen */
        $screen = app($class);

        return $screen;
    }

    /**
     * Резолвит позиционные аргументы для command-метода:
     *   - если в body есть массив `parameters` — берём их
     *   - иначе fallback: один аргумент = `payload` (объект состояния формы)
     *
     * @return list<mixed>
     */
    private static function resolveArguments(Request $request): array
    {
        if ($request->has('parameters') && is_array($request->input('parameters'))) {
            /** @var list<mixed> $params */
            $params = array_values((array) $request->input('parameters'));

            return $params;
        }

        if ($request->has('payload')) {
            return [$request->input('payload')];
        }

        return [];
    }

    /**
     * Передаём в `state` action GET-параметры, которые не являются
     * служебными (`_`), как массив значений в Screen::query().
     *
     * @return list<mixed>
     */
    private static function extractQueryParams(Request $request, Screen $screen): array
    {
        unset($screen);
        /** @var array<string, mixed> $query */
        $query = $request->query();
        if ($query === []) {
            return [];
        }

        return array_values(array_filter(
            $query,
            static fn (mixed $_, string $key): bool => ! str_starts_with($key, '_'),
            ARRAY_FILTER_USE_BOTH,
        ));
    }
}
