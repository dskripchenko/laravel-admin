<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Import;

use Dskripchenko\LaravelAdmin\Field\FileUpload;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Layout\Step;
use Dskripchenko\LaravelAdmin\Layout\View;
use Dskripchenko\LaravelAdmin\Layout\Wizard;

/**
 * The ready-made layout of the four-step import wizard.
 *
 * The steps:
 *   1. Upload — a FileUpload accepting csv, tsv and xlsx.
 *   2. Mapping — the suggested mapping, corrected by hand through the
 *      `admin.import.mapping` view, named after the SPA's implementation.
 *   3. Preview — the sample rows and a summary.
 *   4. Run — the import starts, and the progress is polled from
 *      /api/admin/import/status.
 *
 * The resource slug doubles as the persistKey, so that the wizard keeps its
 * progress.
 */
final class ImportWizardLayout
{
    public static function for(string $resourceSlug): Wizard
    {
        return Layout::wizard([
            Step::make('Загрузка файла', [
                FileUpload::make('file')
                    ->required()
                    ->accept(['.csv', '.tsv', '.xlsx'])
                    ->maxSize((int) config('admin.uploads.max_kilobytes', 51200)),
            ])
                ->description('Выберите CSV, TSV или XLSX файл для импорта')
                ->icon('upload'),

            Step::make('Сопоставление колонок', [
                View::make('admin.import.mapping', ['resource' => $resourceSlug]),
            ])
                ->description('Сопоставьте колонки файла с полями ресурса')
                ->icon('columns'),

            Step::make('Предпросмотр', [
                View::make('admin.import.preview', ['resource' => $resourceSlug]),
            ])
                ->description('Проверьте первые строки перед импортом')
                ->icon('eye'),

            Step::make('Импорт', [
                View::make('admin.import.run', ['resource' => $resourceSlug]),
            ])
                ->description('Запуск и отслеживание прогресса')
                ->icon('play'),
        ])
            ->submit('runImport')
            ->persistKey('import-'.$resourceSlug);
    }
}
