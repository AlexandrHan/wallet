<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FemContainer;
use App\Models\FemContainerPayment;
use Carbon\Carbon;

class FemDebtController extends Controller
{
    private function role(Request $r): ?string
    {
        return $r->user()?->role;
    }

    private function isManager(Request $r): bool
    {
        return $this->role($r) === 'sunfix_manager';
    }

    private function canPay(Request $r): bool
    {
        return in_array($this->role($r), ['owner', 'accountant'], true);
    }

    // GET /api/fem/containers
    public function index(Request $request)
    {
        $rows = FemContainer::with('payments')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($rows);
    }

    // POST /api/fem/containers (manager only)
    public function storeContainer(Request $request)
    {
        if (!$this->isManager($request)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'name'   => ['required','string','min:3','max:255'],
            'amount' => ['required','numeric','min:0.01'], // ✅ НЕ ПУСТО
        ]);

        $c = FemContainer::create([
            'name' => $data['name'],
            'date' => Carbon::today()->toDateString(), // ✅ СЬОГОДНІ автоматом
            'amount' => $data['amount'],
            'created_by' => $request->user()->id,
        ]);

        return response()->json($c->load('payments'));
    }

    // PATCH /api/fem/containers/{id} (manager only)
    public function updateContainer(Request $request, $id)
    {
        if (!$this->isManager($request)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $c = FemContainer::findOrFail($id);
        $today = now()->toDateString();
        $containerDate = $c->date ? $c->date->toDateString() : null;

        if ($containerDate !== $today) {
            return response()->json(['error' => 'Можна редагувати тільки в день створення'], 403);
        }


        $data = $request->validate([
            'name'   => ['sometimes','required','string','min:3','max:255'],
            'amount' => ['sometimes','required','numeric','min:0.01'],
            // date НЕ даємо міняти взагалі, бо "сьогодні автоматом"
        ]);

        $c->update($data);

        return response()->json($c->load('payments'));
    }

    // POST /api/fem/containers/{id}/payments (owner/accountant only)
    public function storePayment(Request $request, $id)
    {
        if (!$this->canPay($request)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $c = FemContainer::findOrFail($id);

        $data = $request->validate([
            'paid_at' => ['nullable','date'],
            'amount' => ['required','numeric','min:0.01'],
        ]);

        FemContainerPayment::create([
            'fem_container_id' => $c->id,
            'paid_at' => $data['paid_at'] ?? now()->toDateString(),
            'amount' => $data['amount'],
            'created_by' => $request->user()->id,
        ]);

        return response()->json($c->fresh()->load('payments'));
    }


    public function receivePayment(Request $request, $paymentId)
    {
        if (!$this->isManager($request)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $p = FemContainerPayment::findOrFail($paymentId);

        if ((int)$p->is_received === 1) {
            return response()->json(['ok' => true, 'already' => true]);
        }

        $p->update([
            'is_received' => 1,
            'received_by' => $request->user()->id,
            'received_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }




}
