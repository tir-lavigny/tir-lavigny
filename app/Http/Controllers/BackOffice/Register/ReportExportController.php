<?php

namespace App\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\Controller;
use App\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelWriter;

class ReportExportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view reports'),
        ];
    }

    public function export(Request $request, Season $season)
    {
        $detail = $request->input('detail', 'balance');

        $fileName = 'rapport-saison-'.Str::slug($season->name).'-'.$detail.'.xlsx';

        return match ($detail) {
            'tickets' => $this->streamExcel($season, $fileName, 'tickets'),
            'items' => $this->streamExcel($season, $fileName, 'items'),
            default => $this->streamExcel($season, $fileName, 'balance'),
        };
    }

    private function streamExcel(Season $season, string $fileName, string $detail)
    {
        $writer = SimpleExcelWriter::streamDownload($fileName);

        foreach ($this->getRows($season, $detail) as $row) {
            $writer->addRow($row);
        }

        return $writer->toBrowser();
    }

    /**
     * @return iterable<array<string, string>>
     */
    public function getRows(Season $season, string $detail): iterable
    {
        return match ($detail) {
            'tickets' => $this->ticketRows($season),
            'items' => $this->itemRows($season),
            default => $this->balanceRows($season),
        };
    }

    private function balanceRows(Season $season): iterable
    {
        $days = $season->operatingDays()->orderBy('date')->get();

        foreach ($days as $day) {
            yield [
                'Date' => $day->date->format('d.m.Y'),
                'Total Espèces' => number_format((float) $day->total_cash, 2, ',', '.'),
                'Total Twint' => number_format((float) $day->total_twint, 2, ',', '.'),
                'Solde Clôture' => $day->closing_balance !== null
                    ? number_format((float) $day->closing_balance, 2, ',', '.')
                    : '',
            ];
        }
    }

    private function ticketRows(Season $season): iterable
    {
        $days = $season->operatingDays()
            ->with(['tickets'])
            ->orderBy('date')
            ->get();

        foreach ($days as $day) {
            foreach ($day->tickets as $ticket) {
                yield [
                    'Date' => $day->date->format('d.m.Y'),
                    '# Ticket' => $ticket->id,
                    'Vendu à' => $ticket->sold_at->setTimezone('Europe/Zurich')->format('H:i'),
                    'Paiement' => $ticket->payment_method === 'cash' ? 'Espèces' : 'TWINT',
                    'Total' => number_format((float) $ticket->total, 2, ',', '.'),
                ];
            }
        }
    }

    private function itemRows(Season $season): iterable
    {
        $days = $season->operatingDays()
            ->with(['tickets.ticketItems.product', 'tickets.ticketItems.voucher'])
            ->orderBy('date')
            ->get();

        foreach ($days as $day) {
            foreach ($day->tickets as $ticket) {
                foreach ($ticket->ticketItems as $item) {
                    $discount = 0;
                    if ($item->voucher) {
                        if ($item->voucher->discount_percent !== null) {
                            $discount = $item->unit_price * ($item->voucher->discount_percent / 100);
                        } else {
                            $discount = $item->voucher->discount_fixed;
                        }
                    }

                    yield [
                        'Date' => $day->date->format('d.m.Y'),
                        '# Ticket' => $ticket->id,
                        'Produit' => $item->product->name,
                        'Quantité' => $item->quantity,
                        'Prix Unitaire' => number_format((float) $item->unit_price, 2, ',', '.'),
                        'Sous-total' => number_format((float) $item->subtotal, 2, ',', '.'),
                        'Réduction' => $discount > 0 ? number_format($discount, 2, ',', '.') : '',
                    ];
                }
            }
        }
    }
}
