<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Menu;

use Dskripchenko\LaravelAdmin\I18n\Localize;
use Dskripchenko\LaravelAdmin\Permission\PermissionCheck;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;

/**
 * An item of the top bar's "+ Create" menu, and an action of the ⌘K palette.
 *
 *   $admin->menu()->quickCreate([
 *       QuickCreate::resource(NewsResource::class),           // «Новость», /r/news/create, admin.news.create
 *       QuickCreate::resource('vacancies', 'Вакансию'),
 *       QuickCreate::make('import', 'Импорт прайса', '/screens/price-import')->permission('admin.prices.import'),
 *   ]);
 *
 * A resource's item is shown to the users with its `.create` permission; an
 * arbitrary one — to those with every key of permission() (none — everyone).
 */
final class QuickCreate
{
    private ?string $resource = null;

    private ?string $icon = null;

    /** @var list<string> */
    private array $permissions = [];

    private function __construct(
        private string $key,
        private string $label,
        private ?string $url,
    ) {}

    public static function make(string $key, string $label, string $url): self
    {
        return new self($key, $label, $url);
    }

    /**
     * A resource's create page. The label defaults to the resource's singular
     * name (Resource::singularLabel()), else its label.
     *
     * @param  class-string<resource>|string  $resource  The class or the slug
     */
    public static function resource(string $resource, ?string $label = null): self
    {
        $slug = is_subclass_of($resource, Resource::class) ? $resource::slug() : $resource;
        $instance = new self('resource.'.$slug, $label ?? '', null);
        $instance->resource = $slug;

        return $instance;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @param  list<string>|string|null  $permissions
     */
    public function permission(array|string|null $permissions): self
    {
        $this->permissions = PermissionCheck::normalize($permissions);

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * The item as the signed-in user sees it, or null when it is not for them.
     *
     * @return array{key: string, label: string, url: string, icon: string|null}|null
     */
    public function toArray(ResourceRegistry $resources, string $panel = 'admin'): ?array
    {
        $label = $this->label;
        $url = $this->url;
        $icon = $this->icon;
        $permissions = $this->permissions;

        if ($this->resource !== null) {
            if (! array_key_exists($this->resource, $resources->all($panel))) {
                return null;
            }
            $instance = $resources->resolve($this->resource, $panel);
            if ($instance === null || $instance->fields() === []) {
                return null;
            }
            $class = $instance::class;
            if ($label === '') {
                $singular = $class::localizedSingularLabel();
                $label = $singular !== null ? \Illuminate\Support\Str::ucfirst($singular) : $class::label();
            }
            $url = '/r/'.$this->resource.'/create';
            $icon ??= $class::$icon ?? null;
            if ($permissions === [] && $class::permission() !== '') {
                $permissions = [$class::permission().'.create'];
            }
        }

        if ($url === null || ! PermissionCheck::allows($permissions)) {
            return null;
        }

        return [
            'key' => $this->key,
            'label' => (string) Localize::string($label),
            'url' => $url,
            'icon' => $icon,
        ];
    }
}
