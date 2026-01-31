<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EntryReceiptController extends Controller
{
    public function store(Request $request, Entry $entry)
    {
        $request->validate([
            'file' => ['required', 'image', 'max:6144'], // 6MB
        ]);

        $file = $request->file('file');

        // збережемо в public, щоб потім легко показувати через /storage/...
        $path = $file->store('receipts/' . now()->format('Y/m'), 'public');

        // видалити старий файл (якщо перезаливають)
        if ($entry->receipt_path) {
            Storage::disk('public')->delete($entry->receipt_path);
        }

        $entry->receipt_path = $path;
        $entry->save();

        return response()->json([
            'ok' => true,
            'receipt_path' => $entry->receipt_path,
            'receipt_url' => Storage::disk('public')->url($entry->receipt_path),
        ]);
    }
}
