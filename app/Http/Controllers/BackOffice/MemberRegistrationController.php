<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\MemberRegistration;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class MemberRegistrationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:create member registration', only: ['create', 'store']),
            new Middleware('permission:manage member registration', only: []),
        ];
    }

    public function index() {}

    public function show(MemberRegistration $memberRegistration) {}

    public function store(Request $request) {}
}
