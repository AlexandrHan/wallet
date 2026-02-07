<?php
namespace App\Http\Controllers;

use App\Models\Reclamation;
use App\Models\ReclamationStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\ReclamationLog;


class ReclamationController extends Controller
{
    private array $stepKeys = [
        'reported',
        'dismantled',            // демонтували (дата + що зробили)
        'where_left',            // де залишили (склад/відправили)
        'shipped_to_service',    // відправили НП (ТТН)
        'service_received',      // сервіс отримав (дата)
        'repaired_shipped_back', // відремонтували і відправили (ТТН)
        'installed',             // встановили (фото + обов’язк. коментар)
        'loaner_return',         // підмінний повернули (склад/постачальнику)
        'closed',                // завершили (дата)
    ];

    public function index()
    {
        $items = Reclamation::with('steps')
            ->whereNotNull('last_name')
            ->where('last_name', '!=', '')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('reclamations.index', compact('items'));
    }

    public function create()
    {
        return view('reclamations.create');
    }

    public function store(Request $r)
    {
        $data = $r->validate([
        'reported_at'    => ['nullable', 'date'],
        'problem'        => ['nullable', 'string'],
        'last_name'      => ['required', 'string', 'max:120'],
        'city'           => ['required', 'string', 'max:120'],
        'phone'          => ['required', 'string', 'max:40'],
        'serial_number'  => ['required', 'string', 'max:120'],
        'has_loaner'     => ['required', 'in:0,1'],
        'loaner_ordered' => ['nullable', 'in:0,1'],
        'close' => ['nullable','in:0,1'],
        ]);


        $nextId = (int)(Reclamation::max('id') ?? 0) + 1;
        $code = 'R-' . str_pad((string)$nextId, 5, '0', STR_PAD_LEFT);

        $rec = Reclamation::create([
            'code' => $code,
            'reported_at' => $data['reported_at'],
            'last_name' => $data['last_name'],
            'city' => $data['city'],
            'phone' => $data['phone'],
            'has_loaner' => $data['has_loaner'] === '1',
            'loaner_ordered' => ($data['has_loaner'] === '0') ? (($data['loaner_ordered'] ?? '0') === '1') : false,
            'serial_number' => $data['serial_number'],
            'status' => 'open',
            'created_by' => Auth::id(),
        ]);

        // створюємо всі етапи наперед (порожні)
        foreach ($this->stepKeys as $k) {
            ReclamationStep::create([
                'reclamation_id' => $rec->id,
                'step_key' => $k,
            ]);
        }

        return redirect()->route('reclamations.show', $rec->id);
    }

    public function show(Reclamation $reclamation)
    {
        $reclamation->load('steps');
        return view('reclamations.show', compact('reclamation'));
    }

    public function saveStep(Request $r, Reclamation $reclamation, string $stepKey)
    {
        $reclamation->load('steps');

        $step = $reclamation->steps->firstWhere('step_key', $stepKey);
        abort_if(!$step, 404);



        // ===== reported: редагування даних клієнта / звернення =====
        if ($stepKey === 'reported') {

            $data = $r->validate([
                'reported_at'    => ['nullable', 'date'],
                'problem'        => ['nullable', 'string'],
                'last_name'      => ['required', 'string', 'max:120'],
                'city'           => ['required', 'string', 'max:120'],
                'phone'          => ['required', 'string', 'max:40'],
                'serial_number'  => ['required', 'string', 'max:120'],
                'has_loaner'     => ['required', 'in:0,1'],
                'loaner_ordered' => ['nullable', 'in:0,1'],
            ]);

            $reclamation->reported_at   = $data['reported_at'] ?? $reclamation->reported_at;
            $reclamation->last_name     = $data['last_name'];
            $reclamation->city          = $data['city'];
            $reclamation->phone         = $data['phone'];
            $reclamation->serial_number = $data['serial_number'];
            $reclamation->problem       = $data['problem'] ?? null;

            $reclamation->has_loaner = ($data['has_loaner'] === '1');
            $reclamation->loaner_ordered = ($data['has_loaner'] === '0')
                ? (($data['loaner_ordered'] ?? '0') === '1')
                : false;

            $reclamation->save();

            $step->done_date = now()->toDateString();
            $step->note = 'Дані оновлено';
            $step->save();
            ReclamationLog::create([
                'reclamation_id' => $reclamation->id,
                'user_id' => Auth::id(),
                'step_key' => 'reported',
                'action' => 'step_update',
                'payload' => [
                    'reported_at' => $reclamation->reported_at?->format('Y-m-d'),
                    'last_name' => $reclamation->last_name,
                    'city' => $reclamation->city,
                    'phone' => $reclamation->phone,
                    'serial_number' => $reclamation->serial_number,
                    'problem' => $reclamation->problem,
                    'has_loaner' => (bool)$reclamation->has_loaner,
                    'loaner_ordered' => (bool)$reclamation->loaner_ordered,
                ],
            ]);


            return response()->json(['ok' => true]);
        }



        $data = $r->validate([
            'done_date' => ['nullable','date'],
            'note' => ['nullable','string'],
            'ttn' => ['nullable','string','max:80'],
            'where_left' => ['nullable','in:warehouse,service'], // тільки для where_left
            'loaner_return_to' => ['nullable','in:warehouse,supplier'], // тільки для loaner_return
            'close' => ['nullable','in:0,1'],
        ]);

        if ($stepKey === 'closed' && isset($data['close'])) {
    if ($data['close'] === '1') {
        $step->done_date = $step->done_date ?? now()->toDateString();
        $step->note = 'Закрито';
        $step->save();

        $reclamation->status = 'done';
        $reclamation->save();
    } else {
        $step->done_date = null;
        $step->note = null;
        $step->ttn = null;
        $step->save();

        $reclamation->status = 'open';
        $reclamation->save();
    }

    ReclamationLog::create([
        'reclamation_id' => $reclamation->id,
        'user_id' => Auth::id(),
        'step_key' => 'closed',
        'action' => 'close_toggle',
        'payload' => ['close' => $data['close'], 'done_date' => $step->done_date],
    ]);

    return response()->json(['ok'=>true]);
}


        // rules специфічні:
        if ($stepKey === 'installed') {
            // обов’язковий коментар на "встановили"
            if (!isset($data['note']) || trim($data['note']) === '') {
                return response()->json(['ok'=>false,'message'=>'Коментар обовʼязковий на етапі "Встановили"'], 422);
            }
        }

        // запишемо note з варіантами (де залишили / куди повернули) простим текстом
        if ($stepKey === 'where_left' && isset($data['where_left'])) {
            $data['note'] = ($data['where_left'] === 'warehouse')
                ? 'Залишили на складі'
                : 'Відправили на ремонт';
        }

        if ($stepKey === 'loaner_return' && isset($data['loaner_return_to'])) {
            $data['note'] = ($data['loaner_return_to'] === 'warehouse')
                ? 'Підмінний повернули на склад'
                : 'Підмінний повернули постачальнику';
        }
        // якщо в етапі з’явилися будь-які дані, а дати нема — ставимо сьогодні
        $hasAny = false;

        if (isset($data['note']) && trim((string)$data['note']) !== '') $hasAny = true;
        if (isset($data['ttn'])  && trim((string)$data['ttn'])  !== '') $hasAny = true;

        if ($hasAny && !$step->done_date) {
            $data['done_date'] = now()->toDateString();
        }


        $step->fill([
            'done_date' => $data['done_date'] ?? $step->done_date,
            'note' => $data['note'] ?? $step->note,
            'ttn' => $data['ttn'] ?? $step->ttn,
        ])->save();


        ReclamationLog::create([
            'reclamation_id' => $reclamation->id,
            'user_id' => Auth::id(),
            'step_key' => $stepKey,
            'action' => 'step_update',
            'payload' => [
                'done_date' => $step->done_date,
                'ttn' => $step->ttn,
                'note' => $step->note,
            ],
        ]);


        // якщо закрили
        if ($stepKey === 'closed' && $step->done_date) {
            $reclamation->status = 'done';
            $reclamation->save();
        }

        return response()->json(['ok'=>true]);
    }

    public function upload(Request $r, Reclamation $reclamation)
    {
        $r->validate([
            'step_key' => ['required','string'],
            'file' => ['required','file','max:10240'], // 10MB
        ]);

        $reclamation->load('steps');
        $step = $reclamation->steps->firstWhere('step_key', $r->string('step_key')->toString());
        abort_if(!$step, 404);

        $path = $r->file('file')->store("reclamations/{$reclamation->id}", 'public');

        $files = $step->files ?? [];
        $files[] = $path;

        $step->files = $files;
        if (!$step->done_date) {
            $step->done_date = now()->toDateString();
        }

        $step->save();

        

        return response()->json([
            'ok' => true,
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }
    public function new()
{
    $nextId = (int)(Reclamation::max('id') ?? 0) + 1;
    $code = 'R-' . str_pad((string)$nextId, 5, '0', STR_PAD_LEFT);

    $rec = Reclamation::create([
        'code' => $code,
        'status' => 'open',
        'created_by' => auth()->id(),
    ]);

    // Створюємо всі етапи одразу (порожні)
    $keys = [
        'reported',              // базові дані (дата/ПІБ/місто/телефон/підмінний)
        'photos',                // фото/файли рекламації
        'serial',                // серійник
        'dismantled',            // демонтували (дата + опис)
        'where_left',            // де залишили (склад/відправили)
        'shipped_to_service',    // відправили НП (ТТН)
        'service_received',      // сервіс отримав (дата)
        'repaired_shipped_back', // відремонтували і відправили (ТТН)
        'installed',             // встановили (фото + коментар обовʼязковий)
        'loaner_return',         // підмінний (куди)
        'closed',                // завершили (дата)
    ];

    foreach ($keys as $k) {
        \App\Models\ReclamationStep::create([
            'reclamation_id' => $rec->id,
            'step_key' => $k,
        ]);
    }

    return redirect()->route('reclamations.show', $rec->id);
}

public function history(Reclamation $reclamation)
{
    $logs = \App\Models\ReclamationLog::with('user')
        ->where('reclamation_id', $reclamation->id)
        ->orderByDesc('id')
        ->limit(200)
        ->get();


    // Повертаємо компактний html, щоб легко вставити в show без шаблонів
    $html = view('reclamations.partials.history', compact('logs'))->render();

    return response()->json(['html' => $html]);
}


}
