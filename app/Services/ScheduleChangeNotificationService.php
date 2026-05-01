<?php

namespace App\Services;

use App\Models\SalesProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ScheduleChangeNotificationService
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function collectOldSchedule(string $assignmentField, array $assignmentValues, string $today): array
    {
        $assignmentValues = array_values(array_unique(array_filter(array_map(
            fn ($value) => trim((string) $value),
            $assignmentValues
        ))));

        if (empty($assignmentValues) || !Schema::hasTable('project_schedule_entries')) {
            return [];
        }

        $rows = DB::table('project_schedule_entries')
            ->where('assignment_field', $assignmentField)
            ->whereIn('assignment_value', $assignmentValues)
            ->where('work_date', '>=', $today)
            ->whereIn('source', ['google_sheet', 'automation'])
            ->get(['project_id', 'assignment_field', 'assignment_value', 'work_date']);

        return $this->groupScheduleRows($rows);
    }

    public function collectNewSchedule(array $scheduleRows, string $assignmentField, string $today): array
    {
        $rows = collect($scheduleRows)
            ->filter(fn ($row) =>
                (string) ($row['assignment_field'] ?? '') === $assignmentField
                && (string) ($row['work_date'] ?? '') >= $today
            )
            ->map(fn ($row) => (object) [
                'project_id' => $row['project_id'] ?? null,
                'assignment_field' => $row['assignment_field'] ?? null,
                'assignment_value' => $row['assignment_value'] ?? null,
                'work_date' => $row['work_date'] ?? null,
            ]);

        return $this->groupScheduleRows($rows);
    }

    public function notifyChangedSchedules(string $assignmentField, array $oldSchedule, array $newSchedule): int
    {
        $sent = 0;
        $keys = array_values(array_unique(array_merge(array_keys($oldSchedule), array_keys($newSchedule))));
        $sentKeys = [];

        foreach ($keys as $key) {
            $old = $oldSchedule[$key] ?? null;
            if (!$old || empty($old['dates'])) {
                continue;
            }

            $new = $newSchedule[$key] ?? [
                'project_id' => $old['project_id'],
                'assignment_field' => $old['assignment_field'],
                'assignment_value' => $old['assignment_value'],
                'dates' => [],
            ];

            $oldDates = $this->sortedDates($old['dates']);
            $newDates = $this->sortedDates($new['dates'] ?? []);
            if ($oldDates === $newDates) {
                continue;
            }

            $dedupKey = implode('|', [
                $old['project_id'],
                $assignmentField,
                $old['assignment_value'],
                md5(implode(',', $oldDates)),
                md5(implode(',', $newDates)),
            ]);
            if (isset($sentKeys[$dedupKey])) {
                continue;
            }
            $sentKeys[$dedupKey] = true;

            $project = SalesProject::query()->find((int) $old['project_id']);
            if (!$project) {
                Log::warning('schedule_change_notification:project_not_found', [
                    'project_id' => $old['project_id'],
                    'assignment_field' => $assignmentField,
                    'assignment_value' => $old['assignment_value'],
                ]);
                continue;
            }

            $this->sendForProject($project, $assignmentField, (string) $old['assignment_value'], $oldDates, $newDates);
            $sent++;
        }

        return $sent;
    }

    private function groupScheduleRows(iterable $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $projectId = (int) ($row->project_id ?? 0);
            $assignmentField = trim((string) ($row->assignment_field ?? ''));
            $assignmentValue = trim((string) ($row->assignment_value ?? ''));
            $date = trim((string) ($row->work_date ?? ''));

            if ($projectId <= 0 || $assignmentField === '' || $assignmentValue === '' || $date === '') {
                continue;
            }

            $key = $this->scheduleKey($projectId, $assignmentField, $assignmentValue);
            $grouped[$key] ??= [
                'project_id' => $projectId,
                'assignment_field' => $assignmentField,
                'assignment_value' => $assignmentValue,
                'dates' => [],
            ];
            $grouped[$key]['dates'][] = $date;
        }

        foreach ($grouped as &$item) {
            $item['dates'] = $this->sortedDates($item['dates']);
        }
        unset($item);

        return $grouped;
    }

    private function scheduleKey(int $projectId, string $assignmentField, string $assignmentValue): string
    {
        return $projectId . '|' . $assignmentField . '|' . mb_strtolower(trim($assignmentValue));
    }

    private function sortedDates(array $dates): array
    {
        $dates = array_values(array_unique(array_filter(array_map(
            fn ($date) => trim((string) $date),
            $dates
        ))));
        sort($dates);

        return $dates;
    }

    private function sendForProject(SalesProject $project, string $assignmentField, string $assignmentValue, array $oldDates, array $newDates): void
    {
        $recipientIds = $this->resolveRecipients($project);
        if (empty($recipientIds)) {
            Log::warning('schedule_change_notification:no_recipients', [
                'project_id' => $project->id,
                'amo_deal_id' => $project->amo_deal_id,
                'assignment_field' => $assignmentField,
            ]);
            return;
        }

        $isElectric = $assignmentField === 'electrician';
        $title = $isElectric ? 'Зміна графіка електромонтажу' : 'Зміна графіка монтажних робіт';
        $roleLine = $isElectric ? "Електрик: {$assignmentValue}." : "Бригада: {$assignmentValue}.";
        $bodyType = $isElectric ? 'електромонтажних' : 'монтажних';

        $amoParts = array_filter([
            $project->amo_deal_name ?: null,
            $project->amo_deal_id ? 'AMO #' . $project->amo_deal_id : null,
        ]);
        $amoContext = $amoParts ? "\n\n" . implode(' · ', $amoParts) : '';

        $message = "Змінено графік {$bodyType} робіт по проєкту: {$project->client_name}.\n\n"
            . 'Було: ' . $this->formatDates($oldDates) . ".\n"
            . 'Стало: ' . $this->formatDates($newDates) . ".\n\n"
            . $roleLine . "\n\n"
            . 'Потрібно зателефонувати замовнику і попередити про зміну дати проведення монтажних робіт.'
            . $amoContext;

        foreach ($recipientIds as $userId) {
            $this->notifications->send((int) $userId, $title, $message, 'system', [
                'project_id' => (int) $project->id,
                'amo_deal_id' => $project->amo_deal_id ? (int) $project->amo_deal_id : null,
                'assignment_field' => $assignmentField,
                'assignment_value' => $assignmentValue,
                'old_dates' => implode(',', $oldDates),
                'new_dates' => implode(',', $newDates),
            ]);
        }

        Log::info('schedule_change_notification:sent', [
            'source' => 'google_sheets_sync',
            'project_id' => (int) $project->id,
            'client_name' => $project->client_name,
            'assignment_field' => $assignmentField,
            'assignment_value' => $assignmentValue,
            'old_dates' => $oldDates,
            'new_dates' => $newDates,
            'recipients' => $recipientIds,
        ]);
    }

    private function resolveRecipients(SalesProject $project): array
    {
        $ids = [];
        $managerId = $this->resolveLeadManagerId($project);
        if ($managerId) {
            $ids[] = $managerId;
        } else {
            Log::warning('schedule_change_notification:no_lead_manager', [
                'project_id' => $project->id,
                'amo_deal_id' => $project->amo_deal_id,
                'client_name' => $project->client_name,
            ]);
        }

        $ntvIds = DB::table('users')
            ->where('role', 'ntv')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($ntvIds)) {
            Log::warning('schedule_change_notification:no_ntv_users', [
                'project_id' => $project->id,
            ]);
        }

        return array_values(array_unique(array_merge($ids, $ntvIds)));
    }

    private function resolveLeadManagerId(SalesProject $project): ?int
    {
        $rawLeadId = $project->lead_manager_user_id ? (int) $project->lead_manager_user_id : null;

        if ($rawLeadId) {
            $localUser = DB::table('users')
                ->where('id', $rawLeadId)
                ->whereIn('role', ['owner', 'ntv', 'manager'])
                ->first(['id']);

            if ($localUser) {
                return (int) $localUser->id;
            }

            $mapped = $this->amoResponsibleToLocalUserMap()[$rawLeadId] ?? null;
            if ($mapped) {
                return (int) $mapped;
            }
        }

        if (!$project->amo_deal_id || !Schema::hasTable('amo_complectation_projects')) {
            return null;
        }

        $amoResponsible = DB::table('amo_complectation_projects')
            ->where('amo_deal_id', (int) $project->amo_deal_id)
            ->orderByDesc('updated_at')
            ->first(['responsible_user_id', 'responsible_name']);

        $amoResponsibleId = (int) ($amoResponsible->responsible_user_id ?? 0);
        if ($amoResponsibleId <= 0) {
            return null;
        }

        $mapped = $this->amoResponsibleToLocalUserMap()[$amoResponsibleId] ?? null;
        if (!$mapped) {
            Log::warning('schedule_change_notification:amo_user_no_local_account', [
                'project_id' => $project->id,
                'amo_deal_id' => $project->amo_deal_id,
                'amo_user_id' => $amoResponsibleId,
                'responsible_name' => $amoResponsible->responsible_name ?? null,
                'client_name' => $project->client_name,
            ]);
            return null;
        }

        return (int) $mapped;
    }

    private function amoResponsibleToLocalUserMap(): array
    {
        return [
            9296522 => 17,
            9888062 => 14,
            12498694 => 15,
            12890150 => 17,
            6838192 => 2,
            11148522 => 1,
        ];
    }

    private function formatDates(array $dates): string
    {
        if (empty($dates)) {
            return '—';
        }

        return implode(', ', array_map(function ($date) {
            try {
                return \Carbon\Carbon::parse($date)->format('d.m.Y');
            } catch (\Throwable) {
                return (string) $date;
            }
        }, $dates));
    }
}
