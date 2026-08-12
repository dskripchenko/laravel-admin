<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Settings;

use Dskripchenko\LaravelAdmin\Settings\Storage\SettingsStorage;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The universal settings controller, serving every registered
 * SettingsResource.
 *
 * The URL is `/api/admin/settings.{slug}/{action}`, where `slug` is
 * SettingsResource::slug(). The registration goes through AdminApi, together
 * with PluginRegistry.
 */
final class SettingsController extends ApiController
{
    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly SettingsStorage $storage,
    ) {}

    /**
     * A settings group's metadata: its fields and permissions.
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {SettingsMetaResponse}
     */
    public function meta(): JsonResponse
    {
        return $this->success($this->currentSettings()->meta());
    }

    /**
     * Reads the current values, merging the defaults with the storage.
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {SettingsReadResponse}
     */
    public function read(): JsonResponse
    {
        $resource = $this->currentSettings();

        return $this->success([
            'values' => $resource->read($this->storage),
        ]);
    }

    /**
     * Saves the values through resource->write().
     *
     * @input object $values
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {SettingsUpdatedResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function update(Request $request): JsonResponse
    {
        $resource = $this->currentSettings();
        $values = (array) $request->input('values', []);

        $resource->write($this->storage, $values);

        return $this->success([
            'values' => $resource->read($this->storage),
            'message' => 'Saved',
        ]);
    }

    private function currentSettings(): SettingsResource
    {
        /** @var string|null $key */
        $key = ApiRequest::getApiControllerKey();
        $key = (string) ($key ?? '');

        // Form: 'settings_brand' → slug = 'brand'.
        if (! str_starts_with($key, 'settings_')) {
            throw new NotFoundHttpException("Settings key `{$key}` malformed");
        }
        $slug = substr($key, strlen('settings_'));
        $resource = $this->registry->resolve($slug, \Dskripchenko\LaravelAdmin\Panel\Panels::current()->id);
        if ($resource === null) {
            throw new NotFoundHttpException("Settings `{$slug}` is not registered");
        }

        return $resource;
    }
}
