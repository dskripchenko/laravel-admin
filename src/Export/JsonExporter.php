<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Export;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The JSON Lines / JSON array exporter, with no dependencies.
 *
 * With config('admin.exports.json.lines', false) set to true it renders NDJSON
 * — one record per line, handy for feeding Splunk or jq — and otherwise a
 * single JSON array.
 *
 * It works off a generator, which keeps the memory use modest on large data
 * sets.
 */
final class JsonExporter implements Exporter
{
    public function format(): string
    {
        return 'json';
    }

    public function mimeType(): string
    {
        return 'application/json; charset=UTF-8';
    }

    public function extension(): string
    {
        return 'json';
    }

    public function export(iterable $rows, array $columns, string $filenameWithoutExt): StreamedResponse
    {
        $lines = (bool) config('admin.exports.json.lines', false);
        $filename = $filenameWithoutExt.'.'.($lines ? 'jsonl' : 'json');
        $colKeys = array_keys($columns);

        return new StreamedResponse(
            function () use ($rows, $colKeys, $lines): void {
                $handle = fopen('php://output', 'w');
                if ($handle === false) {
                    return;
                }

                if (! $lines) {
                    fwrite($handle, '[');
                }

                $first = true;
                foreach ($rows as $row) {
                    $obj = [];
                    foreach ($colKeys as $col) {
                        $obj[$col] = data_get($row, $col);
                    }
                    $encoded = json_encode($obj, JSON_UNESCAPED_UNICODE);
                    if ($encoded === false) {
                        continue;
                    }

                    if ($lines) {
                        fwrite($handle, $encoded."\n");
                    } else {
                        if (! $first) {
                            fwrite($handle, ',');
                        }
                        fwrite($handle, $encoded);
                        $first = false;
                    }
                }

                if (! $lines) {
                    fwrite($handle, ']');
                }

                fclose($handle);
            },
            200,
            [
                'Content-Type' => $this->mimeType(),
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ],
        );
    }
}
