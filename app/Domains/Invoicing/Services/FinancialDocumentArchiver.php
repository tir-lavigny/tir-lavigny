<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Enums\FinancialDocumentStatus;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteReversal;
use App\Domains\Invoicing\Models\FinancialDocument;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FinancialDocumentArchiver
{
    public function queue(Model $documentable, string $pdf, string $directory, string $filename): FinancialDocument
    {
        $issueDate = $documentable->getAttribute('issue_date');
        $year = $issueDate instanceof \DateTimeInterface ? $issueDate->format('Y') : now()->format('Y');
        $path = trim($directory, '/').'/'.$year.'/'.$filename;

        $document = FinancialDocument::query()->updateOrCreate(
            [
                'documentable_type' => $documentable->getMorphClass(),
                'documentable_id' => $documentable->getKey(),
            ],
            [
                'status' => FinancialDocumentStatus::Pending,
                'disk' => config('invoicing.documents.disk', 'local'),
                'path' => $path,
                'sha256' => hash('sha256', $pdf),
                'error' => null,
                'generated_at' => now(),
                'archived_at' => null,
            ],
        );

        DB::afterCommit(fn () => $this->store($document->id, $pdf));

        return $document;
    }

    public function store(int $documentId, string $pdf): void
    {
        $document = FinancialDocument::query()->find($documentId);

        if ($document === null) {
            return;
        }

        try {
            if (! Storage::disk($document->disk)->put($document->path, $pdf)) {
                throw new RuntimeException('Le fichier PDF n’a pas pu être écrit.');
            }

            $document->update([
                'status' => FinancialDocumentStatus::Stored,
                'error' => null,
                'archived_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $document->update([
                'status' => FinancialDocumentStatus::Failed,
                'error' => Str::limit($exception->getMessage(), 1000),
            ]);

            Log::error('Impossible d’archiver un document de facturation.', [
                'financial_document_id' => $document->id,
                'path' => $document->path,
                'exception' => $exception,
            ]);
        }
    }

    public function retry(FinancialDocument $document, FinancialDocumentGenerator $generator): FinancialDocument
    {
        $documentable = $document->documentable()->firstOrFail();

        $pdf = match (true) {
            $documentable instanceof Invoice => $generator->invoice($documentable->loadMissing('items')),
            $documentable instanceof CreditNote => $generator->creditNote($documentable->loadMissing('items', 'invoice')),
            $documentable instanceof CreditNoteReversal => $generator->creditNoteReversal($documentable->loadMissing('items', 'creditNote')),
            default => throw new RuntimeException('Type de document non pris en charge.'),
        };

        $document->update([
            'status' => FinancialDocumentStatus::Pending,
            'sha256' => hash('sha256', $pdf),
            'error' => null,
            'generated_at' => now(),
        ]);

        $this->store($document->id, $pdf);

        return $document->refresh();
    }
}
