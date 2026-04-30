<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\DelayedProcess;

use Dskripchenko\DelayedProcess\Contracts\ProcessFactoryInterface;
use Dskripchenko\DelayedProcess\Models\DelayedProcess;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Запуск и отслеживание async-actions.
 *
 * Endpoints:
 *   - run(entity, method, params?) — создаёт DelayedProcess, валидируя через
 *     AllowlistRegistrar.
 *   - status(uuid) — текущее состояние process'а (status/progress/data/error).
 */
final class DelayedProcessController extends ApiController
{
    public function __construct(
        private readonly AllowlistRegistrar $allowlist,
    ) {}

    /**
     * Запустить async-action.
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
     * @response 403 {ForbiddenErrorResponse}
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
     * Получить статус running process'а.
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
