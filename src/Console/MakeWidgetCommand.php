<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console;

use Dskripchenko\LaravelAdmin\Console\Support\ResourceWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * `php artisan admin:make-widget`
 *
 * Wizard для создания custom Widget (плитка для DashboardScreen).
 */
final class MakeWidgetCommand extends Command
{
    protected $signature = 'admin:make-widget
                            {--force : Перезаписать существующий Widget}';

    protected $description = 'Мастер создания custom Widget';

    public function handle(ResourceWriter $writer): int
    {
        info('🧙 Wizard: новый Widget');

        $name = text(label: 'Имя класса (например: WeatherWidget)', required: true);
        if (! str_ends_with($name, 'Widget')) {
            $name .= 'Widget';
        }

        $type = select(
            label: 'Тип widget (frontend type)',
            options: [
                'stats' => 'Stats (KPI карточка)',
                'chart' => 'Chart (bar/line/donut)',
                'recent_list' => 'Recent list',
                'markdown' => 'Markdown',
                'iframe' => 'Iframe',
                'table' => 'Table',
                'heatmap' => 'Heatmap',
                'gauge' => 'Gauge',
                'custom' => 'Custom (host регистрирует Vue-компонент)',
            ],
            default: 'stats',
        );

        $baseClass = match ($type) {
            'stats' => 'StatsOverviewWidget',
            'chart' => 'ChartWidget',
            'recent_list' => 'RecentListWidget',
            'markdown' => 'MarkdownWidget',
            'iframe' => 'IframeWidget',
            'table' => 'TableWidget',
            'heatmap' => 'HeatmapWidget',
            'gauge' => 'GaugeWidget',
            default => 'Widget',
        };

        $namespace = 'App\\Admin\\Widgets';
        $slug = Str::kebab(Str::beforeLast($name, 'Widget'));

        $dataReturn = $this->dataReturn($type);

        $vars = [
            'namespace' => $namespace,
            'class' => $name,
            'baseClass' => $baseClass,
            'slug' => $slug,
            'widgetType' => $type === 'custom' ? $slug : $type,
            'dataReturn' => $dataReturn,
            'date' => date('Y-m-d'),
        ];

        $stub = $writer->stubPath('widget.stub');
        $target = $writer->classPath($namespace, $name);
        $created = $writer->fromStub($stub, $target, $vars, force: (bool) $this->option('force'));
        if (! $created) {
            $this->error("⚠ Файл уже существует: {$target}. Используйте --force.");

            return self::FAILURE;
        }
        info("✓ Создан: {$target}");
        info('   Используйте в DashboardScreen::widgets():');
        $this->line('     '.$name.'::make()->title(\'…\')->size(6),');

        return self::SUCCESS;
    }

    private function dataReturn(string $type): string
    {
        return match ($type) {
            'stats' => "            'stats' => [\n                ['label' => 'TOTAL', 'value' => 0],\n            ],",
            'chart' => "            'chartType' => 'bar',\n            'labels' => ['Mon', 'Tue', 'Wed'],\n            'datasets' => [['label' => 'Series', 'data' => [10, 20, 15]]],",
            'recent_list' => "            'rows' => [],\n            'columns' => [['column' => 'id', 'label' => 'ID']],\n            'linkTo' => null,",
            'gauge' => "            'value' => 50,\n            'min' => 0,\n            'max' => 100,\n            'thresholds' => [],",
            'markdown' => "            'content' => '# Hello',",
            'iframe' => "            'url' => 'about:blank',",
            'heatmap' => "            'rows' => ['Mon', 'Tue'],\n            'cols' => ['00h', '01h'],\n            'matrix' => [[0, 0], [0, 0]],",
            'table' => "            'rows' => [],\n            'columns' => [],",
            default => '            // your data here',
        };
    }
}
