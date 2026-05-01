<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Import;

use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * 4-step Import Wizard backend:
 *   1. upload    — multipart upload файла → возвращает source_path.
 *   2. preview   — headers + sample + auto-mapping suggestions.
 *   3. start     — создаёт ImportProcess + запускает (sync или async).
 *   4. status    — current ImportProcess state.
 */
final class ImportController extends ApiController
{
    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly ImportPreviewService $previewService,
        private readonly ImportRunner $runner,
    ) {}

    /**
     * Загрузить файл импорта на disk.
     *
     * @input file $file
     * @input string $resource Resource slug куда импортируем.
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ImportUploadResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function upload(Request $request): JsonResponse
    {
        $maxKb = (int) config('admin.uploads.max_kilobytes', 51200);
        $request->validate([
            'file' => ['required', 'file', 'max:'.$maxKb],
            'resource' => ['required', 'string'],
        ]);

        $resourceSlug = (string) $request->input('resource');
        if (! $this->resources->has($resourceSlug)) {
            return $this->error([
                'errorKey' => 'unknown_resource',
                'message' => "Resource `{$resourceSlug}` is not registered",
            ], 422);
        }

        $disk = (string) config('admin.imports.disk', 'local');
        $path = $request->file('file')->store('imports', $disk);

        return $this->success([
            'disk' => $disk,
            'path' => $path,
        ]);
    }

    /**
     * Получить preview загруженного файла + auto-mapping.
     *
     * @input string $resource
     * @input string $path
     * @input string ?$disk
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ImportPreviewResponse}
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'resource' => ['required', 'string'],
            'path' => ['required', 'string'],
            'disk' => ['nullable', 'string'],
        ]);

        $resource = $this->resources->resolve($data['resource']);
        if ($resource === null) {
            return $this->error([
                'errorKey' => 'unknown_resource',
                'message' => "Resource `{$data['resource']}` not registered",
            ], 422);
        }

        $disk = (string) ($data['disk'] ?? config('admin.imports.disk', 'local'));
        $preview = $this->previewService->preview($disk, $data['path']);
        $autoMapping = ColumnMapper::autoMap($preview['headers'], $resource->fields());

        return $this->success([
            'headers' => $preview['headers'],
            'sample' => $preview['sample'],
            'total' => $preview['total'],
            'format' => $preview['format'],
            'auto_mapping' => $autoMapping,
        ]);
    }

    /**
     * Создать ImportProcess + запустить sync (default).
     *
     * @input string $resource
     * @input string $path
     * @input object $mapping
     * @input string ?$disk
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ImportStartResponse}
     */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'resource' => ['required', 'string'],
            'path' => ['required', 'string'],
            'mapping' => ['required', 'array'],
            'disk' => ['nullable', 'string'],
        ]);

        if (! $this->resources->has($data['resource'])) {
            return $this->error([
                'errorKey' => 'unknown_resource',
                'message' => "Resource `{$data['resource']}` not registered",
            ], 422);
        }

        $disk = Storage::disk((string) ($data['disk'] ?? config('admin.imports.disk', 'local')));
        if (! $disk->exists($data['path'])) {
            return $this->error([
                'errorKey' => 'file_missing',
                'message' => "Source file `{$data['path']}` not found",
            ], 422);
        }

        $owner = $this->user();
        $process = ImportProcess::create([
            'resource_slug' => $data['resource'],
            'source_path' => $data['path'],
            'mapping' => $data['mapping'],
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->getKey(),
        ]);

        $this->runner->run($process->id);

        return $this->success([
            'process' => $this->serialize($process->refresh()),
        ]);
    }

    /**
     * Получить статус ImportProcess.
     *
     * @input integer $id
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ImportStatusResponse}
     * @response 404 {NotFoundErrorResponse}
     */
    public function status(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'integer']]);
        $process = ImportProcess::query()->find($data['id']);
        if ($process === null) {
            return $this->error([
                'errorKey' => 'not_found',
                'message' => 'ImportProcess not found',
            ], 404);
        }

        return $this->success(['process' => $this->serialize($process)]);
    }

    private function user(): ?Model
    {
        $guard = (string) config('admin.auth.guard', 'admin');
        $user = Auth::guard($guard)->user();

        return $user instanceof Model ? $user : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ImportProcess $p): array
    {
        return [
            'id' => $p->id,
            'resource_slug' => $p->resource_slug,
            'status' => $p->status,
            'processed_count' => $p->processed_count,
            'created_count' => $p->created_count,
            'updated_count' => $p->updated_count,
            'error_count' => $p->error_count,
            'errors' => $p->errors,
            'started_at' => $p->started_at?->toIso8601String(),
            'completed_at' => $p->completed_at?->toIso8601String(),
        ];
    }
}
