<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Import;

use Dskripchenko\LaravelAdmin\Panel\Panels;
use Dskripchenko\LaravelAdmin\Permission\PermissionCheck;
use Dskripchenko\LaravelAdmin\Resource\RecordWriter;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * The backend of the four-step import wizard:
 *   1. upload    — a multipart file upload, returning the source_path.
 *   2. preview   — the headers, a sample and the suggested mapping.
 *   3. start     — creates an ImportProcess and runs it, synchronously or not.
 *   4. status    — the ImportProcess's current state.
 *
 * Every step requires a resource that opts in through importable() and the
 * user's `{permission}.create` on it. The file is read from
 * `admin.imports.disk` only, and only from the user's own upload folder
 * (ImportUploads); a process's status is shown to its owner, or to whoever
 * may import into its resource.
 */
final class ImportController extends ApiController
{
    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly ImportPreviewService $previewService,
        private readonly ImportRunner $runner,
    ) {}

    /**
     * Uploads the import's file onto a disk.
     *
     * @input file $file
     * @input string $resource The slug of the resource being imported into.
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

        $resource = $this->authorizeImport((string) $request->input('resource'));
        if ($resource instanceof JsonResponse) {
            return $resource;
        }
        $user = $this->user();
        if ($user === null) {
            return $this->unauthenticated();
        }

        $disk = ImportUploads::disk();
        $path = $request->file('file')->store(ImportUploads::directoryFor($user), $disk);

        return $this->success([
            'disk' => $disk,
            'path' => $path,
        ]);
    }

    /**
     * Returns a preview of the uploaded file, with the automatic mapping.
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

        $resource = $this->authorizeImport($data['resource']);
        if ($resource instanceof JsonResponse) {
            return $resource;
        }
        $source = $this->ownedSource($data);
        if ($source instanceof JsonResponse) {
            return $source;
        }

        $preview = $this->previewService->preview(ImportUploads::disk(), $data['path']);
        $autoMapping = ColumnMapper::autoMap($preview['headers'], self::createFields($resource));

        return $this->success([
            'headers' => $preview['headers'],
            'sample' => $preview['sample'],
            'total' => $preview['total'],
            'format' => $preview['format'],
            'auto_mapping' => $autoMapping,
        ]);
    }

    /**
     * Creates an ImportProcess and runs it, synchronously by default.
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
            'mapping.*' => ['nullable', 'string'],
            'disk' => ['nullable', 'string'],
        ]);

        $resource = $this->authorizeImport($data['resource']);
        if ($resource instanceof JsonResponse) {
            return $resource;
        }
        $owner = $this->ownedSource($data);
        if ($owner instanceof JsonResponse) {
            return $owner;
        }

        // A column may go only into a field of the resource's create form: the
        // import writes what the form would, never an arbitrary column.
        $allowed = RecordWriter::createFieldNames($resource);
        $mapping = [];
        $unknown = [];
        foreach ($data['mapping'] as $header => $field) {
            if ($field === null || $field === '') {
                continue;
            }
            if (! in_array($field, $allowed, true)) {
                $unknown[] = $field;

                continue;
            }
            $mapping[(string) $header] = $field;
        }
        if ($unknown !== []) {
            return $this->error([
                'errorKey' => 'invalid_mapping',
                'message' => __('Колонки можно сопоставить только с полями формы создания: :fields', ['fields' => implode(', ', array_unique($unknown))]),
                'fields' => array_values(array_unique($unknown)),
            ], 422);
        }

        $process = ImportProcess::create([
            'resource_slug' => $resource::slug(),
            'source_path' => $data['path'],
            'mapping' => $mapping,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);

        $this->runner->run($process->id);

        return $this->success([
            'process' => $this->serialize($process->refresh()),
        ]);
    }

    /**
     * Returns an ImportProcess's status.
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

        // Another user's process answers exactly as a missing one: its
        // errors quote the imported rows.
        if ($process === null || ! $this->maySeeProcess($process)) {
            return $this->error([
                'errorKey' => 'not_found',
                'message' => 'ImportProcess not found',
            ], 404);
        }

        return $this->success(['process' => $this->serialize($process)]);
    }

    /**
     * The resource, when the signed-in user may import into it: it is
     * registered in the current panel, it opts in through importable(), and
     * the user may create its records — the same `{permission}.create` key the
     * resource's own create action requires, so a host's permission bridge
     * (read-only resources, denied actions) answers here too.
     */
    private function authorizeImport(string $slug): Resource|JsonResponse
    {
        $resource = $this->resources->resolve($slug, Panels::current()->id);
        if ($resource === null) {
            return $this->error([
                'errorKey' => 'unknown_resource',
                'message' => "Resource `{$slug}` is not registered",
            ], 422);
        }

        if (! $resource->importable()) {
            return $this->error([
                'errorKey' => 'import_disabled',
                'message' => __('Импорт в этот раздел не предусмотрен'),
            ], 403);
        }

        $permission = $resource::permission().'.create';
        if (! PermissionCheck::allows($permission)) {
            return $this->error([
                'errorKey' => 'forbidden',
                'message' => __('Доступ запрещён: :permission', ['permission' => $permission]),
                'permission' => $permission,
            ], 403);
        }

        return $resource;
    }

    /**
     * The signed-in user, when the request names a file of their own on the
     * import disk; an error response otherwise.
     *
     * @param  array<string, mixed>  $data
     */
    private function ownedSource(array $data): Model|JsonResponse
    {
        $user = $this->user();
        if ($user === null) {
            return $this->unauthenticated();
        }

        $disk = ImportUploads::disk();
        if (isset($data['disk']) && $data['disk'] !== '' && $data['disk'] !== $disk) {
            return $this->error([
                'errorKey' => 'forbidden_disk',
                'message' => __('Импорт читает файлы только с диска :disk', ['disk' => $disk]),
            ], 422);
        }

        $path = (string) $data['path'];
        if (! ImportUploads::ownedBy($path, $user) || ! Storage::disk($disk)->exists($path)) {
            return $this->error([
                'errorKey' => 'file_missing',
                'message' => "Source file `{$path}` not found",
            ], 422);
        }

        return $user;
    }

    private function maySeeProcess(ImportProcess $process): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        if ($process->owner_id !== null
            && (string) $process->owner_type === $user->getMorphClass()
            && (string) $process->owner_id === (string) $user->getKey()) {
            return true;
        }

        // Whoever may import into the resource may follow its imports.
        $resource = $this->resources->resolve($process->resource_slug, Panels::current()->id);

        return $resource !== null && PermissionCheck::allows($resource::permission().'.create');
    }

    /**
     * The fields of the create form, which the automatic mapping offers.
     *
     * @return list<\Dskripchenko\LaravelAdmin\Field\Field>
     */
    private static function createFields(Resource $resource): array
    {
        return array_values(array_filter(
            $resource->fields(),
            static fn ($field): bool => $field->appliesTo('create'),
        ));
    }

    private function unauthenticated(): JsonResponse
    {
        return $this->error([
            'errorKey' => 'unauthenticated',
            'message' => 'Unauthenticated',
        ], 401);
    }

    private function user(): ?Model
    {
        $guard = Panels::currentGuard();
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
