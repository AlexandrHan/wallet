<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add check_type to quality_checks ───────────────────────────────
        // SQLite: ALTER TABLE ADD COLUMN (one at a time)
        DB::statement("ALTER TABLE quality_checks ADD COLUMN check_type VARCHAR(10) NULL");

        // ── 2. Add check status columns to sales_projects ─────────────────────
        DB::statement("ALTER TABLE sales_projects ADD COLUMN electric_check_status VARCHAR(20) NULL");
        DB::statement("ALTER TABLE sales_projects ADD COLUMN panel_check_status VARCHAR(20) NULL");

        // ── 3. Data migration: set check_type on existing quality_checks ──────
        // Service checks → 'electric' (services are always electrical work)
        DB::statement("
            UPDATE quality_checks
            SET check_type = 'electric'
            WHERE service_request_id IS NOT NULL
              AND check_type IS NULL
        ");

        // Project checks: determine from submitter's position
        DB::statement("
            UPDATE quality_checks
            SET check_type = 'electric'
            WHERE project_id IS NOT NULL
              AND check_type IS NULL
              AND created_by IN (SELECT id FROM users WHERE position = 'electrician')
        ");

        // Remaining project checks → panel (installers, foremen, owner)
        DB::statement("
            UPDATE quality_checks
            SET check_type = 'panel'
            WHERE project_id IS NOT NULL
              AND check_type IS NULL
        ");

        // ── 4. Data migration: sales_projects already completed → both done ──
        DB::statement("
            UPDATE sales_projects
            SET electric_check_status = 'done',
                panel_check_status    = 'done'
            WHERE construction_status IN ('salary_pending', 'salary_paid', 'quality_approved')
        ");

        // ── 5. Projects currently waiting quality check → set per-type status ─

        // Waiting electric checks
        DB::statement("
            UPDATE sales_projects
            SET electric_check_status = 'waiting'
            WHERE id IN (
                SELECT DISTINCT qc.project_id
                FROM quality_checks qc
                WHERE qc.project_id IS NOT NULL
                  AND qc.check_type = 'electric'
                  AND qc.status IN ('pending', 'deficiencies_fixed')
            )
            AND electric_check_status IS NULL
        ");

        // Defect electric checks
        DB::statement("
            UPDATE sales_projects
            SET electric_check_status = 'defect'
            WHERE id IN (
                SELECT DISTINCT qc.project_id
                FROM quality_checks qc
                WHERE qc.project_id IS NOT NULL
                  AND qc.check_type = 'electric'
                  AND qc.status = 'has_deficiencies'
            )
            AND electric_check_status IS NULL
        ");

        // Waiting panel checks
        DB::statement("
            UPDATE sales_projects
            SET panel_check_status = 'waiting'
            WHERE id IN (
                SELECT DISTINCT qc.project_id
                FROM quality_checks qc
                WHERE qc.project_id IS NOT NULL
                  AND qc.check_type = 'panel'
                  AND qc.status IN ('pending', 'deficiencies_fixed')
            )
            AND panel_check_status IS NULL
        ");

        // Defect panel checks
        DB::statement("
            UPDATE sales_projects
            SET panel_check_status = 'defect'
            WHERE id IN (
                SELECT DISTINCT qc.project_id
                FROM quality_checks qc
                WHERE qc.project_id IS NOT NULL
                  AND qc.check_type = 'panel'
                  AND qc.status = 'has_deficiencies'
            )
            AND panel_check_status IS NULL
        ");
    }

    public function down(): void
    {
        // SQLite does not support DROP COLUMN — not reversible cleanly
    }
};
