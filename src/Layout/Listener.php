<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Closure;
use Dskripchenko\LaravelAdmin\Contracts\Renderable;
use Dskripchenko\LaravelAdmin\Support\Repository;
use Illuminate\Http\Request;

/**
 * A reactive part of a form.
 *
 * It watches a set of fields; when one of them changes, the SPA sends the
 * current form state to the server, which runs the listener's handler and
 * renders the listener's children again. The answer replaces the subtree and
 * merges the handler's state patch into the form.
 *
 *     Layout::listener(fn (array $state) => [
 *         Select::make('city')->options(City::optionsFor($state['country'] ?? null)),
 *     ])->listen('country'),
 *
 *     Layout::listener([Number::make('total')->readonly()])
 *         ->listen(['price', 'quantity'])
 *         ->handler('recalculateTotal'),
 *
 * The children are a list of renderables, or a closure that receives the
 * state and returns one. The handler is optional: the name of a public method
 * on the screen or resource the listener belongs to, or a closure. It
 * receives `(array $state, Request $request)` and returns a state patch — an
 * array or a Repository of the keys to change — or null.
 *
 * Only listeners declared in the screen's `layout()` or the resource's
 * `formLayout()` can be reached through the API: the request names a listener
 * by its id, never a method.
 */
final class Listener extends Layout
{
    /** @var list<Renderable>|Closure(array<string, mixed>): list<Renderable> */
    private array|Closure $content = [];

    /** @var list<string> */
    private array $fields = [];

    private string|Closure|null $handler = null;

    private int $debounce = 300;

    /**
     * The state the listener was rendered with on the server, when the screen
     * knew it at compile time; null otherwise.
     *
     * @var array<string, mixed>|null
     */
    private ?array $primedState = null;

    /**
     * @param  list<Renderable>|Closure(array<string, mixed>): list<Renderable>  $children
     */
    public static function make(array|Closure $children = []): self
    {
        $instance = new self;
        $instance->content = $children;

        return $instance;
    }

    public function type(): string
    {
        return 'listener';
    }

    /**
     * The fields whose changes trigger the listener.
     *
     * @param  list<string>|string  $fields
     */
    public function listen(array|string $fields): self
    {
        $this->fields = array_values(array_unique(array_map('strval', (array) $fields)));

        return $this;
    }

    /**
     * The method name on the owning screen or resource, or a closure, called
     * as `(array $state, Request $request)` and returning a state patch.
     */
    public function handler(string|Closure $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    /** How long the SPA waits after the last change before it asks, in milliseconds. */
    public function debounce(int $milliseconds): self
    {
        $this->debounce = max(0, $milliseconds);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function listens(): array
    {
        return $this->fields;
    }

    public function getHandler(): string|Closure|null
    {
        return $this->handler;
    }

    /**
     * The id is deterministic — derived from the watched fields and the
     * handler's name — because the SPA names the listener by it in a later
     * request, which rebuilds the layout from scratch. Two listeners watching
     * the same fields with closure handlers need an explicit `withId()`.
     */
    public function id(): string
    {
        if ($this->id === null) {
            $handler = is_string($this->handler) ? $this->handler : '';
            $this->id = 'listener-'.substr(sha1(implode(',', $this->fields).'|'.$handler), 0, 12);
        }

        return $this->id;
    }

    /**
     * The static children, for the tree walk. A closure's children depend on
     * the state and are not walked.
     *
     * @return list<Renderable>
     */
    public function childRenderables(): array
    {
        return is_array($this->content) ? $this->content : [];
    }

    /**
     * Renders the children against a state.
     *
     * @param  array<string, mixed>  $state
     * @return list<array<string, mixed>>
     */
    public function render(array $state): array
    {
        // A closure is the host's code: whatever it returns is checked.
        /** @var iterable<mixed> $children */
        $children = $this->content instanceof Closure
            ? ($this->content)($state)
            : $this->content;

        $out = [];
        foreach ($children as $child) {
            if (! $child instanceof Renderable || ! $child->isVisible()) {
                continue;
            }
            $out[] = $child->toArray();
        }

        return $out;
    }

    /**
     * Tells whether the string handler names a method the owner may run: a
     * public, non-static method — and, on a screen, not a reserved one.
     */
    public function handlerIsCallable(object $owner): bool
    {
        if ($this->handler === null || $this->handler instanceof Closure) {
            return true;
        }
        if ($owner instanceof \Dskripchenko\LaravelAdmin\Screen\Screen) {
            return $owner->isCallableMethod($this->handler);
        }
        if (! method_exists($owner, $this->handler)) {
            return false;
        }
        $reflection = new \ReflectionMethod($owner, $this->handler);

        return $reflection->isPublic() && ! $reflection->isStatic();
    }

    /**
     * Runs the handler and renders the children with the patched state.
     *
     * @param  array<string, mixed>  $state
     * @return array{listener: string, state: array<string, mixed>, layouts: list<array<string, mixed>>}
     */
    public function respond(object $owner, array $state, Request $request): array
    {
        $patch = [];
        if ($this->handler instanceof Closure) {
            $patch = self::normalizePatch(($this->handler)($state, $request));
        } elseif (is_string($this->handler)) {
            $patch = self::normalizePatch($owner->{$this->handler}($state, $request));
        }

        return [
            'listener' => $this->id(),
            'state' => $patch,
            'layouts' => $this->render(array_replace($state, $patch)),
        ];
    }

    /**
     * Gives the listener the state it is first rendered with; Screen::compile
     * calls it, so the first paint already matches the state.
     *
     * @param  array<string, mixed>  $state
     */
    public function prime(array $state): self
    {
        $this->primedState = $state;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $children = $this->render($this->primedState ?? []);

        return [
            'id' => $this->id(),
            'kind' => 'layout',
            'type' => $this->type(),
            'listen' => $this->fields,
            'debounce' => $this->debounce,
            'primed' => $this->primedState !== null,
            'items' => $children,
            'props' => [],
            'children' => $children,
        ];
    }

    /* -----------------------------------------------------------------
     * Tree helpers
     * ----------------------------------------------------------------- */

    /**
     * Finds the listener with the given id anywhere in a layout tree.
     *
     * @param  iterable<mixed>  $roots
     */
    public static function find(iterable $roots, string $id): ?self
    {
        foreach (self::all($roots) as $listener) {
            if ($listener->id() === $id) {
                return $listener;
            }
        }

        return null;
    }

    /**
     * Every listener in a layout tree, depth first.
     *
     * @param  iterable<mixed>  $roots
     * @return list<self>
     */
    public static function all(iterable $roots): array
    {
        $out = [];
        foreach ($roots as $node) {
            if (! $node instanceof Layout) {
                continue;
            }
            if ($node instanceof self) {
                $out[] = $node;
            }
            foreach (self::all($node->childRenderables()) as $nested) {
                $out[] = $nested;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizePatch(mixed $result): array
    {
        if ($result instanceof Repository) {
            return $result->toArray();
        }
        if (is_array($result)) {
            /** @var array<string, mixed> $result */
            return $result;
        }

        return [];
    }
}
