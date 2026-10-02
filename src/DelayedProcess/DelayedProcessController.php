<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\DelayedProcess;

use Dskripchenko\DelayedProcess\Contracts\ProcessFactoryInterface;
use Dskripchenko\DelayedProcess\Models\DelayedProcess;
use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\Action\ActionLocator;
use Dskripchenko\LaravelAdmin\Permission\PermissionCheck;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedScreen;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Dskripchenko\LaravelAdmin\Widget\DashboardScreen;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Starting and following async actions.
 *
 * The endpoints:
 *   - run(entity, method, params?) creates a DelayedProcess, validating it
 *     against AllowlistRegistrar and against the permissions of the async
 *     actions that start the handler.
 *   - status(uuid) returns the process's current state: status, progress, data
 *     and error.
 */
final class DelayedProcessController extends ApiController
{
    public function __construct(
        private readonly AllowlistRegistrar $allowlist,
    ) {}

    /**
     * Starts an async action.
     *
     * @input string $entity
     * @input string $method
     * @input array ?$params
     * @input string ?$callback
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {DelayedProcessRunResponse}
     * @response 403 {ForbiddenErrorResponse} The handler is not allowlisted, or the user lacks a permission it requires
     * @response 422 {ValidationErrorResponse}
     */
    public function run(Request $request, ProcessFactoryInterface $factory): JsonResponse
    {
        $data = $request->validate([
            'entity' => ['required', 'string'],
            'method' => ['required', 'string'],
            'params' => ['nullable', 'array'],
            'callback' => ['nullable', 'url'],
        ]);

        if (! $this->allowlist->isAllowed($data['entity'], $data['method'])) {
            return $this->error([
                'errorKey' => 'forbidden',
                'message' => 'This async handler is not allowlisted',
            ], 403);
        }

        // The permission the pair was allowlisted with, then the actions that
        // start it: an AsyncAction with a permission() or a canSee() is a
        // promise that only the users it is shown to can start its handler.
        $missing = PermissionCheck::firstMissing($this->allowlist->permissionsFor($data['entity'], $data['method']));
        if ($missing !== null) {
            return $this->error([
                'errorKey' => 'action_forbidden',
                'message' => __('Доступ запрещён: :permission', ['permission' => $missing]),
            ], 403);
        }
        $declared = ActionLocator::byAsyncHandler(self::declaredActions(), $data['entity'], $data['method']);
        if (! ActionLocator::permits($declared)) {
            return $this->error(ActionLocator::forbidden($declared), 403);
        }

        $params = (array) ($data['params'] ?? []);
        try {
            $process = $factory->make($data['entity'], $data['method'], ...$params);
        } catch (\Throwable $e) {
            return $this->error([
                'errorKey' => 'delayed_run_failed',
                'message' => $e->getMessage(),
            ], 500);
        }

        if (! empty($data['callback'])) {
            $process->callback_url = (string) $data['callback'];
            $process->save();
        }

        return $this->success([
            'uuid' => $process->uuid,
            'status' => $process->status->value,
        ]);
    }

    /**
     * The actions every registered resource and custom screen declares: the
     * resources' actions() and the screens' command bars, where async actions
     * live. A screen whose command bar cannot be built outside its own page
     * is skipped; allowlist the handler with a permission to cover it.
     *
     * @return list<Action>
     */
    private static function declaredActions(): array
    {
        $actions = [];

        $resources = app(ResourceRegistry::class);
        foreach (array_keys($resources->all()) as $slug) {
            $resource = $resources->resolve($slug);
            if ($resource !== null) {
                array_push($actions, ...$resource->actions());
            }
        }

        foreach (app(ScreenRegistry::class)->all() as $class) {
            if (is_subclass_of($class, GeneratedScreen::class) || is_subclass_of($class, DashboardScreen::class)) {
                continue;
            }
            try {
                /** @var Screen $screen */
                $screen = app($class);
                array_push($actions, ...$screen->commandBar());
            } catch (\Throwable) {
                continue;
            }
        }

        return $actions;
    }

    /**
     * Returns the status of a running process.
     *
     * @input string $uuid
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {DelayedProcessStatusResponse}
     * @response 404 {NotFoundErrorResponse}
     */
    public function status(Request $request): JsonResponse
    {
        $data = $request->validate(['uuid' => ['required', 'string']]);
        /** @var DelayedProcess|null $process */
        $process = DelayedProcess::query()->where('uuid', $data['uuid'])->first();

        if ($process === null) {
            return $this->error([
                'errorKey' => 'not_found',
                'message' => 'Process not found',
            ], 404);
        }

        return $this->success([
            'uuid' => $process->uuid,
            'status' => $process->status->value,
            'progress' => $process->progress,
            'attempts' => $process->attempts,
            'started_at' => $process->started_at?->toIso8601String(),
            'duration_ms' => $process->duration_ms,
            'data' => $process->data,
            'error' => $process->error_message,
        ]);
    }
}
