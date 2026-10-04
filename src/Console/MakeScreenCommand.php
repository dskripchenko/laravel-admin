<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console;

use Dskripchenko\LaravelAdmin\Console\Support\AdminPluginUpdater;
use Dskripchenko\LaravelAdmin\Console\Support\PluginRegistrationReport;
use Dskripchenko\LaravelAdmin\Console\Support\ResourceWriter;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;

/**
 * `php artisan admin:make-screen`
 *
 * The wizard behind a custom screen. It is started without arguments.
 *
 *   1. The label and the slug
 *   2. The description
 *   3. The state's fields, through a multiselect: text, email, textarea,
 *      select and so on
 *   4. The permission
 *   5. The commands of the command bar: Submit, Cancel, Reload and the rest
 *   6. Optionally, a menu entry under a parent
 */
final class MakeScreenCommand extends Command
{
    protected $signature = 'admin:make-screen
                            {--force : Overwrite an existing screen}';

    protected $description = 'Create a custom screen, a non-CRUD page (interactive)';

    public function handle(ResourceWriter $writer, AdminPluginUpdater $updater): int
    {
        info('New custom screen');

        $label = text(label: 'Title (e.g. Contact the team)', required: true);
        $slug = text(
            label: 'Slug',
            default: $writer->slugFor($label),
            required: true,
        );
        $description = text(label: 'Description (optional)', default: '');

        $fieldTypes = multiselect(
            label: 'Which form fields?',
            options: [
                'text' => 'Input (text)',
                'email' => 'Input type=email',
                'textarea' => 'Textarea',
                'number' => 'Number',
                'select' => 'Select',
                'date' => 'DatePicker',
                'switch' => 'Switcher (boolean)',
            ],
            default: ['text', 'textarea'],
        );
        $fieldNames = [];
        foreach ($fieldTypes as $type) {
            $name = text(
                label: "Name of the '{$type}' field",
                default: $type === 'email' ? 'email' : $type,
                required: true,
            );
            $fieldNames[] = ['name' => $name, 'type' => $type];
        }

        $hasSubmit = confirm(label: "Add a 'Send' button calling a send() method?", default: true);

        $permission = text(
            label: 'Permission (empty: any signed-in administrator)',
            default: '',
        );

        $addMenu = confirm(label: 'Add to the menu?', default: true);
        $menuParent = '';
        if ($addMenu) {
            $menuParent = text(label: 'Parent menu key (empty for a top-level item)', default: 'tools');
        }

        // === Generate ===
        $namespace = 'App\\Admin\\Screens';
        $className = $writer->classNameFor($label, 'Screen');

        $stateInit = $this->stateInit($fieldNames);
        $layoutFields = $this->layoutFields($fieldNames);
        $commandBar = $hasSubmit
            ? "            Button::make('Send')->method('send')->primary(),"
            : '            // Button::make(...)->method(...),';
        $commandMethods = $hasSubmit
            ? $this->sendMethod($fieldNames)
            : '';

        $vars = [
            'namespace' => $namespace,
            'class' => $className,
            'slug' => $this->escape($slug),
            'label' => $this->escape($label),
            'description' => $description !== '' ? "'".$this->escape($description)."'" : 'null',
            'permission' => $permission !== '' ? "'".$this->escape($permission)."'" : 'null',
            'stateInit' => $stateInit,
            'layoutFields' => $layoutFields,
            'commandBar' => $commandBar,
            'commandMethods' => $commandMethods,
            'date' => date('Y-m-d'),
        ];

        $stub = $writer->stubPath('screen.stub');
        $target = $writer->classPath($namespace, $className);
        $created = $writer->fromStub($stub, $target, $vars, force: (bool) $this->option('force'));
        if (! $created) {
            $this->error("File already exists: {$target}. Use --force to overwrite it.");

            return self::FAILURE;
        }
        info("Created: {$target}");

        $screenFqcn = $namespace.'\\'.$className;
        $reg = $updater->registerScreen($screenFqcn);
        info("Plugin: {$reg['path']} ({$reg['action']})");

        if ($addMenu) {
            $updater->ensureImport($reg['path'], 'Dskripchenko\\LaravelAdmin\\Menu\\MenuNode');
            $updater->addMenuNode('screen', $slug, $menuParent ?: null);
            info('Menu item added');
        }

        $plugin = $updater->ensurePluginRegistered($reg['path']);
        PluginRegistrationReport::print($this, $plugin);

        info('Done.');
        note('Open /'.trim((string) config('admin.path', 'admin'), '/')."/screens/{$slug}");

        return self::SUCCESS;
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    /** @param list<array{name: string, type: string}> $fields */
    private function stateInit(array $fields): string
    {
        $lines = [];
        foreach ($fields as $f) {
            $default = match ($f['type']) {
                'number' => '0',
                'switch' => 'false',
                default => "''",
            };
            $lines[] = "            '{$f['name']}' => {$default},";
        }

        return implode("\n", $lines);
    }

    /** @param list<array{name: string, type: string}> $fields */
    private function layoutFields(array $fields): string
    {
        $lines = [];
        foreach ($fields as $f) {
            $name = $f['name'];
            $title = ucfirst(str_replace('_', ' ', $name));
            $code = match ($f['type']) {
                'email' => "Input::make('{$name}')->type('email')->required()->title('{$title}')",
                'textarea' => "Textarea::make('{$name}')->rows(4)->required()->title('{$title}')",
                'number' => "\\Dskripchenko\\LaravelAdmin\\Field\\Number::make('{$name}')->title('{$title}')",
                'select' => "\\Dskripchenko\\LaravelAdmin\\Field\\Select::make('{$name}')->options([])->required()->title('{$title}')",
                'date' => "\\Dskripchenko\\LaravelAdmin\\Field\\DatePicker::make('{$name}')->title('{$title}')",
                'switch' => "\\Dskripchenko\\LaravelAdmin\\Field\\Switcher::make('{$name}')->title('{$title}')",
                default => "Input::make('{$name}')->required()->title('{$title}')",
            };
            $lines[] = '                '.$code.',';
        }

        return implode("\n", $lines);
    }

    /** @param list<array{name: string, type: string}> $fields */
    private function sendMethod(array $fields): string
    {
        $rules = [];
        $reset = [];
        foreach ($fields as $f) {
            $rule = match ($f['type']) {
                'email' => 'required|email',
                'number' => 'required|numeric',
                'switch' => 'boolean',
                default => 'required|string|min:2',
            };
            $rules[] = "            '{$f['name']}' => '{$rule}',";
            $default = match ($f['type']) {
                'number' => '0',
                'switch' => 'false',
                default => "''",
            };
            $reset[] = "            '{$f['name']}' => {$default},";
        }

        $rulesBlock = implode("\n", $rules);
        $resetBlock = implode("\n", $reset);

        return <<<PHP

    /**
     * @param  array<string, mixed>  \$state
     * @return array<string, mixed>
     */
    public function send(array \$state): array
    {
        validator(\$state, [
{$rulesBlock}
        ])->validate();

        // TODO: the actual sending and saving

        return [
            'message' => 'Sent.',
            'state' => [
{$resetBlock}
            ],
        ];
    }
PHP;
    }
}
