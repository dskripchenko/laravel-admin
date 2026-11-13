<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Layout\BlockTree\BlockPreset;
use Dskripchenko\LaravelAdmin\Layout\BlockTree\BlockType;
use Dskripchenko\LaravelAdmin\Layout\BlockTree\TreeValidator;
use Dskripchenko\LaravelAdmin\Permission\PermissionCheck;
use InvalidArgumentException;

/**
 * An editor of a tree of blocks with a live preview — the base of a page
 * builder. Three panes: the tree (drag and drop within the nesting rules, a
 * palette of the types allowed at the place, duplicate, delete with undo),
 * the preview (the page's HTML, POSTed for the current tree, in an iframe;
 * a click on a block selects its node) and the properties (the selected
 * block's form, drawn by the panel's own fields). A bar below: the draft's
 * state, discard, publish.
 *
 *     Layout::blockTree('content')                        // $state['content']
 *         ->types([
 *             BlockType::make('hero', 'Hero')->root()->titleFrom('title')->fields([...]),
 *             BlockType::make('columns', 'Columns')->root()->accepts(['text', 'image']),
 *             BlockType::make('text', 'Text')->titleFrom('body')->fields([...]),
 *         ])
 *         ->preview(route('pages.preview', $page))
 *         ->draft('saveDraft')
 *         ->publish('publish', permission: 'pages.publish')
 *         ->discard('discardDraft')
 *         ->maxDepth(4)
 *
 * Next to the types the palette may offer presets — ready blocks of those
 * types with their own data and meta (a shared block of a library, say):
 *
 *     ->presets([
 *         BlockPreset::make('library-42', 'Footer', 'text')
 *             ->group('Shared blocks')
 *             ->data(['body' => '<p>©</p>'])
 *             ->meta(['library' => 42]),
 *     ])
 *
 * A preset's pick inserts a node of its type with a copy of its data, meta
 * and children, under a new temporary ref; its place follows the rules of
 * the type and of `meta.accepts`, as any block's.
 *
 * The value — one JSON structure in the screen's state, so the screen
 * methods get it with the rest of the state:
 *
 *     [
 *         'nodes' => [['ref' => 15, 'type' => 'hero', 'data' => [...], 'children' => [...], 'meta' => [...]]],
 *         'fingerprint' => 'sha256…',   // of the published state the editing began from
 *         'published' => [...],         // optional: the published nodes, when `nodes` is a draft
 *         'savedAt' => '2026-11-13T12:41:00+03:00', // optional: when the draft was saved
 *         'previewUrl' => '/…',         // optional: replaces preview() for this record
 *     ]
 *
 * A node's `ref` is the id of an existing block or a temporary one the panel
 * makes for a new block (`new-…`). `meta` is the server's: `title` (the
 * caption, when the type has no titleFrom), `rendersOnSite` (false — marked
 * "not shown"); the panel keeps it as it is. For a tree whose blocks inherit
 * from shared base blocks, and for nodes the rules of their type do not fit:
 *
 *   - `accepts` — list<string>: the types allowed inside this node, instead of
 *     its type's (`[]` — none); the palette, the drag and TreeValidator use it;
 *   - `locked` — true: the node is read-only — its fields, its children (none
 *     added, removed or reordered), its place; it can be selected and viewed.
 *     It goes away only with an unlocked ancestor removed as a whole;
 *   - `fields` — `{name: {inherited: bool, base: mixed}}`: the field is
 *     inherited from the base block (its value is `base`) or overridden here.
 *     The form marks it so, an edit makes it overridden at once, and "Return
 *     the base value" puts `base` back and sets `inherited` to true. What is
 *     saved is the server's decision.
 *
 * The methods are the screen's own, called through its runMethod with the
 * whole state as the single argument (`array $state`), like a button. Each
 * one this layout names is declared for the server, so the layout's
 * visibility and the permission guard it:
 *
 *   - draft: autosave, a while after each change. Return
 *     BlockTree::draftSaved() — the editor keeps its tree, with whatever was
 *     typed while the request was out, and only renames the new blocks' refs
 *     — or BlockTree::saved($value) to replace the tree. BlockTree::conflict()
 *     and BlockTree::invalid() stop the autosave until the person acts;
 *   - publish: the publish button. Return BlockTree::conflict() when the
 *     published state changed since the editing began, BlockTree::invalid()
 *     with the validator's errors, or BlockTree::saved() with the new value;
 *   - discard: the discard button, after a confirmation; return the value
 *     to go on from (BlockTree::saved()).
 *
 * Without the draft and publish methods (or their permissions) the editor is
 * read-only.
 *
 * The preview: POST `{nodes, ...previewParams}` as JSON, with the panel's
 * session and CSRF token; the answer is the page's HTML. A block of the page
 * is wrapped into an element with the marker attribute (`data-builder-ref`
 * by default) holding the node's ref; the panel selects a node on a click
 * inside such an element and scrolls to it when a node is selected. A page
 * script may also post `{type: 'builder:select', ref}` to the parent; the
 * panel posts `{type: 'builder:highlight', ref}` to the page.
 */
final class BlockTree extends Layout
{
    /** @var array<string, BlockType> */
    private array $types = [];

    /** @var array<string, BlockPreset> */
    private array $presets = [];

    /** @var list<string>|null */
    private ?array $rootTypes = null;

    /** @var array<string, array{0: string|null, 1: string|null}> role => [method, permission] */
    private array $methods = [
        'draft' => [null, null],
        'publish' => [null, null],
        'discard' => [null, null],
    ];

    private ?int $maxDepth = null;

    public function __construct(private readonly string $source) {}

    public static function make(string $source): self
    {
        if (preg_match(DocumentLayout::STATE_KEY, $source) !== 1) {
            throw new InvalidArgumentException("Layout::blockTree() needs a state key, `{$source}` given");
        }

        return new self($source);
    }

    public function type(): string
    {
        return 'block_tree';
    }

    /**
     * The types of blocks: BlockType instances or their arrays (BlockType::fromArray).
     *
     * @param  iterable<BlockType|array<string, mixed>>  $types
     */
    public function types(iterable $types): self
    {
        foreach ($types as $type) {
            $type = $type instanceof BlockType ? $type : BlockType::fromArray($type);
            $this->types[$type->type()] = $type;
        }

        return $this;
    }

    /**
     * The palette's presets: BlockPreset instances or their arrays
     * (BlockPreset::fromArray). A preset of a type this editor does not have
     * is not offered.
     *
     * @param  iterable<BlockPreset|array<string, mixed>>  $presets
     */
    public function presets(iterable $presets): self
    {
        foreach ($presets as $preset) {
            $preset = $preset instanceof BlockPreset ? $preset : BlockPreset::fromArray($preset);
            $this->presets[$preset->key()] = $preset;
        }

        return $this;
    }

    /**
     * One preset of the palette — the short form of presets([BlockPreset::make(...)]).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public function preset(
        string $key,
        string $label,
        string $type,
        array $data = [],
        array $meta = [],
        ?string $group = null,
        ?string $icon = null,
    ): self {
        return $this->presets([
            BlockPreset::make($key, $label, $type)->data($data)->meta($meta)->group($group)->icon($icon),
        ]);
    }

    /** @return array<string, BlockPreset> */
    public function blockPresets(): array
    {
        return $this->presets;
    }

    /**
     * The types allowed at the top level, instead of those marked root().
     *
     * @param  array<array-key, string>  $types
     */
    public function rootTypes(array $types): self
    {
        $this->rootTypes = array_values(array_map('strval', $types));

        return $this;
    }

    /**
     * Where the preview's HTML comes from: a POST of `{nodes, ...$params}`.
     * A path of the panel's site or a URL of the same origin.
     *
     * @param  array<string, mixed>  $params
     */
    public function preview(string $url, array $params = []): self
    {
        $url = trim($url);
        $sameSitePath = str_starts_with($url, '/') && ! str_starts_with($url, '//');
        if (! $sameSitePath && preg_match('#^https?://[^/\s]+#i', $url) !== 1) {
            throw new InvalidArgumentException("Layout::blockTree()->preview() takes a path or an http(s) URL, `{$url}` given");
        }
        $this->props['previewUrl'] = $url;
        $this->props['previewParams'] = (object) $params;

        return $this;
    }

    /**
     * The marker of a block in the preview's HTML: the attribute holding the
     * node's ref (`data-builder-ref` by default).
     */
    public function markerAttribute(string $attribute): self
    {
        if (preg_match('/^data-[a-z0-9-]+$/', $attribute) !== 1) {
            throw new InvalidArgumentException("Layout::blockTree()->markerAttribute() takes a data-* attribute, `{$attribute}` given");
        }
        $this->props['markerAttribute'] = $attribute;

        return $this;
    }

    /** Saves the draft through this screen method, a while after each change. */
    public function draft(string $method, ?string $permission = null): self
    {
        $this->methods['draft'] = [$method, $permission];

        return $this;
    }

    /** The publish button calls this screen method. */
    public function publish(string $method, ?string $permission = null): self
    {
        $this->methods['publish'] = [$method, $permission];

        return $this;
    }

    /** The discard button calls this screen method, after a confirmation. */
    public function discard(string $method, ?string $permission = null): self
    {
        $this->methods['discard'] = [$method, $permission];

        return $this;
    }

    /** The deepest a block may stand: 1 — the top level only. */
    public function maxDepth(int $depth): self
    {
        if ($depth < 1) {
            throw new InvalidArgumentException("Layout::blockTree()->maxDepth() must be at least 1, {$depth} given");
        }
        $this->maxDepth = $depth;

        return $this;
    }

    /** The pause after a change before the draft is saved, in ms; 0 — no autosave. */
    public function autosave(int $ms): self
    {
        $this->props['autosaveDelay'] = max(0, $ms);

        return $this;
    }

    /** The pause after a change before the preview is reloaded, in ms. */
    public function previewDelay(int $ms): self
    {
        $this->props['previewDelay'] = max(0, $ms);

        return $this;
    }

    /** The editor's height: 640 (px) or a CSS length like 'calc(100vh - 180px)'. */
    public function height(int|string $height): self
    {
        if (is_int($height)) {
            if ($height <= 0) {
                throw new InvalidArgumentException("Layout::blockTree()->height() must be positive, {$height} given");
            }
            $this->props['height'] = $height.'px';

            return $this;
        }
        $height = trim($height);
        if (preg_match('/^(\d+(\.\d+)?(px|%|vh|vw|dvh|svh|lvh|em|rem)|calc\([0-9a-z%.+\-\s]+\))$/', $height) !== 1) {
            throw new InvalidArgumentException("Layout::blockTree()->height() takes pixels or a CSS length, `{$height}` given");
        }
        $this->props['height'] = $height;

        return $this;
    }

    /** A caption above the editor. */
    public function title(string $title): self
    {
        $this->props['title'] = $title;

        return $this;
    }

    /** The state key holding the value. */
    public function source(): string
    {
        return $this->source;
    }

    /** @return array<string, BlockType> */
    public function blockTypes(): array
    {
        return $this->types;
    }

    /**
     * The validator of this editor's trees: its types, root types and depth.
     */
    public function validator(): TreeValidator
    {
        return new TreeValidator($this->types, $this->rootTypes, $this->maxDepth);
    }

    /* -----------------------------------------------------------------
     * The answers of the screen methods
     * ----------------------------------------------------------------- */

    /**
     * The draft is saved, or the tree published or discarded: the editor goes
     * on from `$value` when given (the same shape as the state's value) and
     * shows the time.
     *
     * @param  array<string, mixed>|null  $value
     * @return array<string, mixed>
     */
    public static function saved(?array $value = null, ?string $message = null, ?string $savedAt = null): array
    {
        $answer = ['blockTree' => array_filter([
            'value' => $value,
            'savedAt' => $savedAt ?? now()->toIso8601String(),
        ], static fn (mixed $v): bool => $v !== null)];
        if ($message !== null) {
            $answer['message'] = $message;
        }

        return $answer;
    }

    /**
     * The draft is saved, and the editor keeps its tree as it is now — what
     * was typed while the request was out is not lost: the new blocks'
     * temporary refs are renamed to their ids (`['new-1a2b' => 51]`), the
     * selection and the folded nodes follow, and the fingerprint is the new
     * one. Unlike saved($value), nothing else of the tree is replaced.
     *
     * `$nodes` — what the server made of some nodes, when it changed them
     * (a file's temporary path made permanent, a preset turned into a full
     * block): `[ref => ['data' => [...], 'meta' => [...]]]`, by the block's ref
     * (the new one; a temporary one is renamed by `$refs` first). Either key
     * may be left out. The editor applies them to those nodes, except the
     * fields typed in while the request was out — those keep the input and go
     * with the next draft; `data` replaces the node's data (a key left out goes
     * away), `meta` its meta. The open form keeps its inputs and their focus.
     *
     * @param  array<array-key, int|string>  $refs  temporary ref => the block's ref (id)
     * @param  array<array-key, mixed>  $nodes  ref => ['data' => array|null, 'meta' => array|null], either key optional
     * @return array<string, mixed>
     */
    public static function draftSaved(array $refs, string $fingerprint, ?string $savedAt = null, array $nodes = []): array
    {
        $map = [];
        foreach ($refs as $from => $to) {
            $map[(string) $from] = $to;
        }

        $answer = [
            'refs' => (object) $map,
            'fingerprint' => $fingerprint,
            'savedAt' => $savedAt ?? now()->toIso8601String(),
        ];

        $updates = [];
        foreach ($nodes as $ref => $node) {
            if (! is_array($node)) {
                throw new InvalidArgumentException("BlockTree::draftSaved(): the node `{$ref}` needs ['data' => [...], 'meta' => [...]]");
            }
            $update = [];
            foreach (['data', 'meta'] as $key) {
                if (array_key_exists($key, $node)) {
                    $update[$key] = (object) (array) ($node[$key] ?? []);
                }
            }
            if ($update !== []) {
                $updates[(string) $ref] = (object) $update;
            }
        }
        if ($updates !== []) {
            $answer['nodes'] = (object) $updates;
        }

        return ['blockTree' => $answer];
    }

    /**
     * The published state (or the draft) changed since the editing began:
     * nothing is published or saved, the editor shows the message and the
     * differences. In the draft's answer the autosave waits until the person
     * saves over it ("Save over" takes `$fingerprint`) or starts over.
     *
     * @param  array<array-key, string|array{ref?: int|string|null, label?: string, change?: string}>  $differences
     * @param  string|null  $fingerprint  The current fingerprint, to publish over the change on purpose.
     * @return array<string, mixed>
     */
    public static function conflict(string $message, array $differences = [], ?string $fingerprint = null): array
    {
        return ['blockTree' => ['conflict' => [
            'message' => $message,
            'differences' => array_values($differences),
            'fingerprint' => $fingerprint,
        ]]];
    }

    /**
     * The tree did not pass the checks: the editor marks the nodes. In the
     * draft's answer the autosave waits for the next change.
     *
     * @param  array<array-key, array{ref: int|string|null, path: string, field: string|null, message: string}>  $errors  TreeValidator::errors()
     * @return array<string, mixed>
     */
    public static function invalid(array $errors, ?string $message = null): array
    {
        return ['blockTree' => [
            'errors' => array_values($errors),
            'message' => $message ?? (string) __('Исправьте ошибки в блоках'),
        ]];
    }

    /* -----------------------------------------------------------------
     * Serialization
     * ----------------------------------------------------------------- */

    /**
     * The screen methods this layout calls, declared for the server's check
     * (ActionLocator), like MarkupLayout's.
     *
     * @return list<\Dskripchenko\LaravelAdmin\Contracts\Renderable>
     */
    public function childRenderables(): array
    {
        $declared = [];
        foreach ($this->methods as $role => [$method, $permission]) {
            if ($method === null) {
                continue;
            }
            $button = Button::make($this->type())->withName($this->type().'-'.$role)->method($method);
            if ($permission !== null) {
                $button->permission($permission);
            }
            $declared[] = $button;
        }

        return $declared;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        $allowed = [];
        foreach ($this->methods as $role => [$method, $permission]) {
            $allowed[$role] = $method !== null && ($permission === null || PermissionCheck::allows($permission))
                ? $method
                : null;
        }

        $types = array_values(array_map(static fn (BlockType $type): array => $type->toArray(), $this->types));
        $presets = array_values(array_map(
            static fn (BlockPreset $preset): array => $preset->toArray(),
            array_filter(
                $this->presets,
                fn (BlockPreset $preset): bool => array_diff($preset->usedTypes(), array_keys($this->types)) === [],
            ),
        ));
        $rootTypes = $this->rootTypes ?? array_values(array_map(
            static fn (BlockType $type): string => $type->type(),
            array_filter($this->types, static fn (BlockType $type): bool => $type->isRoot()),
        ));

        return [
            ...$array,
            'source' => $this->source,
            'types' => $types,
            'presets' => $presets,
            'rootTypes' => $rootTypes,
            'maxDepth' => $this->maxDepth,
            'previewUrl' => $this->props['previewUrl'] ?? null,
            'previewParams' => $this->props['previewParams'] ?? (object) [],
            'markerAttribute' => $this->props['markerAttribute'] ?? 'data-builder-ref',
            'autosaveDelay' => $this->props['autosaveDelay'] ?? 1500,
            'previewDelay' => $this->props['previewDelay'] ?? 700,
            'height' => $this->props['height'] ?? null,
            'draftMethod' => $allowed['draft'],
            'publishMethod' => $allowed['publish'],
            'discardMethod' => $allowed['discard'],
            'readOnly' => $allowed['draft'] === null && $allowed['publish'] === null,
        ];
    }
}
