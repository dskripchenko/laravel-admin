# Plugin Development

AdminPlugin — это extension-point для распространения переиспользуемых
Resource'ов, Screen'ов, Settings и Permission'ов через composer-пакет.

## Структура плагина

```
acme-admin-shop/
├── composer.json
├── src/
│   ├── ShopPlugin.php
│   ├── Resources/
│   │   ├── ProductResource.php
│   │   └── OrderResource.php
│   ├── Settings/
│   │   └── ShopSettings.php
│   └── Screens/
│       └── ShopDashboardScreen.php
└── README.md
```

## ShopPlugin

```php
namespace Acme\AdminShop;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Plugin\AdminPlugin;

final class ShopPlugin implements AdminPlugin
{
    public function name(): string { return 'acme-shop'; }
    public function version(): string { return '1.0.0'; }

    public function register(): void
    {
        // Раннее биндинг-время — никакого Admin manager'а здесь нет.
        // Можно регистрировать обычные Laravel-биндинги, миграции через
        // ServiceProvider в host'е.
    }

    public function boot(Admin $admin): void
    {
        $admin->resources([
            Resources\ProductResource::class,
            Resources\OrderResource::class,
        ]);

        $admin->screens([
            Screens\ShopDashboardScreen::class,
        ]);

        // Settings регистрируются через SettingsRegistry singleton:
        app(\Dskripchenko\LaravelAdmin\Settings\SettingsRegistry::class)
            ->add(Settings\ShopSettings::class);

        // Custom permission groups:
        app(\Dskripchenko\LaravelAdmin\Permission\PermissionRegistry::class)
            ->add(\Dskripchenko\LaravelAdmin\Permission\ItemPermission::group('Shop')
                ->addPermission('admin.shop.export', 'Экспорт заказов')
                ->addPermission('admin.shop.refund', 'Возврат платежей')
            );
    }
}
```

## Подключение в host-проекте

```php
// config/admin.php
'plugins' => [
    \Acme\AdminShop\ShopPlugin::class,
    // другие плагины...
],
```

После этого `AdminServiceProvider::bootPlugins()` инстанцирует плагины,
вызывает `register()` → `boot()`. Resource'ы, Screen'ы, Settings из
плагина автоматически появляются в admin-панели.

## Подключение миграций / view'ев

Эти задачи — стандартный Laravel ServiceProvider, рядом с AdminPlugin'ом:

```php
class ShopServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'shop');
    }
}
```

## Тестирование плагина

```php
class ShopPluginTest extends \Dskripchenko\LaravelAdmin\Testing\AdminTestCase
{
    it('boots and registers resources', function () {
        config()->set('admin.plugins', [\Acme\AdminShop\ShopPlugin::class]);
        // ... reboot ...
        expect(app(ResourceRegistry::class)->has('products'))->toBeTrue();
    });
}
```
