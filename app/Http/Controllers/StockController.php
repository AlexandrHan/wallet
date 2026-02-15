<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
public function index()
{
    // 1) Агрегація прийнятого товару по поставках (щоб не множилось)
    $deliveriesAgg = DB::table('supplier_delivery_items as items')
        ->join('supplier_deliveries as deliveries', 'deliveries.id', '=', 'items.delivery_id')
        ->where('deliveries.status', 'accepted')
        ->groupBy('items.product_id')
        ->select(
            'items.product_id',
            DB::raw('COALESCE(SUM(items.qty_accepted),0) as received'),
            DB::raw('COALESCE(MAX(items.supplier_price),0) as supplier_price')
        );

    // 2) Агрегація продажів по товару (теж окремо)
    $salesAgg = DB::table('sales')
        ->groupBy('product_id')
        ->select(
            'product_id',
            DB::raw('COALESCE(SUM(qty),0) as sold'),
            DB::raw('COALESCE(SUM(qty * supplier_price),0) as sold_cost')
        );

    // 3) Збираємо список складу з products + дві агрегації
    $stock = DB::table('products')
        ->leftJoinSub($deliveriesAgg, 'd', function ($join) {
            $join->on('d.product_id', '=', 'products.id');
        })
        ->leftJoinSub($salesAgg, 's', function ($join) {
            $join->on('s.product_id', '=', 'products.id');
        })
        ->select(
            'products.id',
            'products.name',
            DB::raw('COALESCE(d.received,0) as received'),
            DB::raw('COALESCE(s.sold,0) as sold'),
            DB::raw('(COALESCE(d.received,0) - COALESCE(s.sold,0)) as qty_on_stock'),
            DB::raw('(COALESCE(d.received,0) - COALESCE(s.sold,0)) * COALESCE(d.supplier_price,0) as stock_value'),
            DB::raw('COALESCE(d.supplier_price,0) as supplier_price')
        )
        ->orderBy('products.name')
        ->get();

// 1) борг по продажах (в $)
$salesDebt = (float) DB::table('sales')
    ->selectRaw('COALESCE(SUM(qty * supplier_price), 0) as debt')
    ->value('debt');

// 2) скільки реально "отримано" менеджером (в $)
$paid = (float) DB::table('supplier_cash_transfers')
    ->where('is_received', 1)
    ->where('currency', 'USD')
    ->sum('amount');

// 3) актуальний борг
$supplierDebt = max(0, $salesDebt - $paid);




return response()->json([
    'stock' => $stock,
    'supplier_debt' => round($supplierDebt, 2),
]);

}

}
