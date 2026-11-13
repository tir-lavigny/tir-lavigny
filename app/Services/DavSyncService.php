<?php

namespace App\Services;

use App\DavGroupLabels;
use App\Models\Member;
use Illuminate\Support\Facades\Log;

class DavSyncService
{
    public function __construct(
        private readonly DavServiceInterface $dav,
        private readonly MemberSyncService $memberSync,
    ) {}

    /**
     * Import members from DAV contacts that don't already exist.
     */
    /** @return array{created: int} */
    public function importFromDav(): array
    {
        $contacts = $this->dav->getContacts();
        $existingDavUids = Member::withTrashed()->whereNotNull('dav_uid')->pluck('dav_uid')->flip()->toArray();

        $activeContacts = collect($contacts)->filter(fn (array $contact): bool => $this->hasCategory($contact, DavGroupLabels::ACTIVE_MEMBERS->value));

        $toImport = $activeContacts->filter(fn (array $contact): bool => ! isset($existingDavUids[$contact['uid']]));

        $created = 0;

        foreach ($toImport as $contact) {
            $this->memberSync->createFromContact($contact);

            $created++;
        }

        Log::info('Imported members from DAV contacts', [
            'count' => $created,
            'contacts' => count($contacts),
            'skipped' => count($contacts) - count($toImport),
        ]);

        return ['created' => $created];
    }

    private function hasCategory(array $contact, string $category): bool
    {
        return collect(explode(',', (string) ($contact['category'] ?? '')))
            ->map(fn (string $value): string => trim($value))
            ->contains($category);
    }
}
