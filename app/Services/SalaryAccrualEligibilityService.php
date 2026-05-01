<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SalaryAccrualEligibilityService
{
    public function hasApprovedProjectQualityCheck(int $projectId, string $checkType, bool $allowLegacyNullType = false): bool
    {
        return DB::table('quality_checks')
            ->where('project_id', $projectId)
            ->whereNull('service_request_id')
            ->where('status', 'approved')
            ->where(function ($query) use ($checkType, $allowLegacyNullType) {
                $query->where('check_type', $checkType);

                if ($allowLegacyNullType) {
                    $query->orWhereNull('check_type');
                }
            })
            ->exists();
    }

    public function hasPanelQualityApproval(object $project): bool
    {
        $projectId = (int) ($project->id ?? 0);

        return (string) ($project->panel_check_status ?? '') === 'done'
            || ($projectId > 0 && $this->hasApprovedProjectQualityCheck($projectId, 'panel', true));
    }

    public function hasElectricQualityApproval(object $project): bool
    {
        $projectId = (int) ($project->id ?? 0);

        return (string) ($project->electric_check_status ?? '') === 'done'
            || ($projectId > 0 && $this->hasApprovedProjectQualityCheck($projectId, 'electric'));
    }

    public function applyToQuery($query, string $salaryAlias = 'sa', string $projectAlias = 'sp')
    {
        return $query
            ->where(function ($duplicateGuard) use ($salaryAlias) {
                $duplicateGuard
                    ->whereNull("{$salaryAlias}.project_id")
                    ->orWhereNotExists(function ($paidQuery) use ($salaryAlias) {
                        $paidQuery
                            ->select(DB::raw(1))
                            ->from('salary_accruals as paid_sa')
                            ->whereColumn('paid_sa.project_id', "{$salaryAlias}.project_id")
                            ->whereColumn('paid_sa.user_id', "{$salaryAlias}.user_id")
                            ->whereColumn('paid_sa.staff_group', "{$salaryAlias}.staff_group")
                            ->whereColumn('paid_sa.staff_name', "{$salaryAlias}.staff_name")
                            ->where('paid_sa.status', 'paid');
                    });
            })
            ->where(function ($eligibility) use ($salaryAlias, $projectAlias) {
                $eligibility
                    ->whereNull("{$salaryAlias}.project_id")
                    ->orWhereNotIn("{$salaryAlias}.staff_group", ['installation_team', 'electrician'])
                    ->orWhere(function ($panel) use ($salaryAlias, $projectAlias) {
                        $panel
                            ->where("{$salaryAlias}.staff_group", 'installation_team')
                            ->where(function ($quality) use ($salaryAlias, $projectAlias) {
                                $quality
                                    ->where("{$projectAlias}.panel_check_status", 'done')
                                    ->orWhereExists(function ($qualityQuery) use ($salaryAlias) {
                                        $qualityQuery
                                            ->select(DB::raw(1))
                                            ->from('quality_checks as qc')
                                            ->whereColumn('qc.project_id', "{$salaryAlias}.project_id")
                                            ->whereNull('qc.service_request_id')
                                            ->where('qc.status', 'approved')
                                            ->where(function ($typeQuery) {
                                                $typeQuery
                                                    ->where('qc.check_type', 'panel')
                                                    ->orWhereNull('qc.check_type');
                                            });
                                    });
                            });
                    })
                    ->orWhere(function ($electric) use ($salaryAlias, $projectAlias) {
                        $electric
                            ->where("{$salaryAlias}.staff_group", 'electrician')
                            ->whereExists(function ($ruleQuery) use ($salaryAlias) {
                                $ruleQuery
                                    ->select(DB::raw(1))
                                    ->from('salary_rules as sr')
                                    ->where('sr.staff_group', 'electrician')
                                    ->whereColumn('sr.staff_name', "{$salaryAlias}.staff_name")
                                    ->where('sr.mode', '!=', 'fixed');
                            })
                            ->where(function ($quality) use ($salaryAlias, $projectAlias) {
                                $quality
                                    ->where("{$projectAlias}.electric_check_status", 'done')
                                    ->orWhereExists(function ($qualityQuery) use ($salaryAlias) {
                                        $qualityQuery
                                            ->select(DB::raw(1))
                                            ->from('quality_checks as qc')
                                            ->whereColumn('qc.project_id', "{$salaryAlias}.project_id")
                                            ->whereNull('qc.service_request_id')
                                            ->where('qc.status', 'approved')
                                            ->where('qc.check_type', 'electric');
                                    });
                            });
                    });
            });
    }

    public function pendingForUser(int $userId)
    {
        $query = DB::table('salary_accruals as sa')
            ->leftJoin('sales_projects as sp', 'sp.id', '=', 'sa.project_id')
            ->where('sa.user_id', $userId)
            ->where('sa.status', 'pending')
            ->select('sa.*');

        return $this->applyToQuery($query)->get();
    }

    public function countPendingEligible(): int
    {
        $query = DB::table('salary_accruals as sa')
            ->leftJoin('sales_projects as sp', 'sp.id', '=', 'sa.project_id')
            ->where('sa.status', 'pending');

        return (int) $this->applyToQuery($query)->count();
    }
}
