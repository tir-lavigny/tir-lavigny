<?php

namespace App\Support;

use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            $writer->addRow($row);
        }

        $writer->close();

        return response()->streamDownload(function () use ($path): void {
            readfile($path);
            @unlink($path);
        }, $fileName, ['Content-Type' => $this->contentType($format)]);
    }

    private function contentType(string $format): string
    {
        return $format === 'csv'
            ? 'text/csv; charset=UTF-8'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }
}
