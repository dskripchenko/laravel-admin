<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Import;

use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Запускает импорт-процесс синхронно.
 *
 * Для async-варианта зарегистрировать через AllowlistRegistrar и вызывать
 * через DelayedProcessController.run.
 */
final class ImportRunner
{
    public function __construct(private readonly ResourceRegistry $resources) {}

    public function run(int $importProcessId): ImportProcess
    {
        /** @var ImportProcess|null $process */
        $process = ImportProcess::query()->find($importProcessId);
        if ($process === null) {
            throw new \RuntimeException("ImportProcess #{$importProcessId} not found");
        }

        $process->update([
            'status' => ImportProcess::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        try {
            $this->performImport($process);
            $process->update([
                'status' => ImportProcess::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $errors = (array) ($process->errors ?? []);
            $errors[] = ['row' => 0, 'error' => $e->getMessage()];
            $process->update([
                'status' => ImportProcess::STATUS_FAILED,
                'errors' => $errors,
                'completed_at' => now(),
            ]);
        }

        return $process->refresh();
    }

    private function performImport(ImportProcess $process): void
    {
        $resource = $this->resources->resolve($process->resource_slug);
        if ($resource === null) {
            throw new \RuntimeException("Resource `{$process->resource_slug}` is not registered");
        }

        $modelClass = $resource::$model;
        $rules = $resource->validationRules('create');
        $mapping = (array) $process->mapping;

        $diskName = (string) config('admin.imports.disk', 'local');
        $localPath = Storage::disk($diskName)->path($process->source_path);

        $extension = strtolower((string) pathinfo($process->source_path, PATHINFO_EXTENSION));
        $rows = match ($extension) {
            'csv', 'tsv', 'txt' => $this->iterateCsv($localPath, $extension),
            'xlsx' => $this->iterateXlsx($localPath),
            default => throw new \RuntimeException("Unsupported file extension `{$extension}`"),
        };

        $rowIndex = 1;
        foreach ($rows as $rawRow) {
            $rowIndex++;
            $payload = ColumnMapper::applyMapping($rawRow, $mapping);

            try {
                $validator = validator($payload, $rules);
                if ($validator->fails()) {
                    $this->bumpError($process, $rowIndex, $validator->errors()->first());

                    continue;
                }

                DB::transaction(function () use ($modelClass, $payload, $process): void {
                    /** @var \Illuminate\Database\Eloquent\Model $model */
                    $model = new $modelClass;
                    $model->forceFill($payload);
                    $model->save();
                    $process->increment('created_count');
                });
            } catch (Throwable $e) {
                $this->bumpError($process, $rowIndex, $e->getMessage());
            } finally {
                $process->increment('processed_count');
            }
        }
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    private function iterateCsv(string $path, string $extension): iterable
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return;
        }
        $first = fread($handle, 3);
        if ($first !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $delimiter = $extension === 'tsv' ? "\t" : self::sniffDelimiter($path);
        $headers = fgetcsv($handle, 0, $delimiter, '"', '\\');
        if (! is_array($headers)) {
            fclose($handle);

            return;
        }

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $combined = [];
            foreach ($headers as $i => $h) {
                $combined[(string) $h] = $row[$i] ?? null;
            }
            yield $combined;
        }
        fclose($handle);
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    private function iterateXlsx(string $path): iterable
    {
        $reader = new \OpenSpout\Reader\XLSX\Reader;
        $reader->open($path);
        $headers = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                $values = array_map(
                    static fn ($cell): mixed => $cell->getValue(),
                    $row->getCells(),
                );
                if ($rowIndex === 1) {
                    $headers = array_map(static fn ($v): string => (string) $v, $values);

                    continue;
                }
                $combined = [];
                foreach ($headers as $i => $h) {
                    $combined[$h] = $values[$i] ?? null;
                }
                yield $combined;
            }
            break;
        }
        $reader->close();
    }

    private function bumpError(ImportProcess $process, int $row, string $error): void
    {
        $errors = (array) ($process->errors ?? []);
        $errors[] = ['row' => $row, 'error' => $error];
        $process->update(['errors' => $errors]);
        $process->increment('error_count');
    }

    private static function sniffDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return ',';
        }
        $first = fread($handle, 3);
        if ($first !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        $line = fgets($handle);
        fclose($handle);
        if (! is_string($line)) {
            return ',';
        }

        $best = ',';
        $bestCount = 0;
        foreach ([',', ';', "\t", '|'] as $cand) {
            $count = substr_count($line, $cand);
            if ($count > $bestCount) {
                $best = $cand;
                $bestCount = $count;
            }
        }

        return $best;
    }
}
