<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedCreateScreen;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedEditScreen;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Dskripchenko\LaravelAdmin\Widget\ChartWidget;
use Dskripchenko\LaravelAdmin\Widget\RecentListWidget;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;

/**
 * Resource::singularLabel(): the name of one record, for the phrases the
 * plural label read wrong in — "Создать: Авторы", "Create: Authors".
 */
final class SingularBlogPostResource extends Resource
{
    public static string $model = TestResourceUserModel::class;
}

final class SingularApiKeyResource extends Resource
{
    public static string $model = TestResourceUserModel::class;

    public static function label(): string
    {
        return 'API Keys';
    }
}

final class SingularCyrillicResource extends Resource
{
    public static string $model = TestResourceUserModel::class;

    public static function label(): string
    {
        return 'Авторы';
    }
}

final class SingularOverriddenResource extends Resource
{
    public static string $model = TestResourceUserModel::class;

    public static function label(): string
    {
        return 'Authors';
    }

    public static function singularLabel(): ?string
    {
        return 'author';
    }
}

it('derives an English singular from the label', function (): void {
    expect(SingularBlogPostResource::label())->toBe('Singular Blog Posts');
    expect(SingularBlogPostResource::singularLabel())->toBe('singular blog post');
    expect(SingularApiKeyResource::singularLabel())->toBe('API key');
});

it('has no automatic singular for a label in another script', function (): void {
    expect(SingularCyrillicResource::singularLabel())->toBeNull();
    expect((new SingularCyrillicResource)->meta()['singular_label'])->toBeNull();
});

it('sends the singular in the manifest, translated per request', function (): void {
    app()->setLocale('en');
    expect((new SingularOverriddenResource)->meta()['singular_label'])->toBe('author');

    Lang::addLines(['*.Authors' => 'Авторы', '*.author' => 'автор'], 'ru');
    app()->setLocale('ru');
    expect((new SingularOverriddenResource)->meta())
        ->toMatchArray(['label' => 'Авторы', 'singular_label' => 'автор']);
});

it('drops a singular left in another language than its translated label', function (): void {
    // "Authors" reaches Russian, "author" does not: a Russian panel would
    // read "Создать: author", so the panel gets no singular at all.
    Lang::addLines(['*.Authors' => 'Авторы'], 'ru');
    app()->setLocale('ru');
    expect(SingularOverriddenResource::localizedSingularLabel())->toBeNull();
});

it('names the generated create and edit screens by the singular', function (): void {
    app()->setLocale('en');
    expect((new GeneratedCreateScreen(new SingularOverriddenResource))->name())->toBe('Create author');
    expect((new GeneratedEditScreen(new SingularOverriddenResource))->name())->toBe('Edit author');
    // Without one: a phrase that needs no singular.
    expect((new GeneratedCreateScreen(new SingularCyrillicResource))->name())->toBe('New record: Авторы');

    app()->setLocale('ru');
    expect((new GeneratedCreateScreen(new SingularCyrillicResource))->name())->toBe('Новая запись: Авторы');
    expect((new GeneratedEditScreen(new SingularCyrillicResource))->name())->toBe('Редактирование записи: Авторы');
});

it('formats RecentListWidget columns through TableColumn', function (): void {
    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->timestamps();
        });
    }
    TestResourceUserModel::query()->create(['name' => 'alice', 'email' => 'a@example.com']);

    $data = RecentListWidget::make()
        ->model(TestResourceUserModel::class)
        ->column('email', 'Email')
        ->column(TableColumn::make('name')->label('Name')->format(static fn (mixed $v, array $row): string => strtoupper((string) $v).' <'.$row['email'].'>'))
        ->column(TableColumn::make('id')->asMoney('USD', 0)->align('right'))
        ->column(TableColumn::make('created_at')->asDateTime('d.m.Y H:i'), 'Created')
        ->data();

    expect($data['rows'][0]['name'])->toBe('ALICE <a@example.com>');
    expect($data['columns'][2])->toMatchArray([
        'column' => 'id', 'name' => 'id', 'type' => 'money', 'preset' => 'money', 'align' => 'right',
        'meta' => ['currency' => 'USD', 'decimals' => 0],
    ]);
    expect($data['columns'][3])->toMatchArray(['label' => 'Created', 'preset' => 'datetime', 'meta' => ['format' => 'd.m.Y H:i']]);
});

it('lets a chart show its values as money', function (): void {
    expect(ChartWidget::make()->dataset('Revenue', [1591285])->data()['format'])->toBeNull();
    expect(ChartWidget::make()->dataset('Revenue', [1591285])->money('usd')->data()['format'])
        ->toBe(['style' => 'currency', 'currency' => 'USD', 'decimals' => 0]);
    expect(ChartWidget::make()->precision(2)->data()['format'])->toBe(['style' => 'decimal', 'decimals' => 2]);
});
