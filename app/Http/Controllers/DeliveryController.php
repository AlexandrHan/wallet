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
        $delivery = DB::table('supplier_deliveries')
            ->where('id', $id)
            ->first();

        if (!$delivery) {
            return response()->json([
                'error' => 'Delivery not found'
            ], 404);
        }

        if ($delivery->status !== 'draft') {
            return response()->json([
                'error' => 'Delivery already shipped'
            ], 422);
        }

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
    public function ship($id)
    {
        DB::table('supplier_deliveries')
            ->where('id', $id)
            ->update([
                'status' => 'shipped',
                'updated_at' => now()
            ]);

        return response()->json(['ok' => true]);
    }

    public function get($id)
{
    $delivery = DB::table('supplier_deliveries')->where('id', $id)->first();

    if (!$delivery) {
        return response()->json(['error' => 'Delivery not found'], 404);
    }

    return response()->json($delivery);
}

    public function accept(Request $request, $id)
    {
        $u = $request->user();

        // тільки owner + accountant
        if (!$u || !in_array($u->role, ['owner', 'accountant'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $delivery = DB::table('supplier_deliveries')->where('id', $id)->first();

        if (!$delivery) {
            return response()->json(['error' => 'Delivery not found'], 404);
        }

        if ($delivery->status !== 'shipped') {
            return response()->json(['error' => 'Delivery must be SHIPPED'], 422);
        }

        // ставимо qty_accepted = qty_declared (поки без розбіжностей)
        DB::table('supplier_delivery_items')
            ->where('delivery_id', $id)
            ->whereNull('qty_accepted')
            ->update([
                'qty_accepted' => DB::raw('qty_declared'),
                'updated_at' => now(),
            ]);

        DB::table('supplier_deliveries')
            ->where('id', $id)
            ->update([
                'status' => 'accepted',
                'accepted_by' => $u->id,
                'accepted_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['ok' => true]);
    }

    public function indexApi()
{
    $rows = DB::table('supplier_deliveries')
        ->orderByDesc('id')
        ->get();

    return response()->json($rows);
}





}

