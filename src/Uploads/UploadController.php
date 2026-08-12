<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Uploads;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The universal uploads endpoint, serving the WYSIWYG's image upload, the
 * FileUpload field and the four-step import wizard.
 *
 * The URL is `/api/admin/uploads/{action}`, where the action is upload or
 * image.
 *
 * The disk and the path come from config('admin.uploads'):
 *   - disk      — 'local' by default.
 *   - directory — `uploads`.
 *   - max_kilobytes — 51200, that is 50 MB.
 *
 * It returns {disk, path, url, name, size, mime}: the SPA puts the URL into
 * Tiptap's image extension, or keeps the id for a FileUpload field.
 */
final class UploadController extends ApiController
{
    /**
     * A generic upload, of any file.
     *
     * @input file $file
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {UploadResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function upload(Request $request): JsonResponse
    {
        $maxKb = (int) config('admin.uploads.max_kilobytes', 51200);
        $request->validate([
            'file' => ['required', 'file', 'max:'.$maxKb],
        ]);

        return $this->success($this->store($request->file('file')));
    }

    /**
     * An image upload, for the WYSIWYG's image extension and the ImageCropper
     * field.
     *
     * It accepts image/* alone and applies its own maximum size — 10 MB rather
     * than 50 by default — which the config may override.
     *
     * @input file $file
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {UploadResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function image(Request $request): JsonResponse
    {
        $maxKb = (int) config('admin.uploads.max_kilobytes_image', 10240);
        $request->validate([
            'file' => ['required', 'file', 'image', 'max:'.$maxKb],
        ]);

        return $this->success($this->store($request->file('file'), 'images'));
    }

    /**
     * @return array<string, mixed>
     */
    private function store(?UploadedFile $file, ?string $subdirectory = null): array
    {
        if ($file === null) {
            return ['disk' => null, 'path' => null, 'url' => null, 'name' => null, 'size' => 0, 'mime' => null];
        }

        $diskName = (string) config('admin.uploads.disk', 'local');
        $directory = (string) config('admin.uploads.directory', 'uploads');
        if ($subdirectory !== null) {
            $directory .= '/'.$subdirectory;
        }

        $path = $file->store($directory, $diskName);

        return [
            'disk' => $diskName,
            'path' => $path,
            'url' => self::serveUrl($diskName, $path),
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ];
    }

    /**
     * Serves a file from the given disk.
     *
     * Private disks work too; the access is checked exactly as it is on the
     * panel's other actions. The disk has to be allowed in
     * `admin.uploads.servable_disks`.
     *
     * @input string $disk
     * @input string $path
     *
     * @security AdminSession
     *
     * @response 200 binary file
     * @response 404 {NotFoundErrorResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function serve(Request $request): StreamedResponse|JsonResponse
    {
        $diskName = (string) $request->query('disk', '');
        $path = (string) $request->query('path', '');

        if ($diskName === '' || $path === '') {
            return $this->error([
                'errorKey' => 'validation',
                'message' => 'disk and path are required',
            ], 422);
        }

        $servable = (array) config('admin.uploads.servable_disks', [
            (string) config('admin.uploads.disk', 'local'),
        ]);
        if (! in_array($diskName, $servable, true)) {
            return $this->error([
                'errorKey' => 'forbidden_disk',
                'message' => "Disk `{$diskName}` is not in admin.uploads.servable_disks whitelist",
            ], 422);
        }

        $disk = Storage::disk($diskName);
        if (! $disk->exists($path)) {
            return $this->error([
                'errorKey' => 'not_found',
                'message' => 'File not found',
            ], 404);
        }

        return $disk->response($path);
    }

    /**
     * Builds the admin's streaming URL for any disk and path. It is used when
     * saving the upload records, and in a model's accessors when the host
     * wants to show a preview.
     */
    public static function serveUrl(string $disk, string $path): string
    {
        $prefix = (string) config('admin.api_path', 'api/admin');

        return '/'.trim($prefix, '/').'/uploads/serve?disk='.urlencode($disk).'&path='.urlencode($path);
    }
}
