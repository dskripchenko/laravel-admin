<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Filter\InputFilter;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * Минимальная Eloquent-модель для тестов Resource'а.
 */
final class TestResourceUserModel extends Model
{
    protected $table = 'users';

    protected $guarded = [];
}

/**
 * Demo Resource для unit-тестов.
 */
final class TestUserResource extends Resource
{
    public static string $model = TestResourceUserModel::class;

    public function fields(): array
    {
        return [
            Input::make('name')->required()->title('Имя'),
            Input::make('email')->type('email')->required()->title('Email'),
            Input::make('password')->onCreate()->onUpdate(false)->required()->title('Пароль'),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->sort(),
            TableColumn::make('name')->sort()->search(),
            TableColumn::make('email')->copyable()->search(),
        ];
    }

    public function filters(): array
    {
        return [
            InputFilter::for('email'),
        ];
    }

    public function actions(): array
    {
        return [
            Button::make('Активировать')->method('activate'),
        ];
    }

    public function with(): array
    {
        return ['profile', 'roles'];
    }
}

it('Resource has slug pluralized from class name', function (): void {
    expect(TestUserResource::slug())->toBe('test-users');
});

it('Resource has default permission key', function (): void {
    expect(TestUserResource::permission())->toBe('admin.test-users');
});

it('Resource has humanized label', function (): void {
    expect(TestUserResource::label())->toBe('Test Users');
});

it('Resource collects searchable fields from columns', function (): void {
    $resource = new TestUserResource;
    expect($resource->searchableFields())->toBe(['name', 'email']);
});

it('Resource validation rules per context', function (): void {
    $resource = new TestUserResource;

    $createRules = $resource->validationRules('create');
    expect($createRules)->toHaveKey('name');
    expect($createRules)->toHaveKey('email');
    expect($createRules)->toHaveKey('password');

    $updateRules = $resource->validationRules('update');
    expect($updateRules)->toHaveKey('name');
    expect($updateRules)->toHaveKey('email');
    expect($updateRules)->not->toHaveKey('password');                    // onUpdate(false)
});

it('Resource modelQuery returns Eloquent Builder', function (): void {
    $resource = new TestUserResource;
    $query = $resource->modelQuery();
    expect($query)->toBeInstanceOf(Illuminate\Database\Eloquent\Builder::class);
});

it('Resource indexQuery returns same as modelQuery by default', function (): void {
    $resource = new TestUserResource;
    expect($resource->indexQuery()->toSql())->toBe($resource->modelQuery()->toSql());
});

it('Resource throws when $model is not set', function (): void {
    $anonymous = new class extends Resource
    {
        // нет static $model
    };

    expect(fn () => $anonymous->modelQuery())
        ->toThrow(RuntimeException::class);
});

it('Resource meta payload contains all expected sections', function (): void {
    $resource = new TestUserResource;
    $meta = $resource->meta();

    expect($meta['slug'])->toBe('test-users');
    expect($meta['label'])->toBe('Test Users');
    expect($meta['permissions'])->toHaveKey('view');
    expect($meta['permissions']['view'])->toBe('admin.test-users.view');
    expect($meta['fields'])->toHaveCount(3);
    expect($meta['columns'])->toHaveCount(3);
    expect($meta['filters'])->toHaveCount(1);
    expect($meta['actions'])->toHaveCount(1);
    expect($meta['searchable'])->toBe(['name', 'email']);
    expect($meta['with'])->toBe(['profile', 'roles']);
    expect($meta['features'])->toHaveKey('softDeletes');
});

it('ResourceRegistry add/get/resolve via DI', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->clear();
    $registry->add(TestUserResource::class);

    expect($registry->has('test-users'))->toBeTrue();
    expect($registry->get('test-users'))->toBe(TestUserResource::class);

    $resolved = $registry->resolve('test-users');
    expect($resolved)->toBeInstanceOf(TestUserResource::class);
});

it('ResourceRegistry rejects non-Resource classes', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->clear();

    expect(fn () => $registry->add(stdClass::class))
        ->toThrow(InvalidArgumentException::class);
});

it('Admin::resources delegates to registry', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->clear();

    $manager = app(Dskripchenko\LaravelAdmin\Admin::class);
    $manager->resources([TestUserResource::class]);

    expect($manager->getResources())->toHaveKey('test-users');

    $resolved = $manager->resolveResource('test-users');
    expect($resolved)->toBeInstanceOf(TestUserResource::class);
});
