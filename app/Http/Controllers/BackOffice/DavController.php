<?php

namespace App\Http\Controllers\BackOffice;

use App\DavGroupLabels;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\InfomaniakDavService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class DavController extends Controller
{
    public function index(InfomaniakDavService $service)
    {
        try {
            $contacts = $service->getContacts();
        } catch (\Exception $e) {
            return Inertia::render('backOffice/Dav/Index', [
                'contacts' => [],
                'existingDavUids' => [],
                'error' => 'Erreur lors de la récupération des contacts: '.$e->getMessage(),
            ]);
        }

        $existingDavUids = Member::whereNotNull('dav_uid')->pluck('dav_uid')->toArray();

        return Inertia::render('backOffice/Dav/Index', [
            'contacts' => $contacts,
            'existingDavUids' => $existingDavUids,
        ]);
    }

    public function importFromDav(InfomaniakDavService $service)
    {
        try {
            $contacts = $service->getContacts();
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Erreur lors de la récupération des contacts: '.$e->getMessage()]);
        }

        $existingDavUids = Member::whereNotNull('dav_uid')->pluck('dav_uid')->flip()->toArray();

        $actifsContacts = array_filter($contacts, function ($contact) {
            return str_contains($contact['category'] ?? '', DavGroupLabels::ACTIVE_MEMBERS->value);
        });

        $toImport = array_filter($actifsContacts, function ($contact) use ($existingDavUids) {
            return ! isset($existingDavUids[$contact['uid']]);
        });

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

        return back()->with('success', "$created membre(s) créé(s) avec succès.");
    }

    public function syncPreview(InfomaniakDavService $service): JsonResponse
    {
        try {
            $contacts = $service->getContacts();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération des contacts: '.$e->getMessage()], 500);
        }

        $actifsContacts = array_filter($contacts, function ($contact) {
            return str_contains($contact['category'] ?? '', DavGroupLabels::ACTIVE_MEMBERS->value);
        });

        $members = Member::whereNotNull('dav_uid')
            ->whereIn('dav_uid', array_column($actifsContacts, 'uid'))
            ->get()
            ->keyBy('dav_uid');

        $pairs = [];

        foreach ($actifsContacts as $contact) {
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

        return response()->json(['pairs' => $pairs]);
    }

    public function sync(InfomaniakDavService $service, Request $request)
    {
        $choices = $request->validate([
            'choices' => 'required|array|min:1',
            'choices.*.member_id' => 'required|exists:members,id',
            'choices.*.source' => 'required|in:member,dav',
        ]);

        $memberIds = array_column($choices['choices'], 'member_id');
        $members = Member::whereIn('id', $memberIds)->get()->keyBy('id');

        $updated = 0;

        foreach ($choices['choices'] as $choice) {
            $member = $members->get($choice['member_id']);

            if (! $member) {
                continue;
            }

            try {
                if ($choice['source'] === 'dav') {
                    $contact = $service->getContact(
                        rtrim($service->getAddressbookUrl(), '/').'/'.$member->dav_uid.'.vcf',
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
                    $service->updateMember($member);
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

        return back()->with('success', "$updated membre(s) synchronisé(s) avec succès.");
    }
}
