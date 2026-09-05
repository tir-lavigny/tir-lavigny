<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Services\InfomaniakDavService;
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
                'error' => 'Erreur lors de la récupération des contacts: '.$e->getMessage(),
            ]);
        }

        return Inertia::render('backOffice/Dav/Index', [
            'contacts' => $contacts,
        ]);
    }
}
