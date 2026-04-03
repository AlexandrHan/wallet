<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite: recreate table with nullable project_id + add service_request_id
        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement('ALTER TABLE quality_checks RENAME TO quality_checks_bak');

        DB::statement("
            CREATE TABLE quality_checks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NULL,
                service_request_id INTEGER NULL,
                created_by INTEGER NOT NULL,
                approved_by INTEGER NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                deficiencies TEXT NULL,
                voice_memo_path TEXT NULL,
                approved_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )
        ");

        DB::statement('
            INSERT INTO quality_checks
                (id, project_id, created_by, approved_by, status, deficiencies, voice_memo_path, approved_at, created_at, updated_at)
            SELECT
                id, project_id, created_by, approved_by, status, deficiencies, voice_memo_path, approved_at, created_at, updated_at
            FROM quality_checks_bak
        ');

        DB::statement('DROP TABLE quality_checks_bak');

        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        // Not reversible cleanly — just keep as is
    }
};
