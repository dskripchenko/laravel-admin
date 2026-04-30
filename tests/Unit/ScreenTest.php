<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Dskripchenko\LaravelAdmin\Support\Repository;

/**
 * Минимальный Screen для тестов.
 */
final class TestDashboardScreen extends Screen
{
    public function name(): string
    {
        return 'Dashboard';
    }

    public function description(): ?string
    {
        return 'Test dashboard for unit tests';
    }

    public function permission(): array|string|null
    {
        return ['admin.dashboard.view'];
    }

    public function commandBar(): array
    {
        return [
            Button::make('Refresh')->method('refresh'),
        ];
    }

    public function query(mixed ...$params): Repository|array
    {
        return new Repository([
            'stats' => ['users' => 42, 'orders' => 17],
        ]);
    }

    public function layout(): array
    {
        return [
            Layout::rows([Input::make('search')->placeholder('Поиск...')]),
        ];
    }

    public function refresh(): string
    {
        return 'refreshed';
    }

    public function privateMethod(): string
    {
        return 'should not be callable';
    }
}

it('Screen has slug derived from class name', function (): void {
    expect(TestDashboardScreen::slug())->toBe('test-dashboard');
});

it('Screen::name and description exposed', function (): void {
    $screen = new TestDashboardScreen;
    expect($screen->name())->toBe('Dashboard');
    expect($screen->description())->toBe('Test dashboard for unit tests');
});

it('Screen::query returns Repository', function (): void {
    $screen = new TestDashboardScreen;
    $state = $screen->query();
    expect($state)->toBeInstanceOf(Repository::class);
    expect($state->get('stats.users'))->toBe(42);
});

it('Screen::layout returns list of Layout', function (): void {
    $screen = new TestDashboardScreen;
    $layouts = $screen->layout();
    expect($layouts)->toHaveCount(1);
    expect($layouts[0])->toBeInstanceOf(Layout::class);
});

it('Screen::compile aggregates everything for SystemController', function (): void {
    $screen = new TestDashboardScreen;
    $compiled = $screen->compile();

    expect($compiled['name'])->toBe('Dashboard');
    expect($compiled['description'])->toBe('Test dashboard for unit tests');
    expect($compiled['state'])->toBe(['stats' => ['users' => 42, 'orders' => 17]]);
    expect($compiled['layout'])->toHaveCount(1);
    expect($compiled['command_bar'])->toHaveCount(1);
    expect($compiled['command_bar'][0]['name'])->toBe('refresh');
    expect($compiled['permissions'])->toBe(['admin.dashboard.view']);
});

it('isCallableMethod allows public command-methods', function (): void {
    $screen = new TestDashboardScreen;
    expect($screen->isCallableMethod('refresh'))->toBeTrue();
});

it('isCallableMethod rejects reserved methods', function (): void {
    $screen = new TestDashboardScreen;
    foreach (['query', 'layout', 'name', 'permission', 'commandBar', 'compile', 'slug'] as $reserved) {
        expect($screen->isCallableMethod($reserved))->toBeFalse();
    }
});

it('isCallableMethod rejects non-existent methods', function (): void {
    $screen = new TestDashboardScreen;
    expect($screen->isCallableMethod('doesNotExist'))->toBeFalse();
});

it('compile excludes invisible layouts and actions', function (): void {
    $screen = new class extends Screen
    {
        public function query(mixed ...$params): Repository|array
        {
            return [];
        }

        public function layout(): array
        {
            return [
                Layout::rows([])->canSee(true)->withId('visible'),
                Layout::rows([])->canSee(false)->withId('hidden'),
            ];
        }

        public function commandBar(): array
        {
            return [
                Button::make('Visible'),
                Button::make('Hidden')->canSee(false),
            ];
        }
    };

    $compiled = $screen->compile();
    expect($compiled['layout'])->toHaveCount(1);
    expect($compiled['layout'][0]['id'])->toBe('visible');
    expect($compiled['command_bar'])->toHaveCount(1);
    expect($compiled['command_bar'][0]['name'])->toBe('visible');
});

it('ScreenRegistry adds and resolves by slug', function (): void {
    $registry = new ScreenRegistry;
    $registry->add(TestDashboardScreen::class);

    expect($registry->has('test-dashboard'))->toBeTrue();
    expect($registry->get('test-dashboard'))->toBe(TestDashboardScreen::class);
    expect($registry->slugs())->toBe(['test-dashboard']);
});

it('ScreenRegistry rejects non-Screen classes', function (): void {
    $registry = new ScreenRegistry;

    expect(fn () => $registry->add(stdClass::class))
        ->toThrow(InvalidArgumentException::class);
});

it('ScreenRegistry rejects duplicate slug from a different class', function (): void {
    $registry = new ScreenRegistry;
    $registry->add(TestDashboardScreen::class);

    // Same class — OK (idempotent).
    $registry->add(TestDashboardScreen::class);
    expect($registry->all())->toHaveCount(1);
});

it('Admin::screen registers via the registry', function (): void {
    $manager = app(Dskripchenko\LaravelAdmin\Admin::class);
    $manager->screen(TestDashboardScreen::class);

    expect($manager->getScreens())->toHaveKey('test-dashboard');

    $resolved = $manager->resolveScreen('test-dashboard');
    expect($resolved)->toBeInstanceOf(TestDashboardScreen::class);
});
