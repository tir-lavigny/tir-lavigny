<?php

namespace App\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CashMovementController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage cash movements'),
        ];
    }

    public function store(Request $request, Season $season): RedirectResponse
    {
        $validated = $this->validateMovement($request, $season);

        DB::transaction(function () use ($season, $validated) {
            $season->cashMovements()->create($this->payload($validated));
            $season->recalculateClosingBalances();
        });

        return back()->with('success', 'Mouvement de caisse enregistré.');
    }

    public function update(Request $request, Season $season, CashMovement $cashMovement): RedirectResponse
    {
        abort_unless($cashMovement->season_id === $season->id, 404);

        $validated = $this->validateMovement($request, $season);

        DB::transaction(function () use ($season, $cashMovement, $validated) {
            $cashMovement->update($this->payload($validated));
            $season->recalculateClosingBalances();
        });

        return back()->with('success', 'Mouvement de caisse mis à jour.');
    }

    public function destroy(Season $season, CashMovement $cashMovement): RedirectResponse
    {
        abort_unless($cashMovement->season_id === $season->id, 404);

        DB::transaction(function () use ($season, $cashMovement) {
            $cashMovement->delete();
            $season->recalculateClosingBalances();
        });

        return back()->with('success', 'Mouvement de caisse supprimé.');
    }

    /**
     * @return array{direction: string, amount: float, occurred_at: string, remark?: string|null}
     */
    private function validateMovement(Request $request, Season $season): array
    {
        return $request->validate([
            'direction' => ['required', 'string', Rule::in(['in', 'out'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'occurred_at' => [
                'required',
                'date',
                'after_or_equal:'.$season->start_date->toDateString(),
                'before_or_equal:'.$season->end_date->toDateString(),
                'before_or_equal:'.Carbon::today('Europe/Zurich')->toDateString(),
            ],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @param  array{direction: string, amount: float, occurred_at: string, remark?: string|null}  $validated
     * @return array{amount: float, occurred_at: string, remark: string|null}
     */
    private function payload(array $validated): array
    {
        $amount = abs((float) $validated['amount']);

        return [
            'amount' => $validated['direction'] === 'out' ? -$amount : $amount,
            'occurred_at' => $validated['occurred_at'],
            'remark' => $validated['remark'] ?? null,
        ];
    }
}
