<?php

namespace App\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Member;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class TrashController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:restore register'),
        ];
    }

    protected const TYPE_MAP = [
        'category' => Category::class,
        'product' => Product::class,
        'season' => Season::class,
        'member' => Member::class,
        'ticket' => Ticket::class,
        'voucher' => Voucher::class,
    ];

    public function index(Request $request): Response
    {
        $categories = Category::onlyTrashed()->orderBy('deleted_at', 'desc')->paginate(15, ['*'], 'categories_page');
        $products = Product::with('category')->onlyTrashed()->orderBy('deleted_at', 'desc')->paginate(15, ['*'], 'products_page');
        $seasons = Season::onlyTrashed()->orderBy('deleted_at', 'desc')->paginate(15, ['*'], 'seasons_page');
        $members = Member::onlyTrashed()->orderBy('deleted_at', 'desc')->paginate(15, ['*'], 'members_page');
        $tickets = Ticket::with('operatingDay', 'member')->onlyTrashed()->orderBy('deleted_at', 'desc')->paginate(15, ['*'], 'tickets_page');

        $vouchersQuery = Voucher::with(['product', 'season', 'member'])->onlyTrashed()->orderBy('deleted_at', 'desc');
        if ($memberId = $request->input('member_id')) {
            $vouchersQuery->where('member_id', $memberId);
        }
        $vouchers = $vouchersQuery->paginate(15, ['*'], 'vouchers_page');

        return Inertia::render('backOffice/Register/Trash/Index', [
            'categories' => $categories,
            'products' => $products,
            'seasons' => $seasons,
            'members' => $members,
            'tickets' => $tickets,
            'vouchers' => $vouchers,
            'memberFilter' => $memberId,
            'allMembers' => Member::orderBy('last_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function restore(Request $request, string $type, int $id): JsonResponse|RedirectResponse
    {
        $modelClass = $this->resolveType($type);

        $model = $modelClass::onlyTrashed()->findOrFail($id);

        if (! $request->boolean('confirm')) {
            return response()->json([
                'item' => [
                    'id' => $model->id,
                    'name' => $this->getItemLabel($model, $type),
                    'type' => $type,
                    'deleted_at' => $model->deleted_at,
                ],
                'will_restore' => $this->getChildrenCounts($model, $type),
            ]);
        }

        $model->restore();

        return redirect()->back()->with('success', 'Élément restauré avec succès.');
    }

    public function forceDelete(Request $request, string $type, int $id): JsonResponse
    {
        $modelClass = $this->resolveType($type);

        $model = $modelClass::onlyTrashed()->findOrFail($id);

        $children = $this->getChildrenCounts($model, $type);
        if (count($children) > 0) {
            $labels = array_map(fn ($c) => $c['label'], $children);

            return response()->json([
                'message' => 'Impossible de supprimer définitivement car cet élément contient : '.implode(', ', $labels).'. Supprimez-les d\'abord.',
            ], 422);
        }

        try {
            $model->forceDelete();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Impossible de supprimer définitivement cet élément. Une contrainte de base de données empêche la suppression.',
            ], 422);
        }

        return response()->json(['success' => true, 'message' => 'Élément supprimé définitivement.']);
    }

    protected function resolveType(string $type): string
    {
        if (! isset(self::TYPE_MAP[$type])) {
            abort(422, 'Type d\'élément invalide.');
        }

        return self::TYPE_MAP[$type];
    }

    protected function getItemLabel($model, string $type): string
    {
        return match ($type) {
            'category' => $model->name,
            'product' => $model->name,
            'season' => $model->name,
            'member' => $model->first_name.' '.$model->last_name,
            'ticket' => 'Ticket #'.$model->id,
            'voucher' => 'Bon #'.$model->id,
            default => '#'.$model->id,
        };
    }

    protected function getChildrenCounts($model, string $type): array
    {
        return match ($type) {
            'category' => $this->getChildrenForCategory($model),
            'product' => $this->getChildrenForProduct($model),
            'ticket' => $this->getChildrenForTicket($model),
            'member' => $this->getChildrenForMember($model),
            'season' => $this->getChildrenForSeason($model),
            'voucher' => $this->getChildrenForVoucher($model),
        };
    }

    protected function getChildrenForSeason(Season $season): array
    {
        $totalDays = OperatingDay::where('season_id', $season->id)->count();
        $trashedVouchers = Voucher::where('season_id', $season->id)->onlyTrashed()->count();
        $activeVouchers = Voucher::where('season_id', $season->id)->count();
        $children = [];

        if ($totalDays > 0) {
            $children[] = ['type' => 'operating_day', 'count' => $totalDays, 'label' => $totalDays.' journée(s)'];
        }

        $totalVouchers = $trashedVouchers + $activeVouchers;
        if ($totalVouchers > 0) {
            $children[] = ['type' => 'voucher', 'count' => $totalVouchers, 'label' => $totalVouchers.' bon(s)'];
        }

        return $children;
    }

    protected function getChildrenForCategory(Category $category): array
    {
        $trashedProducts = Product::where('category_id', $category->id)->onlyTrashed()->count();
        $activeProducts = Product::where('category_id', $category->id)->withoutTrashed()->count();
        $children = [];

        if ($trashedProducts > 0) {
            $children[] = ['type' => 'product', 'count' => $trashedProducts, 'label' => $trashedProducts.' produit(s) supprimé(s)'];
        }
        if ($activeProducts > 0) {
            $children[] = ['type' => 'product', 'count' => $activeProducts, 'label' => $activeProducts.' produit(s) actif(s)'];
        }

        return $children;
    }

    protected function getChildrenForTicket(Ticket $ticket): array
    {
        $trashedItems = TicketItem::where('ticket_id', $ticket->id)->onlyTrashed()->count();
        $activeItems = TicketItem::where('ticket_id', $ticket->id)->withoutTrashed()->count();
        $children = [];

        if ($trashedItems > 0) {
            $children[] = ['type' => 'ticket_item', 'count' => $trashedItems, 'label' => $trashedItems.' article(s) supprimé(s)'];
        }
        if ($activeItems > 0) {
            $children[] = ['type' => 'ticket_item', 'count' => $activeItems, 'label' => $activeItems.' article(s) actif(s)'];
        }

        return $children;
    }

    protected function getChildrenForMember(Member $member): array
    {
        $trashedVouchers = Voucher::where('member_id', $member->id)->onlyTrashed()->count();
        $activeVouchers = Voucher::where('member_id', $member->id)->withoutTrashed()->count();
        $children = [];

        if ($trashedVouchers > 0) {
            $children[] = ['type' => 'voucher', 'count' => $trashedVouchers, 'label' => $trashedVouchers.' bon(s) supprimé(s)'];
        }
        if ($activeVouchers > 0) {
            $children[] = ['type' => 'voucher', 'count' => $activeVouchers, 'label' => $activeVouchers.' bon(s) actif(s)'];
        }

        return $children;
    }

    protected function getChildrenForProduct(Product $product): array
    {
        $trashedTicketItems = TicketItem::where('product_id', $product->id)->onlyTrashed()->count();
        $activeTicketItems = TicketItem::where('product_id', $product->id)->withoutTrashed()->count();
        $trashedVouchers = Voucher::where('product_id', $product->id)->onlyTrashed()->count();
        $activeVouchers = Voucher::where('product_id', $product->id)->withoutTrashed()->count();
        $children = [];

        if ($trashedTicketItems > 0) {
            $children[] = ['type' => 'ticket_item', 'count' => $trashedTicketItems, 'label' => $trashedTicketItems.' article(s) de ticket supprimé(s)'];
        }
        if ($activeTicketItems > 0) {
            $children[] = ['type' => 'ticket_item', 'count' => $activeTicketItems, 'label' => $activeTicketItems.' article(s) de ticket actif(s)'];
        }
        if ($trashedVouchers > 0) {
            $children[] = ['type' => 'voucher', 'count' => $trashedVouchers, 'label' => $trashedVouchers.' bon(s) supprimé(s)'];
        }
        if ($activeVouchers > 0) {
            $children[] = ['type' => 'voucher', 'count' => $activeVouchers, 'label' => $activeVouchers.' bon(s) actif(s)'];
        }

        return $children;
    }

    protected function getChildrenForVoucher(Voucher $voucher): array
    {
        $trashedTicketItems = TicketItem::where('voucher_id', $voucher->id)->onlyTrashed()->count();
        $activeTicketItems = TicketItem::where('voucher_id', $voucher->id)->withoutTrashed()->count();
        $children = [];

        if ($trashedTicketItems > 0) {
            $children[] = ['type' => 'ticket_item', 'count' => $trashedTicketItems, 'label' => $trashedTicketItems.' article(s) de ticket supprimé(s)'];
        }
        if ($activeTicketItems > 0) {
            $children[] = ['type' => 'ticket_item', 'count' => $activeTicketItems, 'label' => $activeTicketItems.' article(s) de ticket actif(s)'];
        }

        return $children;
    }
}
