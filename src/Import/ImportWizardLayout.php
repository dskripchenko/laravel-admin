<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Import;

use Dskripchenko\LaravelAdmin\Field\FileUpload;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Layout\Step;
use Dskripchenko\LaravelAdmin\Layout\View;
use Dskripchenko\LaravelAdmin\Layout\Wizard;

/**
 * Готовая разметка 4-step Import Wizard.
 *
 * Шаги:
 *   1. Upload — FileUpload + accept csv/tsv/xlsx.
 *   2. Mapping — auto-mapping предложение, ручная корректировка через
 *      `admin.import.mapping` view (имя соответствует реализации SPA).
 *   3. Preview — отображение sample rows + summary.
 *   4. Run — старт импорта, прогресс через polling /api/admin/import/status.
 *
 * Resource slug передаётся как persistKey'ом, чтобы wizard сохранял прогресс.
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
                    ->maxSize(51200),
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
