<?php

namespace App\Support;

use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SpreadsheetExporter
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, int|string>>  $rows
     * @param  array<int, float>  $widths
     */
    public function download(
        string $fileName,
        string $format,
        array $headers,
        iterable $rows,
        array $widths = [],
    ): StreamedResponse {
        $path = tempnam(sys_get_temp_dir(), 'invoicing-export-');

        if ($path === false) {
            abort(500, 'Le fichier d’export n’a pas pu être créé.');
        }

        try {
            $writer = SimpleExcelWriter::create(
                file: $path,
                type: $format,
                configureWriter: function (object $writer) use ($widths): void {
                    if ($writer instanceof XlsxWriter) {
                        foreach ($widths as $columnIndex => $width) {
                            $writer->getOptions()->setColumnWidth($width, max(1, $columnIndex));
                        }
                    }
                },
                delimiter: $format === 'csv' ? ',' : null,
                shouldAddBom: $format === 'csv',
            );

            $writer->noHeaderRow();
            $writer->addHeader($headers);

            foreach ($rows as $row) {
                $writer->addRow(array_map($this->sanitizeCell(...), $row));
            }

            $writer->close();
        } catch (Throwable $exception) {
            @unlink($path);

            throw $exception;
        }

        return response()->streamDownload(function () use ($path): void {
            try {
                readfile($path);
            } finally {
                @unlink($path);
            }
        }, $fileName, ['Content-Type' => $this->contentType($format)]);
    }

    private function sanitizeCell(int|string $value): int|string
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $firstCharacter = $value[0];

        if (! in_array($firstCharacter, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return $value;
        }

        if (in_array($firstCharacter, ['+', '-'], true) && is_numeric($value)) {
            return $value;
        }

        return "'".$value;
    }

    private function contentType(string $format): string
    {
        return $format === 'csv'
            ? 'text/csv; charset=UTF-8'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }
}
