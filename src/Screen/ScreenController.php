<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Screen;

use Dskripchenko\LaravelAdmin\Action\ActionLocator;
use Dskripchenko\LaravelAdmin\I18n\Localize;
use Dskripchenko\LaravelAdmin\Resource\ActionFailedException;
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
 * It implements three actions:
 *   - GET  /state           → compile()
 *   - POST /runMethod       → dispatches the screen's command methods
 *   - POST /listener        → re-renders a Listener layout
 *
 * Per-screen middleware and permissions are attached by ScreenCompiler.
 */
final class ScreenController extends ApiController
{
    public function __construct(private readonly ScreenRegistry $registry) {}

    /**
     * Compiles a screen snapshot: state, layout, command bar and meta.
     *
     * It accepts arbitrary query parameters and passes their values into
     * Screen::query() as positional arguments, in the order of the query
     * string (keys starting with `_` are dropped) — there is no whitelist,
     * the screen validates them. By name they stay at hand through request().
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
     * @input object ?$payload
     * @input array ?$parameters
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ScreenMethodResponse}
     * @response 403 {ForbiddenErrorResponse} An action that calls the method requires a permission the user lacks
     * @response 422 {ValidationErrorResponse} Also `action_failed`, when the method throws ActionFailedException
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

        // A method bound to a button with a permission() or a canSee() runs
        // only for the users that button is shown to. A method no action
        // names stays callable as before: the screen's own permission and the
        // method's own checks guard it.
        $declared = ActionLocator::byMethod([...$screen->commandBar(), ...$screen->layout()], $method);
        if (! ActionLocator::permits($declared)) {
            return $this->error(ActionLocator::forbidden($declared), Response::HTTP_FORBIDDEN);
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

        try {
            /** @var mixed $result */
            $result = $screen->{$method}(...$args);
        } catch (ActionFailedException $e) {
            // A refusal on the merits, as with a resource action: the method
            // explains why it could not finish, and the panel shows that
            // text — not a bare 500.
            return $this->error([
                'errorKey' => 'action_failed',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($result instanceof JsonResponse) {
            return $result;
        }
        if (is_array($result)) {
            return $this->success(self::normalizeMethodPayload($result));
        }

        return $this->success(self::normalizeMethodPayload([]));
    }

    /**
     * Re-renders one of the screen's Listener layouts against the form state.
     *
     * The listener is looked up by its id among the listeners the screen's
     * `layout()` declares; its handler, when it has one, runs with the state
     * and returns a patch, and the listener's children are rendered with the
     * patched state. Nothing else on the screen can be reached this way.
     *
     * The body looks like:
     *   {
     *     "listener": "listener-1a2b3c4d5e6f",
     *     "state": {...form-state...}
     *   }
     *
     * @input string $listener The listener's id, as the layout serialized it
     * @input object ?$state The current form state
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ListenerResponse}
     * @response 404 {NotFoundErrorResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function listener(Request $request): JsonResponse
    {
        $screen = $this->currentScreen();
        if ($screen instanceof JsonResponse) {
            return $screen;
        }

        return \Dskripchenko\LaravelAdmin\Layout\ListenerResponder::respond(
            $this,
            $screen,
            $screen->layout(),
            $request,
        );
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
            'alerts' => self::normalizeAlerts($result['alerts'] ?? []),
            'redirect_url' => $result['redirect_url'] ?? null,
            'refresh' => (bool) ($result['refresh'] ?? false),
            'download_url' => $result['download_url'] ?? null,
            // No message means no banner: the panel shows one only when there is text.
            'message' => (string) ($result['message'] ?? ''),
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
     * The panel shows each alert as a toast. Its text and title go through
     * the JSON translations of the request's locale — idempotent, so a string
     * the screen already translated comes back as it is.
     *
     * @return list<mixed>
     */
    private static function normalizeAlerts(mixed $alerts): array
    {
        if (! is_array($alerts)) {
            return [];
        }

        $out = [];
        foreach (array_values($alerts) as $alert) {
            if (is_array($alert)) {
                // `level` and `variant` read naturally for a toast and are
                // what people write; the schema's key is `type`.
                if (! isset($alert['type'])) {
                    $alias = $alert['level'] ?? $alert['variant'] ?? null;
                    $alert['type'] = is_string($alias) && $alias !== '' ? $alias : 'info';
                }
                unset($alert['level'], $alert['variant']);
                foreach (['message', 'title'] as $key) {
                    if (isset($alert[$key]) && is_string($alert[$key])) {
                        $alert[$key] = Localize::string($alert[$key]);
                    }
                }
            }
            $out[] = $alert;
        }

        return $out;
    }

    /**
     * Shapes the link under the message. Accepted:
     *   - `['url' => …, 'label' => …]` — the canonical shape;
     *   - `['href' => …, 'text' => …]` — `href` for `url`, `text` or `title`
     *     for `label`;
     *   - `[$url, $label]` — a positional pair;
     *   - a bare URL string — the label defaults to «Открыть».
     * A link without a URL is dropped; the label is translated.
     *
     * @return array{url: string, label: string}|null
     */
    private static function normalizeMessageLink(mixed $link): ?array
    {
        if (is_string($link)) {
            $link = ['url' => $link];
        }
        if (! is_array($link)) {
            return null;
        }
        if (array_is_list($link)) {
            $link = ['url' => $link[0] ?? null, 'label' => $link[1] ?? null];
        }

        $url = $link['url'] ?? $link['href'] ?? null;
        $label = $link['label'] ?? $link['text'] ?? $link['title'] ?? null;

        if (! is_string($url) || trim($url) === '') {
            return null;
        }
        if (! is_string($label) || trim($label) === '') {
            $label = __('Открыть');
        }

        return ['url' => $url, 'label' => (string) Localize::string($label)];
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
