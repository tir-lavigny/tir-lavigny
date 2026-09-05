<?php

namespace App\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Register\StoreSeasonRequest;
use App\Http\Requests\BackOffice\Register\UpdateSeasonRequest;
use App\Models\Member;
use App\Models\Product;
use App\Models\Season;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SeasonController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage seasons', only: ['create', 'store', 'edit', 'update', 'destroy']),
        ];
    }

    public function index(): Response
    {
        $seasons = Season::orderBy('start_date', 'desc')->paginate(15);

        return Inertia::render('backOffice/Register/Seasons/Index', [
            'seasons' => $seasons,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('backOffice/Register/Seasons/Create');
    }

    public function store(StoreSeasonRequest $request)
    {
        Season::create($request->validated());

        return redirect()->route('bo.register.seasons.index')->with('success', 'Season created successfully.');
    }

    public function show(Season $season): Response
    {
        $season->load(['operatingDays' => function ($query) {
            $query->orderBy('date');
        }]);

        return Inertia::render('backOffice/Register/Seasons/Show', [
            'season' => $season,
        ]);
    }

    public function edit(Season $season): Response
    {
        return Inertia::render('backOffice/Register/Seasons/Edit', [
            'season' => $season,
        ]);
    }

    public function update(UpdateSeasonRequest $request, Season $season)
    {
        $season->update($request->validated());

        return redirect()->route('bo.register.seasons.index')->with('success', 'Season updated successfully.');
    }

    public function destroy(Season $season)
    {
        try {
            $season->delete();

            return redirect()->route('bo.register.seasons.index')->with('success', 'Saison supprimée.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function previewBaseVouchers(Season $season): JsonResponse
    {
        $basePackage = $this->getBasePackage();
        $products = $this->resolveProducts($basePackage);
        $members = Member::all();
        $plan = $this->computePlan($season, $members, $basePackage, $products);

        return response()->json([
            'plan' => $plan,
            'total_members' => $members->count(),
        ]);
    }

    public function generateBaseVouchers(Season $season): RedirectResponse
    {
        $basePackage = $this->getBasePackage();
        $products = $this->resolveProducts($basePackage);
        $members = Member::all();
        $plan = $this->computePlan($season, $members, $basePackage, $products);

        DB::transaction(function () use ($plan, $season) {
            foreach ($plan as $memberPlan) {
                foreach ($memberPlan['vouchers'] as $voucherPlan) {
                    if ($voucherPlan['count'] <= 0) {
                        continue;
                    }

                    $product = Product::where('name', $voucherPlan['product_name'])->first();

                    for ($i = 0; $i < $voucherPlan['count']; $i++) {
                        Voucher::create([
                            'discount_percent' => 100.00,
                            'discount_fixed' => null,
                            'product_id' => $product->id,
                            'season_id' => $season->id,
                            'member_id' => $memberPlan['member_id'],
                        ]);
                    }
                }
            }
        });

        return redirect()->back()->with('success', 'Bons de base générés avec succès.');
    }

    private function getBasePackage(): array
    {
        return [
            'Tir du jeudi' => 3,
            'Ouverture' => 1,
            'Clôture' => 1,
        ];
    }

    private function resolveProducts(array $basePackage): array
    {
        $products = [];

        foreach ($basePackage as $name => $count) {
            $product = Product::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();

            if (! $product) {
                abort(422, "Produit introuvable : {$name}");
            }

            $products[$name] = $product;
        }

        return $products;
    }

    private function computePlan(Season $season, $members, array $basePackage, array $products): array
    {
        $plan = [];

        foreach ($members as $member) {
            $vouchers = [];

            foreach ($basePackage as $productName => $requiredCount) {
                $product = $products[$productName];

                $existingCount = Voucher::where('member_id', $member->id)
                    ->where('season_id', $season->id)
                    ->where('product_id', $product->id)
                    ->withTrashed()
                    ->count();

                $missing = $requiredCount - $existingCount;

                if ($missing > 0) {
                    $vouchers[] = [
                        'product_name' => $productName,
                        'count' => $missing,
                    ];
                }
            }

            if (count($vouchers) > 0) {
                $plan[] = [
                    'member_id' => $member->id,
                    'member_name' => $member->first_name.' '.$member->last_name,
                    'vouchers' => $vouchers,
                ];
            }
        }

        return $plan;
    }
}
