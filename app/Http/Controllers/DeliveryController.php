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

    $itemsPayload = $request->input('items');

    // якщо бухгалтер передав фактичні значення
    if (is_array($itemsPayload) && count($itemsPayload) > 0) {

        foreach ($itemsPayload as $row) {
            $itemId = isset($row['item_id']) ? (int)$row['item_id'] : 0;
            $qty    = isset($row['qty_accepted']) ? (float)$row['qty_accepted'] : null;

            if ($itemId <= 0 || $qty === null) {
                continue;
            }

            if ($qty < 0) $qty = 0;

            DB::table('supplier_delivery_items')
                ->where('delivery_id', $id)
                ->where('id', $itemId)
                ->update([
                    'qty_accepted' => $qty,
                    'updated_at' => now(),
                ]);
        }

    } else {
        // fallback: “все співпало”
        DB::table('supplier_delivery_items')
            ->where('delivery_id', $id)
            ->update([
                'qty_accepted' => DB::raw('qty_declared'),
                'updated_at' => now(),
            ]);
    }

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


public function items($id)
{
    $items = DB::table('supplier_delivery_items as items')
        ->join('products', 'products.id', '=', 'items.product_id')
        ->where('items.delivery_id', $id)
        ->select([
            'items.id as item_id',
            'products.name',
            'items.qty_declared',
            'items.qty_accepted',
            'items.supplier_price',
        ])
        ->orderBy('items.id')
        ->get();

    return response()->json($items);
}



public function destroy($id)
{
    $delivery = Delivery::findOrFail($id);

    // 🔴 ГОЛОВНЕ ПРАВИЛО
    if ($delivery->status !== 'draft') {
        return response()->json([
            'error' => 'Можна видалити тільки чернетку'
        ], 403);
    }

    $delivery->delete();

    return response()->json([
        'success' => true
    ]);
}

public function deleteItem($itemId)
{
    $item = DB::table('supplier_delivery_items')
        ->where('id', $itemId)
        ->first();

    if (!$item) {
        return response()->json(['error' => 'Item not found'], 404);
    }

    $delivery = DB::table('supplier_deliveries')
        ->where('id', $item->delivery_id)
        ->first();

    if (!$delivery || $delivery->status !== 'draft') {
        return response()->json([
            'error' => 'Можна видаляти тільки у чернетці'
        ], 403);
    }

    DB::table('supplier_delivery_items')
        ->where('id', $itemId)
        ->delete();

    return response()->json(['ok' => true]);
}


}

