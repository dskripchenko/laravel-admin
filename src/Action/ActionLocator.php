<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action;

use Dskripchenko\LaravelAdmin\Layout\Layout;

/**
 * Finds declared actions in a tree of actions and layouts — a resource's
 * actions(), a screen's command bar and layout — and tells whether the
 * current user may run each one.
 *
 * An action may be run when it is visible (its canSee() holds and the user has
 * its permission()) and so is everything it sits in: the dropdown that holds
 * it, the layout that shows it. The server answers with this before it runs
 * anything an action triggers, so hiding a button is never the only guard.
 */
final class ActionLocator
{
    /**
     * Every action matching the predicate, depth first, each with its verdict.
     *
     * @param  iterable<mixed>  $roots  Actions and layouts.
     * @param  callable(Action): bool  $match
     * @return list<array{action: Action, allowed: bool, deniedBy: ?string}>
     */
    public static function find(iterable $roots, callable $match): array
    {
        $out = [];
        self::walk($roots, $match, null, true, $out);

        return $out;
    }

    /**
     * The first action named `$name`, preferring one the user may run; null
     * when nothing is declared under that name.
     *
     * @param  iterable<mixed>  $roots
     * @return array{action: Action, allowed: bool, deniedBy: ?string}|null
     */
    public static function byName(iterable $roots, string $name): ?array
    {
        return self::pick(self::find($roots, static fn (Action $a): bool => $a->name() === $name));
    }

    /**
     * The actions that call a screen or resource method — a Button or a
     * ModalAction with `method($name)`.
     *
     * @param  iterable<mixed>  $roots
     * @return list<array{action: Action, allowed: bool, deniedBy: ?string}>
     */
    public static function byMethod(iterable $roots, string $method): array
    {
        return self::find(
            $roots,
            static fn (Action $a): bool => $a->getAttribute('method') === $method,
        );
    }

    /**
     * The async actions that start the given handler.
     *
     * @param  iterable<mixed>  $roots
     * @return list<array{action: Action, allowed: bool, deniedBy: ?string}>
     */
    public static function byAsyncHandler(iterable $roots, string $entity, string $method): array
    {
        return self::find($roots, static function (Action $a) use ($entity, $method): bool {
            if (! $a instanceof AsyncAction) {
                return false;
            }
            $handler = $a->getAttribute('handler');

            return is_array($handler)
                && ($handler['entity'] ?? null) === $entity
                && ($handler['method'] ?? null) === $method;
        });
    }

    /**
     * Whether a set of matches lets the user through: nothing declared means
     * nothing to enforce; otherwise at least one of the matching actions has
     * to be one the user may run.
     *
     * @param  list<array{action: Action, allowed: bool, deniedBy: ?string}>  $matches
     */
    public static function permits(array $matches): bool
    {
        if ($matches === []) {
            return true;
        }
        foreach ($matches as $match) {
            if ($match['allowed']) {
                return true;
            }
        }

        return false;
    }

    /**
     * The 403 payload for an action the user may not run.
     *
     * @param  list<array{action: Action, allowed: bool, deniedBy: ?string}>  $matches
     * @return array{errorKey: string, message: string}
     */
    public static function forbidden(array $matches): array
    {
        foreach ($matches as $match) {
            if (! $match['allowed'] && $match['deniedBy'] !== null) {
                return [
                    'errorKey' => 'action_forbidden',
                    'message' => __('Доступ запрещён: :permission', ['permission' => $match['deniedBy']]),
                ];
            }
        }

        return [
            'errorKey' => 'action_forbidden',
            'message' => __('Действие недоступно'),
        ];
    }

    /**
     * @param  list<array{action: Action, allowed: bool, deniedBy: ?string}>  $matches
     * @return array{action: Action, allowed: bool, deniedBy: ?string}|null
     */
    private static function pick(array $matches): ?array
    {
        foreach ($matches as $match) {
            if ($match['allowed']) {
                return $match;
            }
        }

        return $matches[0] ?? null;
    }

    /**
     * @param  iterable<mixed>  $nodes
     * @param  callable(Action): bool  $match
     * @param  string|null  $deniedBy  The permission an enclosing dropdown lacks.
     * @param  list<array{action: Action, allowed: bool, deniedBy: ?string}>  $out
     */
    private static function walk(iterable $nodes, callable $match, ?string $deniedBy, bool $parentAllowed, array &$out): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof Action) {
                $allowed = $parentAllowed && $node->isVisible();
                // The permission that shuts the action off: its own, or the
                // one of the dropdown it sits in.
                $blocker = $deniedBy ?? ($node->isPermitted() ? null : $node->getPermission());
                if ($match($node)) {
                    $out[] = ['action' => $node, 'allowed' => $allowed, 'deniedBy' => $allowed ? null : $blocker];
                }
                if ($node instanceof DropDown) {
                    self::walk($node->getItems(), $match, $blocker, $allowed, $out);
                }

                continue;
            }
            if ($node instanceof Layout) {
                self::walk($node->childRenderables(), $match, $deniedBy, $parentAllowed && $node->isVisible(), $out);
            }
        }
    }
}
