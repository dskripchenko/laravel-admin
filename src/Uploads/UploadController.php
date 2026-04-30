<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Uploads;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Универсальный uploads endpoint для Wysiwyg image-upload, FileUpload field и
 * 4-step Import wizard.
 *
 * URL: `/api/admin/uploads/{action}` где action ∈ {upload, image}.
 *
 * Disk и path конфигурируются через config('admin.uploads'):
 *   - disk      — default 'local'.
 *   - directory — `uploads`.
 *   - max_kilobytes — 51200 (50 MB).
 *
 * Возвращает {disk, path, url, name, size, mime} — SPA вставляет URL в
 * Tiptap image-extension или сохраняет id для FileUpload field'а.
 */
final class UploadController extends ApiController
{
    /**
     * Generic upload (любой файл).
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
     * Image upload — для Wysiwyg image-extension и ImageCropper field'а.
     *
     * Принимает только image/* + дополнительный max-size для image (по
     * умолчанию 10 MB вместо 50). Можно override через config.
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
        $disk = Storage::disk($diskName);

        return [
            'disk' => $diskName,
            'path' => $path,
            'url' => $disk->url($path),
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ];
    }
}
