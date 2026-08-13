<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Screen;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The universal controller behind any screen.
 *
 * The URL is `/api/admin/{slug}/{action}`, where `slug` is Screen::slug().
 *
 * It implements two actions:
 *   - GET  /state           → compile()
 *   - POST /runMethod       → dispatches the screen's command methods
 *
 * Per-screen middleware and permissions are attached by ScreenCompiler.
 */
final class ScreenController extends ApiController
{
    public function __construct(private readonly ScreenRegistry $registry) {}

    /**
     * Compiles a screen snapshot: state, layout, command bar and meta.
     *
     * It accepts arbitrary query parameters and passes them into Screen::query()
     * as named arguments — there is no whitelist, the screen validates them.
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
     * Calls one of the screen's command methods.
     *
     * The body looks like:
     *   {
     *     "method": "send",
     *     "payload": {...form-state...},  // optional
     *     "parameters": [..]              // optional, when the method takes positional arguments
     *   }
     *
     * The method must be public, must not be static and must not be one of
     * RESERVED_METHODS. What it returns is treated as follows:
     *   - a JsonResponse → passed through as is
     *   - an array       → wrapped into `success(...)`
     *   - null or void   → success(['ok' => true])
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

        // A screen method declares a parameter, usually `array $state`, while
        // the request may well arrive without a `payload` — from an
        // integrator, from curl, from a typo in the code. That used to produce
        // an ArgumentCountError and a bare 500 on every button of every
        // screen; now we either substitute an empty state (which is exactly
        // "the button was pressed on an empty form", and the method answers
        // with its own validation) or say plainly what is missing.
        $required = (new \ReflectionMethod($screen, $method))->getNumberOfRequiredParameters();
        if (count($args) < $required) {
            if ($required === 1 && $args === []) {
                $args = [[]];
            } else {
                return $this->error([
                    'errorKey' => 'screen_method_arguments_missing',
                    'message' => "Method `{$method}` expects {$required} argument(s); pass them in `payload` or `parameters`",
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

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
     * The default shape of runMethod's response payload — see the
     * ScreenMethodPayload schema.
     *
     * The allowed keys are state, layouts, alerts, redirect_url, refresh,
     * download_url, message and message_link. Everything else goes into
     * `extra`, so that screen methods can return arbitrary data without
     * breaking compatibility.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private static function normalizeMethodPayload(array $result): array
    {
        $known = ['state', 'layouts', 'alerts', 'redirect_url', 'refresh', 'download_url', 'message', 'message_link'];

        $payload = [
            'state' => (object) ($result['state'] ?? []),
            'layouts' => (object) ($result['layouts'] ?? []),
            'alerts' => $result['alerts'] ?? [],
            'redirect_url' => $result['redirect_url'] ?? null,
            'refresh' => (bool) ($result['refresh'] ?? false),
            'download_url' => $result['download_url'] ?? null,
            'message' => (string) ($result['message'] ?? 'OK'),
            // Where the message leads: a screen that starts background work
            // has somewhere to send the person — the job's own page. Shaped
            // rather than passed through, so a half-filled link never reaches
            // the panel as a dead control.
            'message_link' => self::normalizeMessageLink($result['message_link'] ?? null),
        ];

        $extra = array_diff_key($result, array_flip($known));
        if ($extra !== []) {
            $payload['extra'] = $extra;
        }

        return $payload;
    }

    /**
     * @return array{url: string, label: string}|null
     */
    private static function normalizeMessageLink(mixed $link): ?array
    {
        if (! is_array($link)) {
            return null;
        }

        $url = $link['url'] ?? null;
        $label = $link['label'] ?? null;

        if (! is_string($url) || $url === '' || ! is_string($label) || $label === '') {
            return null;
        }

        return ['url' => $url, 'label' => $label];
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
     * Resolves the positional arguments of a command method:
     *   - when the body carries a `parameters` array, those are used
     *   - otherwise a single argument is passed: `payload`, the form's state
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
     * Passes the non-internal GET parameters — everything but `_` — into
     * Screen::query() as an array of values.
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
