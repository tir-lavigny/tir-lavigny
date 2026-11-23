<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\ExportMembersRequest;
use App\Services\MemberExportService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Spatie\SimpleExcel\SimpleExcelWriter;

class MemberExportController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly MemberExportService $memberExportService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage members'),
        ];
    }

    /**
     * Stream the member export to the browser. SimpleExcelWriter::toBrowser() ends
     * the request itself, so this action never returns a response.
     */
    public function export(ExportMembersRequest $request): void
    {
        $validated = $request->validated();
        $format = ($validated['format'] ?? null) === 'csv' ? 'csv' : 'xlsx';

        $columns = $this->memberExportService->resolveColumns(
            array_values(array_filter((array) ($validated['columns'] ?? []), is_string(...))),
            Auth::user()?->can(MemberExportService::SENSITIVE_PERMISSION) ?? false,
        );
        $extraColumns = $this->normalizeExtraColumns((array) ($validated['extra_columns'] ?? []));

        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? null;
        $sort = $validated['sort'] ?? null;

        $query = $this->memberExportService->memberQuery([
            'search' => is_string($search) ? $search : null,
            'status' => is_string($status) ? $status : null,
            'archived' => $request->boolean('archived'),
            'sort' => is_string($sort) ? $sort : null,
        ]);

        // Excel only: best-fit widths for every column, the way double-clicking a
        // column border does. CSV carries no widths.
        $widths = $format === 'xlsx'
            ? $this->memberExportService->columnWidths($query, $columns, $extraColumns)
            : [];

        $fileName = 'membres-'.now()->format('Y-m-d').'.'.$format;

        $writer = SimpleExcelWriter::streamDownload(
            downloadName: $fileName,
            type: $format,
            writerCallback: function (WriterInterface $writer, string $downloadName) use ($widths): void {
                if ($writer instanceof XlsxWriter) {
                    foreach ($widths as $columnIndex => $width) {
                        // openSpout counts columns from 1; the service already returns 1-based keys.
                        $writer->getOptions()->setColumnWidth($width, max(1, $columnIndex));
                    }
                }

                $writer->openToBrowser($downloadName);
            },
            delimiter: $format === 'csv' ? ',' : null,
            shouldAddBom: $format === 'csv',
        );

        $writer->noHeaderRow();
        $writer->addHeader($this->memberExportService->headers($columns, $extraColumns));

        foreach ($this->memberExportService->rows($query, $columns, count($extraColumns)) as $row) {
            $writer->addRow($row);
        }

        $writer->toBrowser();
    }

    /**
     * @param  array<int|string, mixed>  $extraColumns
     * @return array<int, array{header: string, width: float|null}>
     */
    private function normalizeExtraColumns(array $extraColumns): array
    {
        $normalized = [];

        foreach ($extraColumns as $extraColumn) {
            if (! is_array($extraColumn)) {
                continue;
            }

            $header = $extraColumn['header'] ?? '';

            if (! is_string($header) || trim($header) === '') {
                continue;
            }

            $width = $extraColumn['width'] ?? null;

            $normalized[] = [
                'header' => trim($header),
                'width' => is_numeric($width) ? (float) $width : null,
            ];
        }

        return $normalized;
    }
}
