<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\I18n\Localize;
use Dskripchenko\LaravelAdmin\Permission\PermissionCheck;
use Dskripchenko\LaravelAdmin\Support\FlowGraph;
use InvalidArgumentException;

/**
 * A process or a state machine, read-only, with live monitoring — drawn by
 * FlowView of @dskripchenko/flow.
 *
 * The model comes from the screen's state (a key) or is given inline (an
 * array or a JSON string, checked by FlowGraph):
 *
 *     Layout::flow('process')                       // $state['process']
 *         ->poll('processState', seconds: 5)        // the monitoring snapshot
 *         ->onToggle('toggleStep', permission: 'orders.process.manage')
 *         ->subgraphs('loadSubprocess')             // string `subgraph` references
 *         ->instanceFrom('order_id')                // the instance in focus
 *         ->height(560)
 *
 * The methods are the screen's own public methods, called through its
 * `runMethod` action — the same session, CSRF, layers and permissions as a
 * button. Each one this layout names is declared for the server: a user who
 * cannot see the layout (canSee) cannot call them, and the toggle method also
 * needs its permission.
 *
 *   - poll:      `(array $context): array` — `$context` is
 *                `{path: string[], ref: ?string, instance: mixed}`; returns a
 *                monitoring snapshot `{nodes, edges, path, current, updatedAt}`.
 *   - onToggle:  `(array $change)` — `{kind: 'node'|'edge', id, enabled, path}`;
 *                throw ActionFailedException (or return a non-2xx response) to
 *                roll the switch back, the message is shown in a toast.
 *   - subgraphs: `(string $ref, array $node): array` — the referenced graph.
 *   - actions:   buttons in the details drawer, `(array $selection)` —
 *                `{kind, id, path, instance}`; the monitoring is refreshed after.
 */
final class Flow extends Layout
{
    /** @var array<string, mixed>|null The inline model. */
    private ?array $graph = null;

    /** The state key holding the model. */
    private ?string $source = null;

    /** @var array<string, mixed>|null A fixed monitoring snapshot. */
    private ?array $snapshot = null;

    private ?string $snapshotSource = null;

    private ?string $pollMethod = null;

    private int $pollInterval = 5000;

    private ?string $toggleMethod = null;

    private ?string $togglePermission = null;

    private ?string $subgraphMethod = null;

    /** @var array<string, array<string, mixed>> */
    private array $subgraphMap = [];

    private string|int|null $instance = null;

    private ?string $instanceSource = null;

    /** @var list<Action> */
    private array $actions = [];

    /**
     * @param  string|array<string, mixed>  $graph  A state key, a JSON model or the model as an array.
     */
    public static function make(string|array $graph): self
    {
        $instance = new self;
        if (is_array($graph)) {
            $instance->graph = FlowGraph::parse($graph);
        } elseif (str_starts_with(ltrim($graph), '{')) {
            $instance->graph = FlowGraph::parse($graph);
        } else {
            if ($graph === '') {
                throw new InvalidArgumentException('Layout::flow() needs a state key or a model');
            }
            $instance->source = $graph;
        }

        return $instance;
    }

    public function type(): string
    {
        return 'flow';
    }

    /** A caption above the graph. */
    public function title(string $title): self
    {
        $this->props['title'] = $title;

        return $this;
    }

    /**
     * The layout direction: 'LR', 'TB', or 'auto' (follows the box's shape);
     * the model's `meta.direction` when not set.
     */
    public function direction(string $direction): self
    {
        if (! in_array($direction, ['LR', 'TB', 'auto'], true)) {
            throw new InvalidArgumentException("Unknown flow direction `{$direction}`: LR, TB or auto");
        }
        $this->props['direction'] = $direction;

        return $this;
    }

    /** 'orthogonal' (right angles, the default) or 'smooth' (curves). */
    public function edgeStyle(string $style): self
    {
        if (! in_array($style, ['orthogonal', 'smooth'], true)) {
            throw new InvalidArgumentException("Unknown flow edge style `{$style}`: orthogonal or smooth");
        }
        $this->props['edgeStyle'] = $style;

        return $this;
    }

    /** The height of the box: 560 (px) or '70vh'; 480px by default. */
    public function height(int|string $height): self
    {
        $this->props['height'] = is_int($height) ? $height.'px' : $height;

        return $this;
    }

    public function minimap(bool $on = true): self
    {
        $this->props['minimap'] = $on;

        return $this;
    }

    /** The details drawer a node opens on click; on by default. */
    public function details(bool $on = true): self
    {
        $this->props['details'] = $on;

        return $this;
    }

    /** The status line: last update, staleness, a summary by status; on by default. */
    public function statusBar(bool $on = true): self
    {
        $this->props['statusBar'] = $on;

        return $this;
    }

    /**
     * Enable/disable switches on the nodes and edges. Without onToggle() the
     * change stays in the browser.
     */
    public function toggleable(bool $on = true): self
    {
        $this->props['toggleable'] = $on;

        return $this;
    }

    /**
     * A fixed monitoring snapshot: the snapshot itself, or the state key that
     * holds it. poll() takes over once it answers.
     *
     * @param  string|array<string, mixed>  $snapshot
     */
    public function snapshot(string|array $snapshot): self
    {
        if (is_array($snapshot)) {
            $this->snapshot = $snapshot;
            $this->snapshotSource = null;
        } else {
            $this->snapshotSource = $snapshot;
            $this->snapshot = null;
        }

        return $this;
    }

    /**
     * Polls a screen method for the monitoring snapshot every $seconds. The
     * method receives `{path, ref, instance}` and returns the snapshot.
     */
    public function poll(string $method, int|float $seconds = 5): self
    {
        $this->pollMethod = $method;
        $this->pollInterval = max(1000, (int) round($seconds * 1000));

        return $this;
    }

    /**
     * Persists a toggle through a screen method, which receives
     * `{kind, id, enabled, path}`. A failure rolls the switch back with a
     * toast. With $permission the switches are shown — and the method may be
     * called — only by a user holding it.
     */
    public function onToggle(string $method, ?string $permission = null): self
    {
        $this->toggleMethod = $method;
        $this->togglePermission = $permission;
        $this->props['toggleable'] = true;

        return $this;
    }

    /**
     * How string `subgraph` references are resolved: a screen method called
     * with `($ref, $node)` that returns the graph, or the graphs themselves,
     * keyed by reference.
     *
     * @param  string|array<string, array<string, mixed>|string>  $subgraphs
     */
    public function subgraphs(string|array $subgraphs): self
    {
        if (is_string($subgraphs)) {
            $this->subgraphMethod = $subgraphs;

            return $this;
        }
        foreach ($subgraphs as $ref => $graph) {
            $this->subgraphMap[(string) $ref] = FlowGraph::parse($graph);
        }

        return $this;
    }

    /**
     * The instance on screen, passed to the poll method as
     * `$context['instance']` and to the drawer's actions.
     */
    public function instance(string|int|null $instance): self
    {
        $this->instance = $instance;
        $this->instanceSource = null;

        return $this;
    }

    /**
     * Reads the instance from a key of the screen's state, so a field on the
     * same screen (a select of orders, say) switches the instance: the
     * monitoring restarts for the new one.
     */
    public function instanceFrom(string $key): self
    {
        $this->instanceSource = $key;
        $this->instance = null;

        return $this;
    }

    /**
     * Buttons in the details drawer of a node or an edge. Each is a Button
     * bound to a screen method, which receives the selection
     * `{kind, id, path, instance}`; permission() and canSee() apply as for
     * any action.
     *
     * @param  array<array-key, Action>  $actions
     */
    public function actions(array $actions): self
    {
        $this->actions = array_values($actions);

        return $this;
    }

    /** The state key holding the model, or null for an inline one. */
    public function source(): ?string
    {
        return $this->source;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function graph(): ?array
    {
        return $this->graph;
    }

    /**
     * The screen methods this layout calls, declared for the server's check
     * (ActionLocator): runMethod finds them here, so the layout's visibility
     * and the toggle's permission guard them like a button's.
     *
     * @return list<\Dskripchenko\LaravelAdmin\Contracts\Renderable>
     */
    public function childRenderables(): array
    {
        $declared = [];
        foreach (['poll' => $this->pollMethod, 'subgraphs' => $this->subgraphMethod] as $role => $method) {
            if ($method !== null) {
                $declared[] = Button::make('flow')->withName('flow-'.$role)->method($method);
            }
        }
        if ($this->toggleMethod !== null) {
            $toggle = Button::make('flow')->withName('flow-toggle')->method($this->toggleMethod);
            if ($this->togglePermission !== null) {
                $toggle->permission($this->togglePermission);
            }
            $declared[] = $toggle;
        }

        return [...$declared, ...$this->actions];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        $canToggle = $this->togglePermission === null
            || PermissionCheck::allows($this->togglePermission);
        if (! $canToggle) {
            // No switches for a user who may not use them.
            $array['toggleable'] = false;
            $array['props']['toggleable'] = false;
        }

        $translate = static fn (string $s): string => (string) Localize::string($s);

        // The model travels once, at the top level, like Markdown's text.
        $array['graph'] = $this->graph !== null ? FlowGraph::forJson($this->graph, $translate) : null;
        $array['source'] = $this->source;
        $array['snapshot'] = $this->snapshot;
        $array['snapshotSource'] = $this->snapshotSource;
        $array['poll'] = $this->pollMethod !== null
            ? ['method' => $this->pollMethod, 'interval' => $this->pollInterval]
            : null;
        $array['toggle'] = $this->toggleMethod !== null && $canToggle
            ? ['method' => $this->toggleMethod]
            : null;
        $array['subgraphs'] = [
            'method' => $this->subgraphMethod,
            'graphs' => (object) array_map(
                static fn (array $g): array => FlowGraph::forJson($g, $translate),
                $this->subgraphMap,
            ),
        ];
        $array['instance'] = $this->instance;
        $array['instanceSource'] = $this->instanceSource;
        $array['actions'] = array_values(array_map(
            static fn (Action $a): array => $a->toArray(),
            array_filter($this->actions, static fn (Action $a): bool => $a->isVisible()),
        ));

        return $array;
    }
}
