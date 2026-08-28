<?php

namespace App\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Register\UpdateOperatingDayRequest;
use App\Models\OperatingDay;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class OperatingDayController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage operating days', only: ['update']),
        ];
    }

    public function index(Request $request): Response
    {
        $query = OperatingDay::with('season')->orderBy('date', 'desc');

        if ($seasonId = $request->input('season_id')) {
            $query->where('season_id', $seasonId);
        }

        $days = $query->paginate(15)->withQueryString();

        return Inertia::render('backOffice/Register/OperatingDays/Index', [
            'operatingDays' => $days,
            'filters' => ['season_id' => $seasonId ?? null],
        ]);
    }

    public function show(OperatingDay $operatingDay): Response
    {
        $operatingDay->load(['tickets.ticketItems.product', 'tickets.ticketItems.voucher']);

        return Inertia::render('backOffice/Register/OperatingDays/Show', [
            'operatingDay' => $operatingDay,
        ]);
    }

    public function update(UpdateOperatingDayRequest $request, OperatingDay $operatingDay)
    {
        $operatingDay->update($request->validated());

        return redirect()->route('bo.register.operating-days.show', $operatingDay)
            ->with('success', 'Operating day updated successfully.');
    }
}
