<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminSearch\Http\Controllers;

use Dskripchenko\LaravelAdminSearch\SearchService;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GET /api/admin/system/search?q=...
 */
final class SearchController extends ApiController
{
    public function __construct(private readonly SearchService $service) {}

    /**
     * @input string $q  Поисковый запрос.
     *
     * @output object $payload
     * @output array $payload.groups
     *
     * @security AdminSession
     *
     * @response 200 {SuccessResponse}
     */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string']]);
        $query = (string) $data['q'];

        $guard = (string) config('admin.auth.guard', 'admin');
        $user = Auth::guard($guard)->user();

        $hasPermission = function (string $permission) use ($user): bool {
            if ($user === null) {
                return false;
            }
            if (! method_exists($user, 'hasAccess')) {
                return false;
            }

            return (bool) $user->hasAccess($permission);
        };

        $groups = $this->service->search($query, $hasPermission);

        return $this->success(['groups' => $groups]);
    }
}
