<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Export;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * XLSX without a library: one sheet of inline strings and numbers written
 * row by row into a temporary file, zipped with PHP's own zip extension.
 * Registered when openspout is not installed (XlsxExporter wins when it is).
 * Enough for list exports and reports; no formulas, merges or images.
 */
final class NativeXlsxExporter implements Exporter
{
    public function format(): string
    {
        return 'xlsx';
    }

    public function mimeType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function extension(): string
    {
        return 'xlsx';
    }

    public function export(iterable $rows, array $columns, string $filenameWithoutExt): StreamedResponse
    {
        $filename = $filenameWithoutExt.'.'.$this->extension();

        return new StreamedResponse(
            function () use ($rows, $columns): void {
                $path = self::write($rows, $columns);
                try {
                    readfile($path);
                } finally {
                    @unlink($path);
                }
            },
            200,
            [
                'Content-Type' => $this->mimeType(),
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ],
        );
    }

    /**
     * Writes the workbook to a temporary file and returns its path.
     *
     * @param  iterable<array<string, mixed>>  $rows
     * @param  array<string, string>  $columns  key => header
     */
    public static function write(iterable $rows, array $columns): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('XLSX export needs the zip extension of PHP.');
        }
        $sheet = (string) tempnam(sys_get_temp_dir(), 'xlsx-sheet');
        $out = fopen($sheet, 'wb');
        if ($out === false) {
            throw new RuntimeException('Cannot write a temporary file for the XLSX export.');
        }
        fwrite($out, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');
        fwrite($out, self::row(array_values($columns), header: true));
        foreach ($rows as $row) {
            $values = [];
            foreach (array_keys($columns) as $key) {
                $values[] = data_get($row, $key);
            }
            fwrite($out, self::row($values));
        }
        fwrite($out, '</sheetData></worksheet>');
        fclose($out);

        $path = (string) tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($sheet);
            throw new RuntimeException('Cannot create the XLSX archive.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        // Style 1: the bold header.
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            .'</styleSheet>');
        $zip->addFile($sheet, 'xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($sheet);

        return $path;
    }

    /** @param list<mixed> $values */
    private static function row(array $values, bool $header = false): string
    {
        $cells = '';
        foreach ($values as $value) {
            $style = $header ? ' s="1"' : '';
            if (is_int($value) || is_float($value)) {
                $cells .= '<c'.$style.'><v>'.$value.'</v></c>';

                continue;
            }
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (! is_scalar($value) && $value !== null) {
                $value = (string) json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            // XML 1.0 has no place for most control characters.
            $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $value);
            $cells .= '<c t="inlineStr"'.$style.'><is><t xml:space="preserve">'.htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
        }

        return '<row>'.$cells.'</row>';
    }
}
