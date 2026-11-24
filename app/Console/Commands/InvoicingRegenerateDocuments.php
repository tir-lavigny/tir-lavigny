<?php

namespace App\Console\Commands;

use App\Domains\Invoicing\Enums\FinancialDocumentStatus;
use App\Domains\Invoicing\Models\FinancialDocument;
use App\Domains\Invoicing\Services\FinancialDocumentArchiver;
use App\Domains\Invoicing\Services\FinancialDocumentGenerator;
use Illuminate\Console\Command;
use Throwable;

class InvoicingRegenerateDocuments extends Command
{
    protected $signature = 'invoicing:regenerate-documents
        {--dry-run : Liste les documents sans les régénérer}
        {--force : Confirme le remplacement des PDF archivés}';

    protected $description = 'Regénère tous les PDF archivés depuis les données figées des documents';

    public function handle(FinancialDocumentArchiver $archiver, FinancialDocumentGenerator $generator): int
    {
        $documents = FinancialDocument::query()->orderBy('id')->get();

        if ($documents->isEmpty()) {
            $this->info('Aucun document à régénérer.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach ($documents as $document) {
                $this->line("#{$document->id} {$document->path} [{$document->status->value}]");
            }

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->error('Cette commande remplace les PDF archivés.');
            $this->line('Utilisez --force après avoir vérifié que vous êtes sur un environnement de test.');

            return self::FAILURE;
        }

        $failures = 0;

        foreach ($documents as $document) {
            try {
                $document = $archiver->retry($document, $generator);
            } catch (Throwable $exception) {
                report($exception);
                $failures++;
                $this->error("#{$document->id} {$document->path}: {$exception->getMessage()}");

                continue;
            }

            if ($document->status === FinancialDocumentStatus::Stored) {
                $this->info("#{$document->id} régénéré.");
            } else {
                $failures++;
                $this->error("#{$document->id} {$document->path}: {$document->error}");
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
