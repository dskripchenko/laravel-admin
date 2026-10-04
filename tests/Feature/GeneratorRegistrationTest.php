<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Console\Support\AdminPluginUpdater;
use Dskripchenko\LaravelAdmin\Console\Support\PhpListEditor;
use Dskripchenko\LaravelAdmin\Console\Support\ResourceWriter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;

/**
 * How the generators register what they write: the formatting-preserving
 * list insert, the plugin's registration in config('admin.plugins'), the
 * class and slug names, and the wizards' output.
 */

/**
 * Points the application at an empty host project in a temp directory, so
 * that the generators write there and not into the testbench skeleton.
 */
function generatorSandbox(?string $config = null): string
{
    $dir = sys_get_temp_dir().'/laravel-admin-host-'.uniqid();
    mkdir($dir.'/config', 0755, true);
    if ($config !== null) {
        file_put_contents($dir.'/config/admin.php', $config);
    }
    app()->setBasePath($dir);
    app()->useConfigPath($dir.'/config');
    $GLOBALS['generatorSandboxes'][] = $dir;

    return $dir;
}

afterEach(function (): void {
    foreach ($GLOBALS['generatorSandboxes'] ?? [] as $dir) {
        (new Filesystem)->deleteDirectory($dir);
    }
    $GLOBALS['generatorSandboxes'] = [];
});

function appendTo(string $source, string $method, string $item): string
{
    $open = PhpListEditor::findMethodCallList($source, $method);
    expect($open)->not->toBeNull();

    return (string) PhpListEditor::append($source, (int) $open, $item);
}

it('appends to a one-line list on the same line', function (): void {
    expect(appendTo('<?php $admin->resources([PostResource::class]);', 'resources', 'TagResource::class'))
        ->toBe('<?php $admin->resources([PostResource::class, TagResource::class]);');
    expect(appendTo('<?php $admin->resources([]);', 'resources', 'TagResource::class'))
        ->toBe('<?php $admin->resources([TagResource::class]);');
    expect(appendTo('<?php $admin->resources([A::class, ]);', 'resources', 'B::class'))
        ->toBe('<?php $admin->resources([A::class, B::class]);');
});

it('appends to a multi-line list on a new line with the items\' indentation', function (): void {
    $source = <<<'PHP'
<?php
        $admin->resources([
            PostResource::class,
            // the tags
            TagResource::class
        ]);
        $admin->screen([
        ]);
PHP;

    expect(appendTo($source, 'resources', 'UserResource::class'))->toBe(<<<'PHP'
<?php
        $admin->resources([
            PostResource::class,
            // the tags
            TagResource::class,
            UserResource::class,
        ]);
        $admin->screen([
        ]);
PHP);

    expect(appendTo($source, 'screen', 'ContactUsScreen::class'))->toContain(<<<'PHP'
        $admin->screen([
            ContactUsScreen::class,
        ]);
PHP);
});

it('finds the plugins key of the config, not the commented example', function (): void {
    $config = <<<'PHP'
<?php

return [
    /*
    | 'panels' => ['client' => ['plugins' => [App\Admin\ClientPanelPlugin::class]]],
    */
    'panels' => [
        'client' => ['plugins' => []],
    ],

    'plugins' => [
        // \Vendor\Pack\PackPlugin::class,
    ],
];
PHP;

    $open = PhpListEditor::findConfigKeyList($config, 'plugins');
    expect(PhpListEditor::append($config, (int) $open, '\App\Admin\AdminPlugin::class'))->toContain(<<<'PHP'
    'plugins' => [
        // \Vendor\Pack\PackPlugin::class,
        \App\Admin\AdminPlugin::class,
    ],
];
PHP)->toContain("'client' => ['plugins' => []],");
});

it('derives class names and slugs word by word', function (): void {
    $writer = new ResourceWriter(new Filesystem);

    expect($writer->classNameFor('Contact us', 'Screen'))->toBe('ContactUsScreen');
    expect($writer->slugFor('Contact us'))->toBe('contact-us');
    expect($writer->classNameFor('Blog post'))->toBe('BlogPostResource');
    expect($writer->resourceSlugFor('Blog post'))->toBe('blog-posts');
    expect($writer->classNameFor('Order screen', 'Screen'))->toBe('OrderScreen');
    expect($writer->classNameFor('2fa log'))->toBe('Admin2faLogResource');
    expect($writer->classNameFor('Статья'))->toMatch('/^[A-Z][A-Za-z0-9]*Resource$/');
});

it('adds a newly created plugin to config/admin.php', function (): void {
    $dir = generatorSandbox((string) file_get_contents(__DIR__.'/../../config/admin.php'));
    config(['admin.plugins' => []]);

    $updater = new AdminPluginUpdater(new Filesystem);
    $reg = $updater->registerResource('App\\Admin\\Resources\\PostResource');
    expect($reg['path'])->toBe($dir.'/app/Admin/AdminPlugin.php');

    $result = $updater->ensurePluginRegistered($reg['path']);
    expect($result['status'])->toBe('registered')
        ->and($result['class'])->toBe('App\\Admin\\AdminPlugin');

    $config = (string) file_get_contents($dir.'/config/admin.php');
    expect($config)->toContain("        \\App\\Admin\\AdminPlugin::class,\n    ],");
    // Still a valid config.
    expect((require $dir.'/config/admin.php')['plugins'])->toBe(['App\\Admin\\AdminPlugin']);

    // Idempotent.
    expect($updater->ensurePluginRegistered($reg['path'])['status'])->toBe('listed');

    // The plugin's lists keep their shape.
    $updater->registerResource('App\\Admin\\Resources\\TagResource');
    expect((string) file_get_contents($reg['path']))->toContain(<<<'PHP'
        $admin->resources([
            PostResource::class,
            TagResource::class,
        ]);
PHP);
});

it('prints the line to add when there is no published config', function (): void {
    generatorSandbox();
    config(['admin.plugins' => []]);

    $updater = new AdminPluginUpdater(new Filesystem);
    $reg = $updater->registerScreen('App\\Admin\\Screens\\ContactUsScreen');
    $result = $updater->ensurePluginRegistered($reg['path']);

    expect($result['status'])->toBe('manual')
        ->and($result['instructions'])->toContain('\\App\\Admin\\AdminPlugin::class')
        ->and($result['instructions'])->toContain('vendor:publish --tag=admin-config');
});

it('leaves the config alone when the plugin is already loaded', function (): void {
    $dir = generatorSandbox("<?php\n\nreturn ['plugins' => []];\n");
    config(['admin.plugins' => ['App\\Admin\\AdminPlugin']]);

    $updater = new AdminPluginUpdater(new Filesystem);
    $reg = $updater->registerResource('App\\Admin\\Resources\\PostResource');

    expect($updater->ensurePluginRegistered($reg['path'])['status'])->toBe('listed');
    expect((string) file_get_contents($dir.'/config/admin.php'))->toBe("<?php\n\nreturn ['plugins' => []];\n");
});

it('admin:make-screen names "Contact us" ContactUsScreen with the slug contact-us', function (): void {
    $dir = generatorSandbox("<?php\n\nreturn [\n    'plugins' => [\n    ],\n];\n");
    config(['admin.plugins' => []]);

    $this->artisan('admin:make-screen')
        ->expectsQuestion('Title (e.g. Contact the team)', 'Contact us')
        ->expectsQuestion('Slug', 'contact-us')
        ->expectsQuestion('Description (optional)', '')
        ->expectsChoice('Which form fields?', ['text'], ['text' => 'Input (text)', 'email' => 'Input type=email', 'textarea' => 'Textarea', 'number' => 'Number', 'select' => 'Select', 'date' => 'DatePicker', 'switch' => 'Switcher (boolean)'])
        ->expectsQuestion("Name of the 'text' field", 'name')
        ->expectsConfirmation("Add a 'Send' button calling a send() method?", 'yes')
        ->expectsQuestion('Permission (empty: any signed-in administrator)', '')
        ->expectsConfirmation('Add to the menu?', 'no')
        ->doesntExpectOutputToContain('npm run build')
        ->assertSuccessful();

    $source = (string) file_get_contents($dir.'/app/Admin/Screens/ContactUsScreen.php');
    expect($source)->toContain('final class ContactUsScreen')->toContain("return 'contact-us';");
    expect((string) file_get_contents($dir.'/config/admin.php'))->toContain('\\App\\Admin\\AdminPlugin::class,');
});

it('admin:make-section writes a model, a resource and registers the plugin, with no npm step', function (): void {
    Schema::create('gen_widgets', function (Blueprint $t): void {
        $t->id();
        $t->string('title');
        $t->boolean('enabled')->default(true);
        $t->timestamps();
    });
    $dir = generatorSandbox("<?php\n\nreturn ['plugins' => [Vendor\\Pack\\PackPlugin::class]];\n");
    config(['admin.plugins' => []]);

    $this->artisan('admin:make-section')
        ->expectsQuestion('Singular label (e.g. Article)', 'Gen widget')
        ->expectsQuestion('Plural label (for the table and the menu)', 'Gen widgets')
        ->expectsChoice('Data source', 'table', ['model' => 'Eloquent model (auto-discovered)', 'table' => 'Database table (no model)'])
        ->expectsQuestion('Choose a table', 'gen_widgets')
        ->expectsQuestion('Form fields (create/edit)', ['title', 'enabled'])
        ->expectsQuestion('Table columns (list)', ['id', 'title', 'enabled'])
        ->expectsQuestion('Base permission (derived: .view/.create/.update/.delete)', 'admin.gen-widgets')
        ->expectsQuestion('Lucide icon name (see lucide.dev)', 'box')
        ->expectsQuestion('Sidebar group (empty for none)', '')
        ->expectsConfirmation('Add to the menu?', 'no')
        ->expectsConfirmation('Create a role with these permissions?', 'no')
        ->doesntExpectOutputToContain('npm run build')
        ->assertSuccessful();

    expect(file_exists($dir.'/app/Models/GenWidget.php'))->toBeTrue();
    $resource = (string) file_get_contents($dir.'/app/Admin/Resources/GenWidgetResource.php');
    expect($resource)
        ->toContain("Switcher::make('enabled')")
        ->toContain("return 'gen-widgets';");
    expect((string) file_get_contents($dir.'/config/admin.php'))
        ->toBe("<?php\n\nreturn ['plugins' => [Vendor\\Pack\\PackPlugin::class, \\App\\Admin\\AdminPlugin::class]];\n");
});
