<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Middleware;

use Closure;
use Dskripchenko\LaravelAdmin\Demo\DemoMode;
use Dskripchenko\LaravelAdmin\Panel\Panels;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo mode's read-only guard: refuses the operations listed in
 * `admin.demo` with a 403 and errorKey `demo_readonly`.
 *
 * It is part of every panel's API stack and does nothing unless both
 * `admin.demo.enabled` and `admin.demo.readonly` are on. What it refuses:
 *
 *   - the API actions matching `admin.demo.blocked`, as `controller.action`
 *     patterns where `*` matches anything;
 *   - the write actions (`admin.demo.protected_actions`) of the resources
 *     whose model is one of `admin.demo.protected_models` — by default the
 *     panel's user model and Role, so that nobody can lock the next visitor
 *     out;
 *   - uploaded files larger than `admin.demo.max_upload_kb`.
 */
final class DemoReadonly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! DemoMode::readonly()) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        /** @var string|null $controller */
        $controller = ApiRequest::getApiControllerKey();
        /** @var string|null $action */
        $action = ApiRequest::getApiActionKey();

        if (is_string($controller) && is_string($action)
            && ($this->isBlocked($controller, $action) || $this->writesProtectedModel($controller, $action))) {
            return self::refuse(__('Демо-режим: это действие отключено.'));
        }

        $limit = (int) config('admin.demo.max_upload_kb', 0);
        if ($limit > 0) {
            foreach (self::files($request->allFiles()) as $file) {
                if ($file->getSize() > $limit * 1024) {
                    return self::refuse(__('Демо-режим: файл больше :size КБ.', ['size' => $limit]));
                }
            }
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }

    private function isBlocked(string $controller, string $action): bool
    {
        $patterns = array_filter(
            (array) config('admin.demo.blocked', []),
            static fn ($pattern): bool => is_string($pattern) && $pattern !== '',
        );

        return $patterns !== [] && Str::is($patterns, $controller.'.'.$action);
    }

    private function writesProtectedModel(string $controller, string $action): bool
    {
        if (! in_array($action, (array) config('admin.demo.protected_actions', []), true)) {
            return false;
        }

        $resource = app(ResourceRegistry::class)->get($controller);
        if ($resource === null) {
            return false;
        }

        // A resource that declares no model has nothing to protect.
        $property = new \ReflectionProperty($resource, 'model');
        if (! $property->isInitialized()) {
            return false;
        }
        $model = $property->getValue();
        if (! is_string($model)) {
            return false;
        }

        foreach (self::protectedModels() as $protected) {
            if (is_a($model, $protected, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function protectedModels(): array
    {
        $configured = config('admin.demo.protected_models');
        if (is_array($configured)) {
            return array_values(array_filter($configured, 'is_string'));
        }

        return array_values(array_filter([Panels::currentAuthModel(), Role::class]));
    }

    /**
     * @param  array<mixed>  $files
     * @return list<UploadedFile>
     */
    private static function files(array $files): array
    {
        $out = [];
        array_walk_recursive($files, static function ($file) use (&$out): void {
            if ($file instanceof UploadedFile) {
                $out[] = $file;
            }
        });

        return $out;
    }

    private static function refuse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'payload' => ['errorKey' => 'demo_readonly', 'message' => $message],
        ], Response::HTTP_FORBIDDEN);
    }
}
