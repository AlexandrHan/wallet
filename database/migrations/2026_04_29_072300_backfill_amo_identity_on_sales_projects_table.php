<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('sales_projects')
            || !Schema::hasTable('amo_complectation_projects')
            || !Schema::hasTable('amocrm_deal_map')
            || !Schema::hasColumn('sales_projects', 'amo_deal_id')
        ) {
            return;
        }

        DB::transaction(function () {
            DB::statement(<<<'SQL'
                UPDATE sales_projects
                SET
                    amo_deal_id = (
                        SELECT ac.amo_deal_id
                        FROM amo_complectation_projects ac
                        WHERE ac.wallet_project_id = sales_projects.id
                        LIMIT 1
                    ),
                    pipeline_id = (
                        SELECT ac.pipeline_id
                        FROM amo_complectation_projects ac
                        WHERE ac.wallet_project_id = sales_projects.id
                        LIMIT 1
                    ),
                    amo_deal_name = (
                        SELECT ac.deal_name
                        FROM amo_complectation_projects ac
                        WHERE ac.wallet_project_id = sales_projects.id
                        LIMIT 1
                    ),
                    amo_status_id = (
                        SELECT ac.status_id
                        FROM amo_complectation_projects ac
                        WHERE ac.wallet_project_id = sales_projects.id
                        LIMIT 1
                    )
                WHERE source_layer = 'finance'
                  AND EXISTS (
                      SELECT 1
                      FROM amo_complectation_projects ac
                      WHERE ac.wallet_project_id = sales_projects.id
                  )
            SQL);

            DB::statement(<<<'SQL'
                UPDATE sales_projects
                SET
                    amo_deal_id = (
                        SELECT m.amo_deal_id
                        FROM amocrm_deal_map m
                        JOIN amo_complectation_projects ac ON ac.amo_deal_id = m.amo_deal_id
                        WHERE m.wallet_project_id = sales_projects.id
                        GROUP BY m.wallet_project_id
                        HAVING COUNT(DISTINCT m.amo_deal_id) = 1
                        LIMIT 1
                    ),
                    pipeline_id = (
                        SELECT ac.pipeline_id
                        FROM amocrm_deal_map m
                        JOIN amo_complectation_projects ac ON ac.amo_deal_id = m.amo_deal_id
                        WHERE m.wallet_project_id = sales_projects.id
                        GROUP BY m.wallet_project_id
                        HAVING COUNT(DISTINCT m.amo_deal_id) = 1
                        LIMIT 1
                    ),
                    amo_deal_name = (
                        SELECT ac.deal_name
                        FROM amocrm_deal_map m
                        JOIN amo_complectation_projects ac ON ac.amo_deal_id = m.amo_deal_id
                        WHERE m.wallet_project_id = sales_projects.id
                        GROUP BY m.wallet_project_id
                        HAVING COUNT(DISTINCT m.amo_deal_id) = 1
                        LIMIT 1
                    ),
                    amo_status_id = (
                        SELECT COALESCE(m.amo_status_id, ac.status_id)
                        FROM amocrm_deal_map m
                        JOIN amo_complectation_projects ac ON ac.amo_deal_id = m.amo_deal_id
                        WHERE m.wallet_project_id = sales_projects.id
                        GROUP BY m.wallet_project_id
                        HAVING COUNT(DISTINCT m.amo_deal_id) = 1
                        LIMIT 1
                    )
                WHERE source_layer = 'projects'
                  AND EXISTS (
                      SELECT 1
                      FROM amocrm_deal_map m
                      JOIN amo_complectation_projects ac ON ac.amo_deal_id = m.amo_deal_id
                      WHERE m.wallet_project_id = sales_projects.id
                      GROUP BY m.wallet_project_id
                      HAVING COUNT(DISTINCT m.amo_deal_id) = 1
                  )
            SQL);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sales_projects') || !Schema::hasColumn('sales_projects', 'amo_deal_id')) {
            return;
        }

        DB::table('sales_projects')->update([
            'amo_deal_id' => null,
            'pipeline_id' => null,
            'amo_deal_name' => null,
            'amo_status_id' => null,
        ]);
    }
};
