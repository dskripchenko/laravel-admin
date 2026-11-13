<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Navigation;

use Dskripchenko\LaravelAdmin\Panel\Panels;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The user's own navigation: the favourites (the star in a page's header),
 * the recently opened pages and records, and which menu groups are folded.
 *
 * Everything is kept on the server, per user and panel, in
 * `admin_navigation_states`. What is handed back passes UrlAccess first: an
 * address the user may no longer open is not shown — it stays saved, and comes
 * back with the permission.
 *
 * Limits: `admin.navigation.favorites_limit` (7) and
 * `admin.navigation.recent_limit` (10).
 */
final class NavigationController extends ApiController
{
    private const LABEL_MAX = 200;

    public function __construct(private readonly UrlAccess $access) {}

    /**
     * The user's favourites, recent pages and menu groups.
     *
     * @output object $payload
     * @output array  $payload.favorites
     * @output array  $payload.recent
     * @output object $payload.groups
     * @output integer $payload.favoritesLimit
     *
     * @security AdminSession
     *
     * @response 200 {NavigationStateResponse}
     */
    public function state(Request $request): JsonResponse
    {
        $user = $this->user();
        if ($user === null) {
            return $this->unauthenticated();
        }

        return $this->success($this->payload(NavigationState::for($user, Panels::current()->id)));
    }

    /**
     * Adds a page to the favourites — at the end; an address already there
     * gets the new label and keeps its place.
     *
     * @input string $url The page's path inside the panel, with its query (`/screens/queue?tab=new`)
     * @input string $label
     * @input string ?$icon
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {NavigationStateResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function favorite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'label' => ['required', 'string', 'max:'.self::LABEL_MAX],
            'icon' => ['nullable', 'string', 'max:64'],
        ]);
        $user = $this->user();
        if ($user === null) {
            return $this->unauthenticated();
        }
        $panel = Panels::current()->id;
        $url = $this->checkedUrl($data['url'], $panel);

        $row = NavigationState::for($user, $panel);
        $favorites = $row->favorites ?? [];
        $found = false;
        foreach ($favorites as $i => $item) {
            if (($item['url'] ?? null) === $url) {
                $favorites[$i] = ['url' => $url, 'label' => $data['label'], 'icon' => $data['icon'] ?? null];
                $found = true;
            }
        }
        if (! $found) {
            if (count($favorites) >= self::favoritesLimit()) {
                throw ValidationException::withMessages([
                    'url' => __('В избранном не больше :count страниц — уберите одну из них', ['count' => self::favoritesLimit()]),
                ]);
            }
            $favorites[] = ['url' => $url, 'label' => $data['label'], 'icon' => $data['icon'] ?? null];
        }
        $row->favorites = $favorites;
        $row->save();

        return $this->success($this->payload($row));
    }

    /**
     * Removes a page from the favourites.
     *
     * @input string $url
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {NavigationStateResponse}
     */
    public function unfavorite(Request $request): JsonResponse
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:2048']]);
        $user = $this->user();
        if ($user === null) {
            return $this->unauthenticated();
        }
        $row = NavigationState::for($user, Panels::current()->id);
        $row->favorites = array_values(array_filter(
            $row->favorites ?? [],
            static fn (array $item): bool => ($item['url'] ?? null) !== $data['url'],
        ));
        $row->save();

        return $this->success($this->payload($row));
    }

    /**
     * Puts the favourites in the given order — the addresses as they are to
     * follow. The favourites the list does not name keep their relative order
     * after it (one hidden from the user right now, say).
     *
     * @input array $urls
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {NavigationStateResponse}
     */
    public function reorderFavorites(Request $request): JsonResponse
    {
        $data = $request->validate([
            'urls' => ['present', 'array'],
            'urls.*' => ['string', 'max:2048'],
        ]);
        $user = $this->user();
        if ($user === null) {
            return $this->unauthenticated();
        }
        $row = NavigationState::for($user, Panels::current()->id);
        $byUrl = [];
        foreach ($row->favorites ?? [] as $item) {
            $byUrl[(string) ($item['url'] ?? '')] = $item;
        }
        $ordered = [];
        foreach ($data['urls'] as $url) {
            if (isset($byUrl[$url])) {
                $ordered[] = $byUrl[$url];
                unset($byUrl[$url]);
            }
        }
        $row->favorites = [...$ordered, ...array_values($byUrl)];
        $row->save();

        return $this->success($this->payload($row));
    }

    /**
     * Notes an opened page or record in the recent list: first place, one
     * entry per path — a page opened on another tab or filter replaces its
     * earlier entry.
     *
     * @input string $url
     * @input string $label
     * @input string ?$subtitle What the page belongs to — a record's resource, a tab's screen
     * @input string ?$kind `screen`, `record` or `page`
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {NavigationStateResponse}
     */
    public function visit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'label' => ['required', 'string', 'max:'.self::LABEL_MAX],
            'subtitle' => ['nullable', 'string', 'max:'.self::LABEL_MAX],
            'kind' => ['nullable', 'string', 'in:screen,record,page'],
        ]);
        $user = $this->user();
        if ($user === null) {
            return $this->unauthenticated();
        }
        $panel = Panels::current()->id;
        $url = $this->checkedUrl($data['url'], $panel);
        $path = UrlAccess::pathOf($url);

        $row = NavigationState::for($user, $panel);
        $recent = array_values(array_filter(
            $row->recent ?? [],
            static fn (array $item): bool => ($item['path'] ?? null) !== $path,
        ));
        array_unshift($recent, [
            'url' => $url,
            'path' => $path,
            'label' => $data['label'],
            'subtitle' => $data['subtitle'] ?? null,
            'kind' => $data['kind'] ?? 'page',
            'at' => now()->toIso8601String(),
        ]);
        // A few more than are shown: an entry the user cannot open right now
        // should not push out one they can.
        $row->recent = array_slice($recent, 0, self::recentLimit() * 2);
        $row->save();

        return $this->success($this->payload($row));
    }

    /**
     * Remembers whether a menu group is unfolded.
     *
     * @input string $key The menu node's key
     * @input boolean $open
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {SuccessResponse}
     */
    public function group(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'open' => ['required', 'boolean'],
        ]);
        $user = $this->user();
        if ($user === null) {
            return $this->unauthenticated();
        }
        $row = NavigationState::for($user, Panels::current()->id);
        $groups = $row->menu_groups ?? [];
        $groups[$data['key']] = (bool) $data['open'];
        // A bounded map: a menu has dozens of groups, not thousands.
        if (count($groups) > 500) {
            $groups = array_slice($groups, -500, null, true);
        }
        $row->menu_groups = $groups;
        $row->save();

        return $this->success(['groups' => $groups]);
    }

    public static function favoritesLimit(): int
    {
        return max(1, (int) config('admin.navigation.favorites_limit', 7));
    }

    public static function recentLimit(): int
    {
        return max(1, (int) config('admin.navigation.recent_limit', 10));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(NavigationState $row): array
    {
        $panel = Panels::current()->id;
        $allowed = fn (array $item): bool => is_string($item['url'] ?? null) && $this->access->allows($item['url'], $panel);

        return [
            'favorites' => array_values(array_filter($row->favorites ?? [], $allowed)),
            'recent' => array_slice(array_values(array_filter($row->recent ?? [], $allowed)), 0, self::recentLimit()),
            'groups' => (object) ($row->menu_groups ?? []),
            'favoritesLimit' => self::favoritesLimit(),
        ];
    }

    private function checkedUrl(string $url, string $panel): string
    {
        if (! UrlAccess::isPanelPath($url) || ! $this->access->allows($url, $panel)) {
            throw ValidationException::withMessages([
                'url' => __('Эта страница недоступна'),
            ]);
        }

        return $url;
    }

    private function user(): ?Model
    {
        $user = Auth::guard(Panels::currentGuard())->user();

        return $user instanceof Model ? $user : null;
    }

    private function unauthenticated(): JsonResponse
    {
        return $this->error([
            'errorKey' => 'unauthenticated',
            'message' => 'Unauthenticated',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
