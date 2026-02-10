<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function index()
    {
        $stock = DB::table('products')
            ->leftJoin('supplier_delivery_items as items','items.product_id','=','products.id')
            ->leftJoin('supplier_deliveries as deliveries','deliveries.id','=','items.delivery_id')
            ->leftJoin('sales','sales.product_id','=','products.id')
            ->where('deliveries.status','accepted')
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(items.qty_accepted) as received'),
                DB::raw('COALESCE(SUM(sales.qty),0) as sold'),
                DB::raw('SUM(items.qty_accepted) - COALESCE(SUM(sales.qty),0) as qty_on_stock'),
                DB::raw('(SUM(items.qty_accepted) - COALESCE(SUM(sales.qty),0)) * MAX(items.supplier_price) as stock_value')
            )
            ->groupBy('products.id','products.name')
            ->get();

        $supplierDebt = DB::table('sales')
            ->select(DB::raw('SUM(qty * supplier_price) as debt'))
            ->value('debt');

        return response()->json([
            'stock' => $stock,
            'supplier_debt' => $supplierDebt ?? 0
        ]);
    }

}
