# Import / Export

## Export — встроено

Любой Resource автоматически имеет actions `exportCsv` (всегда) и `export`
(универсальный с параметром `format`). XLSX/PDF появляются если установлены
соответствующие пакеты:

```bash
composer require openspout/openspout    # XLSX
composer require mpdf/mpdf               # PDF (default)
# либо
composer require dompdf/dompdf           # PDF (fallback)
```

URL'ы:

- `GET /api/admin/articles/exportCsv?columns[]=title&columns[]=created_at`
- `GET /api/admin/articles/export?format=xlsx&filters[is_published]=1`
- `GET /api/admin/articles/export?format=pdf`

## Custom CSV конфигурация

```php
// config/admin.php
'exports' => [
    'csv' => [
        'delimiter' => ';',     // для русского Excel
        'enclosure' => '"',
        'bom' => true,
    ],
    'pdf' => [
        'driver' => 'mpdf',     // или 'dompdf'
        'options' => [
            'mpdf' => ['mode' => 'utf-8', 'format' => 'A4'],
        ],
    ],
],
```

## Custom exporter

Реализовать `Dskripchenko\LaravelAdmin\Export\Exporter` и зарегистрировать
в `ExporterRegistry`:

```php
// AppServiceProvider::boot()
$this->app->resolving(ExporterRegistry::class, function (ExporterRegistry $reg) {
    $reg->add(new \App\Exporters\JsonExporter);
});
```

Теперь доступен `?format=json`.

## Import — 4-step Wizard

Включить на Resource'е:

```php
public function importable(): bool
{
    return true;
}
```

Использовать готовую разметку wizard'а на custom-screen:

```php
use Dskripchenko\LaravelAdmin\Import\ImportWizardLayout;

class ArticleImportScreen extends Screen
{
    public function name(): string { return 'Импорт статей'; }
    public function query(...$params): array { return []; }
    public function layout(): array
    {
        return [ImportWizardLayout::for('articles')];
    }
}
```

Wizard:
1. **Upload** — `POST /api/admin/import/upload` с файлом CSV/TSV/XLSX.
2. **Mapping** — `POST /api/admin/import/preview` возвращает `auto_mapping` (auto-match по name/title/snake_case). Пользователь корректирует.
3. **Preview** — sample rows визуализируются с применённым mapping'ом.
4. **Run** — `POST /api/admin/import/start` создаёт `ImportProcess`, валидирует каждую строку через Resource'овые rules, копит errors[].

Status: `GET /api/admin/import/status?id=N` возвращает progress (processed/created/error counts).

## Custom валидация при импорте

Resource::validationRules('create') используется по дефолту — добавьте rules
к Field'ам:

```php
public function fields(): array
{
    return [
        Input::make('email')->type('email')->required(),    // implicit 'required|email'
        Input::make('slug')->rules(['unique:articles,slug']), // explicit
    ];
}
```

Невалидные строки пропускаются с записью в `import_processes.errors`,
валидные — создаются.
