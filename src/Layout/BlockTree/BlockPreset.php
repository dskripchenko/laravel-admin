<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout\BlockTree;

use Dskripchenko\LaravelAdmin\I18n\Localize;
use InvalidArgumentException;

/**
 * A preset of the block tree's palette (see BlockTree::presets()): a ready
 * block offered next to the types — a node of an ordinary type with its own
 * data, meta and, maybe, children. Its pick inserts that node with a new
 * temporary ref; nothing else tells it from a block of its type.
 *
 *     BlockPreset::make('library-42', 'Footer (shared)', 'text')
 *         ->group('Shared blocks')
 *         ->icon('library')
 *         ->description('Edited in the library')
 *         ->data(['body' => '<p>©</p>'])
 *         ->meta(['library' => 42, 'fields' => ['body' => ['inherited' => true, 'base' => '<p>©</p>']]])
 *
 * The rules are its type's — where it may stand, what may go inside — or the
 * meta's `accepts`; a preset lets nothing in that its type would not, and
 * TreeValidator checks the node as any other. The key names the preset in the
 * palette (its test id: `block-tree-palette-preset-{key}`).
 */
final class BlockPreset
{
    private ?string $icon = null;

    private ?string $description = null;

    private ?string $group = null;

    private ?string $thumbnail = null;

    /** @var array<string, mixed> */
    private array $data = [];

    /** @var array<string, mixed> */
    private array $meta = [];

    /** @var list<array<string, mixed>> */
    private array $children = [];

    private function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $type,
    ) {
        if (trim($key) === '') {
            throw new InvalidArgumentException('BlockPreset::make() needs a key');
        }
        if (trim($type) === '') {
            throw new InvalidArgumentException("BlockPreset::make('{$key}') needs a type");
        }
    }

    public static function make(string $key, string $label, string $type): self
    {
        return new self($key, $label, $type);
    }

    /**
     * A preset described as an array:
     *
     *     ['key' => ..., 'label' => ..., 'type' => ..., 'data' => [...], 'meta' => [...],
     *      'children' => [['type' => ..., 'data' => [...], 'meta' => [...], 'children' => [...]]],
     *      'icon' => ..., 'description' => ..., 'group' => ..., 'thumbnail' => ...]
     *
     * @param  array<string, mixed>  $preset
     */
    public static function fromArray(array $preset): self
    {
        $key = (string) ($preset['key'] ?? '');
        $instance = new self($key, (string) ($preset['label'] ?? $key), (string) ($preset['type'] ?? ''));
        $instance->icon = isset($preset['icon']) ? (string) $preset['icon'] : null;
        $instance->description = isset($preset['description']) ? (string) $preset['description'] : null;
        $instance->group = isset($preset['group']) ? (string) $preset['group'] : null;
        $instance->thumbnail = isset($preset['thumbnail']) ? (string) $preset['thumbnail'] : null;
        $instance->data((array) ($preset['data'] ?? []));
        $instance->meta((array) ($preset['meta'] ?? []));
        $instance->children((array) ($preset['children'] ?? []));

        return $instance;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /** A line under the label in the palette. */
    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /** The palette's group heading. */
    public function group(?string $group): self
    {
        $this->group = $group;

        return $this;
    }

    /** A picture of the block for the palette: a URL. */
    public function thumbnail(?string $url): self
    {
        $this->thumbnail = $url;

        return $this;
    }

    /**
     * The data of the new node, as it is: the type's defaults are not mixed in.
     *
     * @param  array<string, mixed>  $data
     */
    public function data(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * The meta of the new node — what the server reads back from the draft
     * (`library`, `fields`, `accepts`, `locked`, `title`...).
     *
     * @param  array<string, mixed>  $meta
     */
    public function meta(array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    /**
     * The nodes inside the new one: `['type' => ..., 'data' => [...], 'meta' => [...], 'children' => [...]]`,
     * without refs (the panel gives them). They must fit the rules, or the
     * preset is not offered.
     *
     * @param  array<array-key, array<string, mixed>>  $children
     */
    public function children(array $children): self
    {
        $this->children = array_values(array_map(fn (mixed $child): array => $this->child($child), $children));

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function blockType(): string
    {
        return $this->type;
    }

    /**
     * The types the preset's nodes have — its own and its children's.
     *
     * @return list<string>
     */
    public function usedTypes(): array
    {
        $types = [$this->type];
        $walk = static function (array $children) use (&$walk, &$types): void {
            foreach ($children as $child) {
                $types[] = (string) $child['type'];
                $walk($child['children'] ?? []);
            }
        };
        $walk($this->children);

        return array_values(array_unique($types));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => (string) Localize::string($this->label),
            'type' => $this->type,
            'icon' => $this->icon,
            'description' => $this->description === null ? null : (string) Localize::string($this->description),
            'group' => $this->group === null ? null : (string) Localize::string($this->group),
            'thumbnail' => $this->thumbnail,
            'data' => (object) $this->data,
            'meta' => (object) $this->meta,
            'children' => $this->children,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function child(mixed $child): array
    {
        if (! is_array($child) || ! isset($child['type']) || trim((string) $child['type']) === '') {
            throw new InvalidArgumentException("BlockPreset '{$this->key}': a child needs a type");
        }

        return [
            'type' => (string) $child['type'],
            'data' => (object) (array) ($child['data'] ?? []),
            'meta' => (object) (array) ($child['meta'] ?? []),
            'children' => array_values(array_map(fn (mixed $c): array => $this->child($c), (array) ($child['children'] ?? []))),
        ];
    }
}
