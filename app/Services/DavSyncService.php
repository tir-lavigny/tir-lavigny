<?php

namespace App\Services;

use App\DavGroupLabels;
use App\Models\Member;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DavSyncService
{
    public function __construct(
        private readonly DavServiceInterface $dav,
        private readonly MemberSyncService $memberSync,
    ) {}

    /**
     * Import unknown DAV contacts as members.
     *
     * @return array{created: int, skipped: array<int, string>, failed: array<int, string>}
     */
    public function importFromDav(): array
    {
        $contacts = $this->dav->getContacts();
        $existingDavUids = Member::withTrashed()->whereNotNull('dav_uid')->pluck('dav_uid')->flip();

        $unlinkedMemberEmails = Member::withTrashed()
            ->whereNull('dav_uid')
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->map(fn (string $email): string => Str::lower(trim($email)))
            ->unique()
            ->flip();

        $toImport = collect($contacts)
            ->filter(fn (array $contact): bool => ($contact['uid'] ?? '') !== '')
            ->reject(fn (array $contact): bool => $existingDavUids->has($contact['uid']))
            ->filter(fn (array $contact): bool => $this->hasCategory($contact, DavGroupLabels::ACTIVE_MEMBERS->value)
                || $this->hasCategory($contact, DavGroupLabels::PASSIVE_MEMBERS->value));

        $created = 0;
        $skipped = [];
        $failed = [];
        $knownEmails = $unlinkedMemberEmails;

        foreach ($toImport as $contact) {
            $email = Str::lower(trim((string) ($contact['email'] ?? '')));

            if ($email !== '' && $knownEmails->has($email)) {
                $skipped[] = $contact['uid'];

                continue;
            }

            try {
                $this->memberSync->createFromContact($contact);
                $created++;

                if ($email !== '') {
                    $knownEmails->put($email, true);
                }
            } catch (\Throwable $e) {
                report($e);
                $failed[] = $contact['uid'];

                Log::warning('Failed to import DAV contact', [
                    'uid' => $contact['uid'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Imported members from DAV contacts', [
            'created' => $created,
            'skipped' => count($skipped),
            'failed' => count($failed),
            'contacts' => count($contacts),
        ]);

        return ['created' => $created, 'skipped' => $skipped, 'failed' => $failed];
    }

    /** @param array<string, mixed> $contact */
    private function hasCategory(array $contact, string $category): bool
    {
        return collect(explode(',', (string) ($contact['category'] ?? '')))
            ->map(fn (string $value): string => trim($value))
            ->contains($category);
    }
}
