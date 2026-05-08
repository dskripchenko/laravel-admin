<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console;

use Dskripchenko\LaravelAdmin\Console\Support\AdminPluginUpdater;
use Dskripchenko\LaravelAdmin\Console\Support\ResourceWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;

/**
 * `php artisan admin:make-screen`
 *
 * Wizard для кастомного Screen'а. Запускается без аргументов.
 *
 *   1. Имя (label) и slug
 *   2. Описание
 *   3. Поля state'а (через multiselect: text/email/textarea/select/...)
 *   4. Permission
 *   5. Команды для commandBar (Submit/Cancel/Reload/...)
 *   6. (опц.) добавить в меню под parent
 */
final class MakeScreenCommand extends Command
{
    protected $signature = 'admin:make-screen
                            {--force : Перезаписать существующий Screen}';

    protected $description = 'Мастер создания custom Screen (non-CRUD страница)';

    public function handle(ResourceWriter $writer, AdminPluginUpdater $updater): int
    {
        info('🧙 Wizard: новый Custom Screen');

        $label = text(label: 'Название (например: Связаться с командой)', required: true);
        $slug = text(
            label: 'Slug',
            default: Str::kebab(Str::singular($label)),
            required: true,
        );
        $description = text(label: 'Описание (опц.)', default: '');

        $fieldTypes = multiselect(
            label: 'Какие поля state-формы?',
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
                label: "Имя поля типа '{$type}'",
                default: $type === 'email' ? 'email' : $type,
                required: true,
            );
            $fieldNames[] = ['name' => $name, 'type' => $type];
        }

        $hasSubmit = confirm(label: "Добавить кнопку 'Отправить' с command-методом send()?", default: true);

        $permission = text(
            label: 'Permission (пусто — только аутентификация)',
            default: '',
        );

        $addMenu = confirm(label: 'Добавить в меню?', default: true);
        $menuParent = '';
        if ($addMenu) {
            $menuParent = text(label: 'Parent в меню (пусто — корневой)', default: 'tools');
        }

        // === Generate ===
        $namespace = 'App\\Admin\\Screens';
        $className = $writer->classNameFor($label, 'Screen');

        $stateInit = $this->stateInit($fieldNames);
        $layoutFields = $this->layoutFields($fieldNames);
        $commandBar = $hasSubmit
            ? "            Button::make('Отправить')->method('send')->primary(),"
            : '            // Button::make(...)->method(...),';
        $commandMethods = $hasSubmit
            ? $this->sendMethod($fieldNames)
            : '';

        $vars = [
            'namespace' => $namespace,
            'class' => $className,
            'label' => $label,
            'description' => $description !== '' ? "'{$description}'" : 'null',
            'permission' => $permission !== '' ? "'{$permission}'" : 'null',
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
            $this->error("⚠ Файл уже существует: {$target}. Используйте --force.");

            return self::FAILURE;
        }
        info("✓ Создан: {$target}");

        $screenFqcn = $namespace.'\\'.$className;
        $reg = $updater->registerScreen($screenFqcn);
        info("✓ Plugin: {$reg['path']} ({$reg['action']})");

        if ($addMenu) {
            $updater->ensureImport($reg['path'], 'Dskripchenko\\LaravelAdmin\\Menu\\MenuNode');
            $updater->addMenuNode('screen', $slug, $menuParent ?: null);
            info('✓ Menu: добавлен пункт');
        }

        info('✅ Готово!');
        note("Откройте /admin/screens/{$slug} (после composer dump-autoload).");

        return self::SUCCESS;
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

        // TODO: реальная логика отправки/сохранения

        return [
            'message' => 'Отправлено',
            'state' => [
{$resetBlock}
            ],
            'alerts' => [['type' => 'success', 'message' => 'OK']],
        ];
    }
PHP;
    }
}
