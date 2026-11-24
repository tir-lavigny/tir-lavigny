<?php

namespace App\Console\Commands;

use App\Domains\Invoicing\Enums\FinancialDocumentStatus;
use App\Domains\Invoicing\Models\FinancialDocument;
use App\Domains\Invoicing\Services\FinancialDocumentArchiver;
use App\Domains\Invoicing\Services\FinancialDocumentGenerator;
use Illuminate\Console\Command;
use Throwable;

class InvoicingRetryDocuments extends Command
{
    protected $signature = 'invoicing:retry-documents {--dry-run : Liste les documents sans les régénérer}';

    protected $description = 'Regénère et archive les PDF de facturation en attente ou en échec';

    public function handle(FinancialDocumentArchiver $archiver, FinancialDocumentGenerator $generator): int
    {
        $documents = FinancialDocument::query()
            ->whereIn('status', [FinancialDocumentStatus::Pending->value, FinancialDocumentStatus::Failed->value])
            ->orderBy('id')
            ->get();

        if ($documents->isEmpty()) {
            $this->info('Aucun document à archiver.');

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($documents as $document) {
            if ($this->option('dry-run')) {
                $this->line("#{$document->id} {$document->path} [{$document->status->value}]");

                continue;
            }

            try {
                $document = $archiver->retry($document, $generator);
            } catch (Throwable $exception) {
                report($exception);
                $failures++;
                $this->error("#{$document->id} {$document->path}: {$exception->getMessage()}");

                continue;
            }

            if ($document->status === FinancialDocumentStatus::Stored) {
                $this->info("#{$document->id} archivé.");
            } else {
                $failures++;
                $this->error("#{$document->id} {$document->path}: {$document->error}");
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
