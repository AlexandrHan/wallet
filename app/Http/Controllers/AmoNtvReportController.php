<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AmoNtvReportController extends Controller
{
    private const PIPELINE_ID = 4071382;

    private const STAGE_LABELS = [
        38556547 => 'Частично оплатил',
        69586234 => 'Комплектація',
        38556550 => 'Очікування доставки',
        69593822 => 'заплановане будівництво',
        69593826 => 'Монтаж сонячних панелей',
        69593830 => 'Електрична частина',
        69593834 => 'Здача проекту замовнику',
        41906428 => 'Збільшення потужності',
        41906431 => 'Оформлення ЗТ',
        49782427 => 'Остаточна оплата',
    ];

    private const EQUIPMENT_FIELDS = [
        1202241 => 'Інвертор',
        1200253 => 'Панелі',
        1200259 => 'АКБ',
        1216078 => 'BMS',
        1204253 => 'Кріплення',
        1204257 => 'Захист',
        1204259 => 'Моніторинг',
        1204265 => 'Кабель Solar',
        1204267 => 'Силовий кабель',
        1204269 => 'МС4',
    ];

    public function index(Request $request)
    {
        $this->authorizeRole($request);

        return view('sales.amo-ntv-report', $this->reportPayload($request));
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorizeRole($request);

        return response()->json($this->reportPayload($request));
    }

    private function authorizeRole(Request $request): void
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['owner', 'ntv', 'manager'], true)) {
            abort(403);
        }
    }

    private function reportPayload(Request $request): array
    {
        $stageIds = $this->stageIds();
        $managerId = (int) $request->query('manager', 0);

        $baseQuery = DB::table('amo_complectation_projects')
            ->where('pipeline_id', self::PIPELINE_ID)
            ->whereIn('status_id', $stageIds);

        $managers = (clone $baseQuery)
            ->whereNotNull('responsible_user_id')
            ->select('responsible_user_id', 'responsible_name')
            ->groupBy('responsible_user_id', 'responsible_name')
            ->orderBy('responsible_name')
            ->get()
            ->map(fn ($manager) => [
                'id' => (int) $manager->responsible_user_id,
                'name' => $this->managerLabel((int) $manager->responsible_user_id, (string) $manager->responsible_name),
            ])
            ->values()
            ->all();

        $query = (clone $baseQuery);
        if ($managerId > 0) {
            $query->where('responsible_user_id', $managerId);
        }

        $stageOrder = array_flip($stageIds);
        $rows = $query
            ->select([
                'id',
                'amo_deal_id',
                'client_name',
                'deal_name',
                'total_amount',
                'currency',
                'responsible_user_id',
                'responsible_name',
                'status_id',
                'raw_payload',
                'updated_at',
            ])
            ->get()
            ->map(fn ($row) => $this->formatRow($row))
            ->sort(function (array $a, array $b) use ($stageOrder) {
                $stageCompare = ($stageOrder[$a['status_id']] ?? 999) <=> ($stageOrder[$b['status_id']] ?? 999);
                return $stageCompare !== 0 ? $stageCompare : strcmp($a['client_name'], $b['client_name']);
            })
            ->values()
            ->all();

        return [
            'selected_manager' => $managerId,
            'stage_ids' => $stageIds,
            'stage_labels' => self::STAGE_LABELS,
            'managers' => $managers,
            'rows' => $rows,
            'totals' => [
                'projects' => count($rows),
                'amount' => round(array_sum(array_column($rows, 'total_amount')), 2),
                'advance' => round(array_sum(array_column($rows, 'advance_amount')), 2),
            ],
        ];
    }

    private function formatRow(object $row): array
    {
        $payload = json_decode((string) ($row->raw_payload ?? ''), true);
        $payload = is_array($payload) ? $payload : [];

        return [
            'id' => (int) $row->id,
            'amo_deal_id' => (int) $row->amo_deal_id,
            'client_name' => (string) ($row->client_name ?: $this->payloadValue($payload, 'name')),
            'deal_name' => (string) ($row->deal_name ?: $this->payloadValue($payload, 'name')),
            'manager_id' => $row->responsible_user_id ? (int) $row->responsible_user_id : null,
            'manager_name' => $this->managerLabel((int) ($row->responsible_user_id ?? 0), (string) ($row->responsible_name ?? '')),
            'status_id' => (int) $row->status_id,
            'stage' => self::STAGE_LABELS[(int) $row->status_id] ?? ('Етап #' . $row->status_id),
            'total_amount' => round((float) ($row->total_amount ?? 0), 2),
            'currency' => strtoupper(trim((string) ($row->currency ?: 'USD'))),
            'advance_amount' => $this->extractNumericField($payload, 107031, 'Предоплата, $'),
            'equipment' => $this->extractEquipment($payload),
            'updated_at' => $row->updated_at,
        ];
    }

    private function extractEquipment(array $payload): array
    {
        $equipment = [];

        foreach ((array) ($payload['custom_fields_values'] ?? []) as $field) {
            $fieldId = (int) ($field['field_id'] ?? 0);
            if (!isset(self::EQUIPMENT_FIELDS[$fieldId])) {
                continue;
            }

            $value = $this->fieldValuesToString((array) ($field['values'] ?? []));
            if ($this->isUsefulValue($value)) {
                $equipment[] = [
                    'label' => self::EQUIPMENT_FIELDS[$fieldId],
                    'value' => $value,
                ];
            }
        }

        return $equipment;
    }

    private function extractNumericField(array $payload, int $fieldId, string $fieldName): float
    {
        foreach ((array) ($payload['custom_fields_values'] ?? []) as $field) {
            if ((int) ($field['field_id'] ?? 0) !== $fieldId && (string) ($field['field_name'] ?? '') !== $fieldName) {
                continue;
            }

            $value = $this->fieldValuesToString((array) ($field['values'] ?? []));
            $number = (float) preg_replace('/[^\d.]/', '', $value);

            return round(max(0, $number), 2);
        }

        return 0.0;
    }

    private function fieldValuesToString(array $values): string
    {
        $result = [];
        foreach ($values as $valueRow) {
            $value = is_array($valueRow) ? ($valueRow['value'] ?? '') : $valueRow;
            $value = trim((string) $value);
            if ($value !== '') {
                $result[] = $value;
            }
        }

        return implode(', ', array_unique($result));
    }

    private function isUsefulValue(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return !in_array(mb_strtolower($value), ['-', '—', '.', 'ні', 'нет', 'no', 'відсутні', 'відсутня'], true);
    }

    private function managerLabel(int $managerId, string $fallback): string
    {
        $labels = [
            12498694 => 'Наташа',
            9296522 => 'Володимир Вдовенко',
            9888062 => 'Інга',
        ];

        return $labels[$managerId] ?? ($fallback !== '' ? $fallback : ('AMO #' . $managerId));
    }

    private function stageIds(): array
    {
        $ids = array_filter(
            (array) config('services.amocrm.ntv_report_stage_ids', array_keys(self::STAGE_LABELS)),
            fn ($id) => is_numeric($id) && (int) $id > 0
        );

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function payloadValue(array $payload, string $key): string
    {
        return trim((string) ($payload[$key] ?? ''));
    }
}
