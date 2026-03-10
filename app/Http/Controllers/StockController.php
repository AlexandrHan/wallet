<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockController extends Controller
{
    public function index(Request $request)
    {
        // ====== 0) Діапазон для звітного "за період" (не для загального боргу) ======
        $from = $request->query('from');
        $to   = $request->query('to');

        if (!$from || !$to) {
            $now  = Carbon::now();
            $from = $now->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
            $to   = $now->copy()->startOfWeek(Carbon::MONDAY)->addDays(6)->toDateString();
        }

        // дата продажу: якщо sold_at пустий — берем created_at
        $saleDate = DB::raw("date(COALESCE(sold_at, created_at))");

        // ====== 1) СКЛАД (як у тебе було по прийнятим поставкам) ======
        $purchases = DB::table('supplier_delivery_items as i')
            ->join('supplier_deliveries as d', 'd.id', '=', 'i.delivery_id')
            ->where('d.status', 'accepted')
            ->groupBy('i.product_id')
            ->select(
                'i.product_id',
                DB::raw('SUM(i.qty_accepted) as received'),
                DB::raw('SUM(i.qty_accepted * i.supplier_price) as received_cost'),
                DB::raw('ROUND(SUM(i.qty_accepted * i.supplier_price) / NULLIF(SUM(i.qty_accepted),0), 2) as avg_purchase_price')
            );

        $salesAll = DB::table('sales')
            ->groupBy('product_id')
            ->select(
                'product_id',
                DB::raw('SUM(qty) as sold'),
                DB::raw('SUM(qty * supplier_price) as sold_cost')
            );

        $rows = DB::table('products as p')
            ->leftJoin('product_categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoinSub($purchases, 'pur', 'pur.product_id', '=', 'p.id')
            ->leftJoinSub($salesAll, 's', 's.product_id', '=', 'p.id')
            ->whereNotNull('pur.product_id')
            ->select(
                'p.id as product_id',
                'p.name',
                'p.category_id',
                DB::raw("COALESCE(c.name, 'Інше') as category_name"),

                DB::raw('COALESCE(pur.received,0) as received'),
                DB::raw('COALESCE(s.sold,0) as sold'),
                DB::raw('(COALESCE(pur.received,0) - COALESCE(s.sold,0)) as qty_on_stock'),

                DB::raw("
                    CASE
                    WHEN (COALESCE(pur.received,0) - COALESCE(s.sold,0)) > 0
                    THEN ROUND(
                        (COALESCE(pur.received_cost,0) - COALESCE(s.sold_cost,0))
                        / NULLIF((COALESCE(pur.received,0) - COALESCE(s.sold,0)), 0)
                    , 2)
                    ELSE ROUND(COALESCE(pur.avg_purchase_price,0), 2)
                    END as supplier_price
                "),

                DB::raw('ROUND((COALESCE(pur.received_cost,0) - COALESCE(s.sold_cost,0)), 2) as stock_value')
            )
            ->orderByRaw("COALESCE(c.name, 'Інше') asc")
            ->orderBy('p.name')
            ->get();


        // ====== 2) БОРГ ЗА ВЕСЬ ЧАС (АКТУАЛЬНИЙ) ======
        $soldAllTime = (float) DB::table('sales')
            ->where($saleDate, '<=', Carbon::today()->toDateString())
            ->sum(DB::raw('qty * supplier_price'));

        $paidAllTime = (float) DB::table('supplier_cash_transfers')
            ->where('is_received', 1)
            ->where('currency', 'USD')
            ->sum('amount');

        $debtTotal = max(0, $soldAllTime - $paidAllTime);

        // ====== 3) БОРГ ЗА ПЕРІОД (для тижневого звіту) ======
        $debtPeriod = (float) DB::table('sales')
            ->whereBetween($saleDate, [$from, $to])
            ->sum(DB::raw('qty * supplier_price'));

        return response()->json([
            'from' => $from,
            'to' => $to,

            // ✅ тепер supplier_debt = загальний борг (те що ти хочеш бачити "на цей день")
            'supplier_debt' => round($debtTotal, 2),

            // ✅ окремо лишаємо борг за період для звіту (якщо треба показувати)
            'supplier_debt_period' => round($debtPeriod, 2),

            'paid_total' => round($paidAllTime, 2),
            'sold_total' => round($soldAllTime, 2),

            'stock' => $rows,

            
        ]);
    }

    public function salesReports(Request $request)
    {
        $u = $request->user();
        if (!$u || !in_array($u->role, ['owner', 'sunfix_manager'], true)) {
            abort(403);
        }

        $saleDateExpr = "date(COALESCE(s.sold_at, s.created_at))";

        $reports = DB::table('sales as s')
            ->selectRaw("{$saleDateExpr} as report_date")
            ->selectRaw('MAX(s.created_at) as report_created_at')
            ->selectRaw('ROUND(SUM(s.qty * s.supplier_price), 2) as report_total')
            ->groupBy(DB::raw($saleDateExpr))
            ->orderByDesc('report_date')
            ->get()
            ->map(function ($row) {
                return [
                    'report_date' => (string) $row->report_date,
                    'report_created_at' => $row->report_created_at
                        ? Carbon::parse($row->report_created_at)->format('d.m.Y H:i')
                        : null,
                    'report_total' => (float) $row->report_total,
                ];
            })
            ->values();

        $rows = DB::table('sales as s')
            ->join('products as p', 'p.id', '=', 's.product_id')
            ->selectRaw("
                {$saleDateExpr} as report_date,
                p.id as product_id,
                p.name as product_name,
                SUM(s.qty) as qty,
                ROUND(SUM(s.qty * s.supplier_price), 2) as total,
                ROUND(SUM(s.qty * s.supplier_price) / NULLIF(SUM(s.qty),0), 2) as unit_price
            ")
            ->groupBy(DB::raw($saleDateExpr), 'p.id', 'p.name')
            ->orderByDesc('report_date')
            ->orderBy('p.name')
            ->get();

        $itemsByDate = [];
        foreach ($rows as $row) {
            $dateKey = (string) $row->report_date;
            if (!isset($itemsByDate[$dateKey])) {
                $itemsByDate[$dateKey] = [];
            }
            $itemsByDate[$dateKey][] = [
                'product_id' => (int) $row->product_id,
                'product_name' => (string) $row->product_name,
                'qty' => (int) $row->qty,
                'unit_price' => (float) $row->unit_price,
                'total' => (float) $row->total,
            ];
        }

        return view('stock.sales-reports', [
            'reports' => $reports,
            'itemsByDate' => $itemsByDate,
        ]);
    }
}
