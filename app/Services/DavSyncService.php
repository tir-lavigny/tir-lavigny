<?php

namespace App\Services;

use App\DavGroupLabels;
use App\Models\Member;
use Illuminate\Support\Facades\Log;

class DavSyncService
{
    public function __construct(
        private readonly DavServiceInterface $dav
    ) {}

    /**
     * Import members from DAV contacts that don't already exist.
     */
    public function importFromDav(): array
    {
        $contacts = $this->dav->getContacts();
        $existingDavUids = Member::whereNotNull('dav_uid')->pluck('dav_uid')->flip()->toArray();

        $activeContacts = array_filter($contacts, fn ($contact) => str_contains($contact['category'] ?? '', DavGroupLabels::ACTIVE_MEMBERS->value)
        );

        $toImport = array_filter($activeContacts, fn ($contact) => ! isset($existingDavUids[$contact['uid']])
        );

        $created = 0;

        foreach ($toImport as $contact) {
            $parts = explode(';', $contact['n'] ?? '');
            $lastName = $parts[0] ?? '';
            $firstName = $parts[1] ?? '';

            Member::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'dav_uid' => $contact['uid'],
            ]);

            $created++;
        }

        Log::info('Imported members from DAV contacts', ['count' => $created]);

        return ['created' => $created];
    }

    /**
     * Build a diff preview of DAV contacts vs existing members.
     *
     * @return array{pairs: array<int, array{member_id: int, member_first_name: string, member_last_name: string, dav_first_name: string, dav_last_name: string, dav_uid: string}>}
     */
    public function syncPreview(): array
    {
        $contacts = $this->dav->getContacts();

        $activeContacts = array_filter($contacts, fn ($contact) => str_contains($contact['category'] ?? '', DavGroupLabels::ACTIVE_MEMBERS->value)
        );

        $members = Member::whereNotNull('dav_uid')
            ->whereIn('dav_uid', array_column($activeContacts, 'uid'))
            ->get()
            ->keyBy('dav_uid');

        $pairs = [];

        foreach ($activeContacts as $contact) {
            $member = $members->get($contact['uid']);

            if (! $member) {
                continue;
            }

            $parts = explode(';', $contact['n'] ?? '');
            $davLastName = $parts[0] ?? '';
            $davFirstName = $parts[1] ?? '';

            if ($davFirstName === $member->first_name && $davLastName === $member->last_name) {
                continue;
            }

            $pairs[] = [
                'member_id' => $member->id,
                'member_first_name' => $member->first_name,
                'member_last_name' => $member->last_name,
                'dav_first_name' => $davFirstName,
                'dav_last_name' => $davLastName,
                'dav_uid' => $contact['uid'],
            ];
        }

        return ['pairs' => $pairs];
    }

    /**
     * Apply sync choices: update members from DAV or DAV from members.
     */
    public function sync(array $choices): array
    {
        $memberIds = array_column($choices, 'member_id');
        $members = Member::whereIn('id', $memberIds)->get()->keyBy('id');

        $updated = 0;

        foreach ($choices as $choice) {
            $member = $members->get($choice['member_id']);

            if (! $member) {
                continue;
            }

            try {
                if ($choice['source'] === 'dav') {
                    $contact = $this->dav->getContact(
                        rtrim($this->dav->getAddressbookUrl(), '/').'/'.$member->dav_uid.'.vcf',
                    );

                    if ($contact) {
                        $parts = explode(';', $contact['n'] ?? '');
                        $member->update([
                            'first_name' => $parts[1] ?? '',
                            'last_name' => $parts[0] ?? '',
                        ]);
                        $updated++;
                    }
                } else {
                    $this->dav->updateMember($member);
                    $updated++;
                }
            } catch (\Exception $e) {
                Log::warning('Failed to sync member', [
                    'member_id' => $member->id,
                    'source' => $choice['source'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Synced members with DAV', ['count' => $updated]);

        return ['updated' => $updated];
    }
}
