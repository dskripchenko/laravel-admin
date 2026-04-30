<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Table;

use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Универсальный controller сохранённых view'ев — обслуживает все Resource'ы
 * по тому же паттерну, что и ResourceController.
 *
 * URL: `/api/admin/{slug}.views/{action}` где slug = resource_slug.
 *
 * Регистрация — через ResourceCompiler с дополнительными actions, либо
 * через отдельную регистрацию в AdminApi (выбран второй вариант — отдельный
 * controller key `{slug}_views`).
 */
final class SavedViewsController extends ApiController
{
    public function __construct(private readonly ResourceRegistry $registry) {}

    /**
     * Список сохранённых view'ев для текущего пользователя + глобальные.
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {SavedViewListResponse}
     */
    public function list(): JsonResponse
    {
        $slug = $this->resourceSlug();
        $user = $this->user();

        $views = SavedView::query()
            ->where('resource_slug', $slug)
            ->visibleTo($user)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return $this->success([
            'data' => $views->map(static fn (SavedView $v): array => [
                'id' => $v->id,
                'name' => $v->name,
                'state' => $v->state,
                'is_default' => $v->is_default,
                'owned' => $v->owner_id !== null,
            ])->all(),
        ]);
    }

    /**
     * Создать view (owned by current user).
     *
     * @input string $name
     * @input array $state
     * @input boolean ?$is_default
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {SavedViewResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function create(Request $request): JsonResponse
    {
        $user = $this->user();
        if ($user === null) {
            return $this->error([
                'errorKey' => 'unauthenticated',
                'message' => 'Unauthenticated',
            ], 401);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'state' => ['required', 'array'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $slug = $this->resourceSlug();
        $view = SavedView::create([
            'resource_slug' => $slug,
            'name' => $data['name'],
            'state' => $data['state'],
            'is_default' => (bool) ($data['is_default'] ?? false),
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->getKey(),
        ]);

        return $this->success(['view' => $this->serialize($view)]);
    }

    /**
     * Обновить state существующего view'а.
     *
     * @input integer $id
     * @input string ?$name
     * @input array ?$state
     * @input boolean ?$is_default
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {SavedViewResponse}
     * @response 403 {ForbiddenErrorResponse}
     * @response 404 {NotFoundErrorResponse}
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['sometimes', 'string', 'max:255'],
            'state' => ['sometimes', 'array'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $view = SavedView::query()->find($data['id']);
        if ($view === null) {
            return $this->error(['errorKey' => 'not_found', 'message' => 'View not found'], 404);
        }
        if (! $this->canEdit($view)) {
            return $this->error(['errorKey' => 'forbidden', 'message' => 'You can edit only your own views'], 403);
        }

        $view->fill(array_intersect_key($data, array_flip(['name', 'state', 'is_default'])))->save();

        return $this->success(['view' => $this->serialize($view)]);
    }

    /**
     * Удалить view.
     *
     * @input integer $id
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {SuccessResponse}
     * @response 403 {ForbiddenErrorResponse}
     * @response 404 {NotFoundErrorResponse}
     */
    public function delete(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'integer']]);
        $view = SavedView::query()->find($data['id']);
        if ($view === null) {
            return $this->error(['errorKey' => 'not_found', 'message' => 'View not found'], 404);
        }
        if (! $this->canEdit($view)) {
            return $this->error(['errorKey' => 'forbidden', 'message' => 'You can delete only your own views'], 403);
        }

        $view->delete();

        return $this->success(['id' => $data['id']]);
    }

    /**
     * Slug текущего Resource'а — берём из api controller key.
     * Регистрация: в AdminApi controller key = "{slug}_views".
     */
    private function resourceSlug(): string
    {
        /** @var string|null $key */
        $key = ApiRequest::getApiControllerKey();
        $key = (string) ($key ?? '');

        // Form: 'users_views' → resource = 'users'.
        if (str_ends_with($key, '_views')) {
            $slug = substr($key, 0, -strlen('_views'));
            if ($this->registry->has($slug)) {
                return $slug;
            }
        }

        throw new NotFoundHttpException("Saved views key `{$key}` doesn't match a registered resource");
    }

    private function user(): ?Model
    {
        $guard = (string) config('admin.auth.guard', 'admin');
        $user = Auth::guard($guard)->user();

        return $user instanceof Model ? $user : null;
    }

    private function canEdit(SavedView $view): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return $view->owner_id !== null
            && (string) $view->owner_type === $user->getMorphClass()
            && (string) $view->owner_id === (string) $user->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SavedView $view): array
    {
        return [
            'id' => $view->id,
            'name' => $view->name,
            'state' => $view->state,
            'is_default' => $view->is_default,
            'owned' => $view->owner_id !== null,
        ];
    }
}
