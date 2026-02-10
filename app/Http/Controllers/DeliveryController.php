<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    public function store(Request $request)
    {
        $id = DB::table('supplier_deliveries')->insertGetId([
            'supplier_id' => $request->supplier_id,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        

        return response()->json([
            'id' => $id
        ]);
    }
    public function addItem(Request $request, $id)
    {
        DB::table('supplier_delivery_items')->insert([
            'delivery_id' => $id,
            'product_id' => $request->product_id,
            'qty_declared' => $request->qty_declared,
            'supplier_price' => $request->supplier_price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

}

