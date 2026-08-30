<?php

namespace App\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Register\StoreVoucherRequest;
use App\Http\Requests\BackOffice\Register\UpdateVoucherRequest;
use App\Models\Member;
use App\Models\Product;
use App\Models\Season;
use App\Models\Voucher;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class VoucherController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage vouchers', only: ['create', 'store', 'edit', 'update', 'destroy']),
        ];
    }

    public function index(): Response
    {
        $vouchers = Voucher::with(['product', 'season', 'member'])
            ->orderBy('id')
            ->paginate(15);

        return Inertia::render('backOffice/Register/Vouchers/Index', [
            'vouchers' => $vouchers,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('backOffice/Register/Vouchers/Create', [
            'products' => Product::whereNull('deleted_at')->orderBy('name')->get(),
            'seasons' => Season::whereNull('deleted_at')->orderBy('name')->get(),
            'members' => Member::whereNull('deleted_at')->orderBy('last_name')->get(),
        ]);
    }

    public function store(StoreVoucherRequest $request)
    {
        Voucher::create($request->validated());

        return redirect()->route('bo.register.vouchers.index')->with('success', 'Voucher created successfully.');
    }

    public function edit(Voucher $voucher): Response
    {
        $voucher->load(['product', 'season', 'member']);

        return Inertia::render('backOffice/Register/Vouchers/Edit', [
            'voucher' => $voucher,
            'products' => Product::whereNull('deleted_at')->orderBy('name')->get(),
            'seasons' => Season::whereNull('deleted_at')->orderBy('name')->get(),
            'members' => Member::whereNull('deleted_at')->orderBy('last_name')->get(),
        ]);
    }

    public function update(UpdateVoucherRequest $request, Voucher $voucher)
    {
        $voucher->update($request->validated());

        return redirect()->route('bo.register.vouchers.index')->with('success', 'Voucher updated successfully.');
    }

    public function destroy(Voucher $voucher)
    {
        $voucher->delete();

        return redirect()->route('bo.register.vouchers.index')->with('success', 'Voucher deleted successfully.');
    }
}
