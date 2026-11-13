<?php

namespace App\Services;

use App\DavGroupLabels;
use App\MemberStatus;
use App\Models\Member;
use App\Support\PiiMasker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MemberSyncService
{
    public function __construct(
        private readonly DavServiceInterface $dav,
    ) {}

    /** @return array{success: bool, error: string|null} */
    public function push(Member $member): array
    {
        try {
            if (! $member->dav_uid) {
                $result = $this->dav->createContactFromMember($member);

                $member->forceFill(['dav_uid' => $result['uid']])->saveQuietly();
            } elseif (! $this->dav->updateMember($member)) {
                throw new \RuntimeException('Impossible de mettre à jour le contact DAV.');
            }

            $member->forceFill([
                'dav_synced_at' => now(),
                'dav_synced_snapshot' => $this->snapshot($member),
                'dav_sync_error' => null,
            ])->saveQuietly();

            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            report($e);

            $member->forceFill([
                'dav_sync_error' => $e->getMessage(),
            ])->saveQuietly();

            Log::warning('Member DAV sync failed', [
                'member_id' => $member->id,
                'dav_uid' => $member->dav_uid,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array{success: bool, error: string|null} */
    public function delete(Member $member): array
    {
        try {
            if ($member->dav_uid && ! $this->dav->deleteMember($member)) {
                throw new \RuntimeException('Impossible de supprimer le contact DAV.');
            }

            if ($member->exists) {
                $member->forceFill(['dav_sync_error' => null])->saveQuietly();
            }

            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            report($e);

            Log::warning('Member DAV delete failed', [
                'member_id' => $member->id,
                'dav_uid' => $member->dav_uid,
                'error' => $e->getMessage(),
            ]);

            if ($member->exists) {
                $member->forceFill(['dav_sync_error' => $e->getMessage()])->saveQuietly();
            }

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{
     *     members: array<int, array<string, mixed>>,
     *     unlinked_contacts: array<int, array<string, mixed>>,
     *     unlinked_members: array<int, array<string, mixed>>
     * }
     */
    public function preview(): array
    {
        $contacts = collect($this->dav->getContacts());
        $contactsByUid = $contacts->keyBy('uid');

        $members = Member::withTrashed()
            ->whereNotNull('dav_uid')
            ->orderByName()
            ->get();

        $memberUids = $members->pluck('dav_uid')->filter()->all();

        $memberRows = [];

        foreach ($members as $member) {
            $contact = $contactsByUid->get($member->dav_uid);

            if (! $contact) {
                $memberRows[] = [
                    'member_id' => $member->id,
                    'member_name' => $this->memberName($member),
                    'dav_uid' => $member->dav_uid,
                    'missing_contact' => true,
                    'fields' => [],
                ];

                continue;
            }

            $memberValues = $this->memberValues($member);
            $davValues = $this->davValues($contact);
            $base = $member->dav_synced_snapshot ?? [];

            $fields = [];

            foreach (['first_name', 'last_name', 'email', 'tel', 'status', 'archived'] as $field) {
                $fields[$field] = $this->fieldRow(
                    $memberValues[$field],
                    $davValues[$field],
                    array_key_exists($field, $base) ? $base[$field] : null,
                );
            }

            $memberRows[] = [
                'member_id' => $member->id,
                'member_name' => $this->memberName($member),
                'dav_uid' => $member->dav_uid,
                'missing_contact' => false,
                'fields' => $fields,
            ];
        }

        $memberRows = array_values(array_filter($memberRows, function (array $row): bool {
            if ($row['missing_contact']) {
                return true;
            }

            return collect($row['fields'])->contains(fn (array $field): bool => $field['member'] !== $field['dav']);
        }));

        $unlinkedMembers = Member::withTrashed()
            ->whereNull('dav_uid')
            ->orderByName()
            ->get();

        $unlinkedContacts = $contacts
            ->filter(fn (array $contact) => ($contact['uid'] ?? '') !== '')
            ->reject(fn (array $contact) => in_array($contact['uid'], $memberUids, true))
            ->values();

        $membersByEmail = $unlinkedMembers
            ->filter(fn (Member $member): bool => $member->email !== null && $member->email !== '')
            ->groupBy(fn (Member $member): string => Str::lower(trim((string) $member->email)));

        $contactsByEmail = $unlinkedContacts
            ->filter(fn (array $contact): bool => ($contact['email'] ?? '') !== '')
            ->groupBy(fn (array $contact): string => Str::lower(trim((string) $contact['email'])));

        $unlinkedContactRows = $unlinkedContacts->map(function (array $contact) use ($membersByEmail): array {
            $emailKey = ($contact['email'] ?? '') !== '' ? Str::lower(trim((string) $contact['email'])) : null;
            $matches = $emailKey !== null ? $membersByEmail->get($emailKey, collect()) : collect();
            $suggested = $matches->count() === 1 ? $matches->first() : null;

            return [
                'uid' => $contact['uid'],
                'name' => $this->contactName($contact),
                'email' => PiiMasker::email($contact['email'] ?? null),
                'tel' => $contact['tel'] ?? null,
                'category' => $contact['category'] ?? '',
                'suggested_status' => $this->statusFromCategory($contact['category'] ?? ''),
                'archived' => $this->isArchivedCategory($contact['category'] ?? ''),
                'suggested_member_id' => $suggested?->id,
                'suggested_member_name' => $suggested ? $this->memberName($suggested) : null,
            ];
        })->all();

        $unlinkedMemberRows = $unlinkedMembers->map(function (Member $member) use ($contactsByEmail): array {
            $emailKey = $member->email !== null && $member->email !== '' ? Str::lower(trim((string) $member->email)) : null;
            $matches = $emailKey !== null ? $contactsByEmail->get($emailKey, collect()) : collect();
            $suggested = $matches->count() === 1 ? $matches->first() : null;

            return [
                'member_id' => $member->id,
                'member_name' => $this->memberName($member),
                'email' => $member->email_masked,
                'status' => $member->trashed() ? 'archived' : $member->status->value,
                'suggested_contact_uid' => $suggested['uid'] ?? null,
                'suggested_contact_name' => $suggested ? $this->contactName($suggested) : null,
            ];
        })->all();

        return [
            'members' => $memberRows,
            'unlinked_contacts' => $unlinkedContactRows,
            'unlinked_members' => $unlinkedMemberRows,
        ];
    }

    /**
     * @param  array<int, array{member_id: int, fields: array<string, string>}>  $choices
     * @return array{updated: int}
     */
    public function apply(array $choices): array
    {
        $updated = 0;
        $contactsByUid = collect($this->dav->getContacts())->keyBy('uid');

        foreach ($choices as $choice) {
            $member = Member::withTrashed()->find($choice['member_id']);

            if (! $member) {
                continue;
            }

            $davValues = null;

            if ($member->dav_uid) {
                $contact = $contactsByUid->get($member->dav_uid);
                $davValues = $contact ? $this->davValues($contact) : null;
            }

            $dirty = false;

            foreach ($choice['fields'] as $field => $source) {
                if ($source !== 'dav' || ! $davValues) {
                    continue;
                }

                if ($field === 'archived') {
                    $member->deleted_at = $davValues['archived'] ? now() : null;
                    $dirty = true;

                    continue;
                }

                if ($field === 'status') {
                    if ($davValues['status'] !== null) {
                        $member->status = MemberStatus::from($davValues['status']);
                        $dirty = true;
                    }

                    continue;
                }

                if (in_array($field, ['first_name', 'last_name', 'email', 'tel'], true)) {
                    $member->{$field} = $davValues[$field];
                    $dirty = true;
                }
            }

            if ($dirty) {
                $member->save();
            }

            if ($this->push($member)['success']) {
                $updated++;
            }
        }

        return ['updated' => $updated];
    }

    /** @return array{success: bool, error: string|null} */
    public function link(Member $member, string $davUid): array
    {
        try {
            $contact = collect($this->dav->getContacts())->firstWhere('uid', $davUid);
        } catch (Throwable $e) {
            report($e);

            return ['success' => false, 'error' => 'Impossible de vérifier le contact DAV.'];
        }

        if (! $contact) {
            return ['success' => false, 'error' => 'Contact DAV introuvable.'];
        }

        $member->forceFill([
            'dav_uid' => $davUid,
            'dav_synced_at' => null,
            'dav_synced_snapshot' => null,
            'dav_sync_error' => null,
        ])->saveQuietly();

        return ['success' => true, 'error' => null];
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    public function createFromContact(array $contact): Member
    {
        $values = $this->davValues($contact);
        $status = $values['status'] === null ? MemberStatus::ACTIVE : MemberStatus::from($values['status']);

        $member = Member::create([
            'first_name' => $values['first_name'],
            'last_name' => $values['last_name'],
            'email' => $values['email'],
            'tel' => $values['tel'],
            'status' => $status,
            'dav_uid' => $contact['uid'] ?? null,
        ]);

        if ($values['archived']) {
            $member->deleted_at = now();
        }

        $member->forceFill([
            'dav_synced_at' => now(),
            'dav_synced_snapshot' => $this->snapshot($member),
        ])->saveQuietly();

        return $member;
    }

    /** @return array<string, mixed> */
    private function snapshot(Member $member): array
    {
        return [
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'email' => $member->email,
            'tel' => $member->tel,
            'status' => $member->status->value,
            'archived' => $member->trashed(),
        ];
    }

    /** @return array<string, mixed> */
    private function memberValues(Member $member): array
    {
        return [
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'email' => $member->email,
            'tel' => $member->tel,
            'status' => $member->status->value,
            'archived' => $member->trashed(),
        ];
    }

    /** @param array<string, mixed> $contact
     * @return array<string, mixed> */
    private function davValues(array $contact): array
    {
        [$lastName, $firstName] = $this->parseName($contact);

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $this->nullableString($contact['email'] ?? null),
            'tel' => $this->nullableString($contact['tel'] ?? null),
            'status' => $this->statusFromCategory($contact['category'] ?? ''),
            'archived' => $this->isArchivedCategory($contact['category'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $contact
     * @return array{0: string, 1: string} */
    private function parseName(array $contact): array
    {
        $parts = explode(';', (string) ($contact['n'] ?? ''));

        $lastName = trim($parts[0]);
        $firstName = trim($parts[1] ?? '');

        if ($lastName === '' && $firstName === '') {
            $fn = trim((string) ($contact['fn'] ?? ''));

            if ($fn !== '') {
                $segments = preg_split('/\s+/', $fn) ?: [];
                $firstName = (string) array_shift($segments);
                $lastName = implode(' ', $segments);
            }
        }

        return [$lastName, $firstName];
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function contactName(array $contact): string
    {
        [$lastName, $firstName] = $this->parseName($contact);

        return trim($firstName.' '.$lastName);
    }

    private function memberName(Member $member): string
    {
        return trim($member->first_name.' '.$member->last_name);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function statusFromCategory(string $category): ?string
    {
        $categories = collect(explode(',', $category))->map(fn (string $value) => trim($value));

        if ($categories->contains(DavGroupLabels::SOFT_DELETED_MEMBERS->value)) {
            return 'active';
        }

        if ($categories->contains(DavGroupLabels::PASSIVE_MEMBERS->value)) {
            return 'passive';
        }

        if ($categories->contains(DavGroupLabels::ACTIVE_MEMBERS->value)) {
            return 'active';
        }

        return null;
    }

    private function isArchivedCategory(string $category): bool
    {
        return collect(explode(',', $category))
            ->map(fn (string $value) => trim($value))
            ->contains(DavGroupLabels::SOFT_DELETED_MEMBERS->value);
    }

    /** @return array{member: mixed, dav: mixed, base: mixed, conflict: bool} */
    private function fieldRow(mixed $memberValue, mixed $davValue, mixed $baseValue): array
    {
        $memberValue = $this->normalize($memberValue);
        $davValue = $this->normalize($davValue);
        $baseValue = $this->normalize($baseValue);

        $conflict = $baseValue !== null
            && $memberValue !== $baseValue
            && $davValue !== $baseValue
            && $memberValue !== $davValue;

        return [
            'member' => $memberValue,
            'dav' => $davValue,
            'base' => $baseValue,
            'conflict' => $conflict,
        ];
    }

    private function normalize(mixed $value): mixed
    {
        if ($value === '') {
            return null;
        }

        return $value;
    }
}
