<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout\BlockTree;

use Dskripchenko\LaravelAdmin\Field\Field;
use Dskripchenko\LaravelAdmin\I18n\Localize;
use InvalidArgumentException;

/**
 * One type of block in a block tree (see BlockTree): what the palette offers,
 * where it may stand, the form of its properties and the caption of its node.
 *
 *     BlockType::make(TextBlock::class, 'Text with buttons')
 *         ->icon('type')
 *         ->root()                              // may stand at the top level
 *         ->accepts([ButtonBlock::class])       // the types allowed inside it
 *         ->titleFrom(['subtitle', 'description'])
 *         ->fields([
 *             Input::make('subtitle')->title('Subtitle'),
 *             Textarea::make('description')->title('Description'),
 *         ])
 *
 * The type is any string: a model class, a slug. The fields are the panel's
 * ordinary fields; their names are the keys of the node's `data`, and their
 * rules are what TreeValidator checks the data with.
 */
final class BlockType
{
    private string $label;

    private ?string $icon = null;

    private ?string $description = null;

    private ?string $group = null;

    private ?string $thumbnail = null;

    private bool $root = false;

    /** @var list<string> */
    private array $accepts = [];

    /** @var list<string> */
    private array $titleFrom = [];

    /** @var list<Field> */
    private array $fields = [];

    private bool $rendersOnSite = true;

    /** @var list<array<string, mixed>> Fields given already serialized (fromArray). */
    private array $rawFields = [];

    /** @var array<string, mixed> */
    private array $defaults = [];

    private function __construct(private readonly string $type, ?string $label)
    {
        if (trim($type) === '') {
            throw new InvalidArgumentException('BlockType::make() needs a type');
        }
        $this->label = $label ?? $type;
    }

    public static function make(string $type, ?string $label = null): self
    {
        return new self($type, $label);
    }

    /**
     * A type described as an array — what a schema class of the host may
     * already produce:
     *
     *     ['type' => ..., 'label' => ..., 'icon' => ..., 'root' => true,
     *      'children' => [...], 'titleFrom' => [...], 'fields' => [Field|array, ...],
     *      'rendersOnSite' => true, 'description' => ..., 'group' => ..., 'thumbnail' => ...,
     *      'defaults' => [...]]
     *
     * A field given as an array is passed to the panel as it is.
     *
     * @param  array<string, mixed>  $type
     */
    public static function fromArray(array $type): self
    {
        $instance = new self((string) ($type['type'] ?? ''), isset($type['label']) ? (string) $type['label'] : null);
        $instance->icon = isset($type['icon']) ? (string) $type['icon'] : null;
        $instance->description = isset($type['description']) ? (string) $type['description'] : null;
        $instance->group = isset($type['group']) ? (string) $type['group'] : null;
        $instance->thumbnail = isset($type['thumbnail']) ? (string) $type['thumbnail'] : null;
        $instance->root = (bool) ($type['root'] ?? false);
        $instance->accepts = array_values(array_map('strval', (array) ($type['children'] ?? $type['accepts'] ?? [])));
        $instance->titleFrom = array_values(array_map('strval', (array) ($type['titleFrom'] ?? [])));
        $instance->rendersOnSite = (bool) ($type['rendersOnSite'] ?? true);
        $instance->defaults = (array) ($type['defaults'] ?? []);
        $instance->rawFields = array_values(array_filter((array) ($type['fields'] ?? []), 'is_array'));
        $instance->fields = array_values(array_filter((array) ($type['fields'] ?? []), static fn (mixed $f): bool => $f instanceof Field));

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

    /** May stand at the top level of the tree. */
    public function root(bool $root = true): self
    {
        $this->root = $root;

        return $this;
    }

    /**
     * The types allowed inside this one; none — a leaf.
     *
     * @param  array<array-key, string>  $types
     */
    public function accepts(array $types): self
    {
        $this->accepts = array_values(array_map('strval', $types));

        return $this;
    }

    /**
     * The node's caption in the tree: the first of these data keys with a
     * non-empty value, without tags, cut to 80 characters. Without it the
     * caption is the node's `meta.title` sent by the server, else the label.
     *
     * @param  string|array<array-key, string>  $keys
     */
    public function titleFrom(string|array $keys): self
    {
        $this->titleFrom = array_values(array_map('strval', (array) $keys));

        return $this;
    }

    /**
     * @param  array<array-key, Field>  $fields
     */
    public function fields(array $fields): self
    {
        $this->fields = array_values($fields);

        return $this;
    }

    /**
     * Whether the site draws this type. A type it does not is marked "not shown"
     * in the tree.
     */
    public function rendersOnSite(bool $renders = true): self
    {
        $this->rendersOnSite = $renders;

        return $this;
    }

    /**
     * The data of a new block of this type; the fields' defaults fill the rest.
     *
     * @param  array<string, mixed>  $data
     */
    public function defaults(array $data): self
    {
        $this->defaults = $data;

        return $this;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function isRoot(): bool
    {
        return $this->root;
    }

    /** @return list<string> */
    public function acceptedTypes(): array
    {
        return $this->accepts;
    }

    public function allows(string $child): bool
    {
        return in_array($child, $this->accepts, true);
    }

    /** @return list<Field> */
    public function fieldList(): array
    {
        return $this->fields;
    }

    /**
     * The validation rules of the data, by field name.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [];
        foreach ($this->fields as $field) {
            $own = $field->getRules();
            if ($own !== []) {
                $rules[$field->name()] = $own;
            }
        }
        foreach ($this->rawFields as $field) {
            if (isset($field['name']) && is_array($field['rules'] ?? null) && $field['rules'] !== []) {
                $rules[(string) $field['name']] = $field['rules'];
            }
        }

        return $rules;
    }

    /**
     * The labels of the fields, for the validator's messages.
     *
     * @return array<string, string>
     */
    public function attributeNames(): array
    {
        $names = [];
        foreach ($this->fields as $field) {
            $names[$field->name()] = (string) ($field->toArray()['label'] ?? $field->name());
        }
        foreach ($this->rawFields as $field) {
            if (isset($field['name'])) {
                $names[(string) $field['name']] = (string) ($field['label'] ?? $field['name']);
            }
        }

        return $names;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $defaults = [];
        foreach ($this->fields as $field) {
            if ($field->getDefaultValue() !== null) {
                $defaults[$field->name()] = $field->getDefaultValue();
            }
        }

        return [
            'type' => $this->type,
            'label' => (string) Localize::string($this->label),
            'icon' => $this->icon,
            'description' => $this->description === null ? null : (string) Localize::string($this->description),
            'group' => $this->group === null ? null : (string) Localize::string($this->group),
            'thumbnail' => $this->thumbnail,
            'root' => $this->root,
            'accepts' => $this->accepts,
            'titleFrom' => $this->titleFrom,
            'rendersOnSite' => $this->rendersOnSite,
            'defaults' => (object) [...$defaults, ...$this->defaults],
            'fields' => [
                ...array_map(static fn (Field $field): array => $field->toArray(), array_values(array_filter($this->fields, static fn (Field $f): bool => $f->isVisible()))),
                ...$this->rawFields,
            ],
        ];
    }
}
