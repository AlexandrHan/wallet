<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        DB::statement('ALTER TABLE salary_accruals RENAME TO salary_accruals_bak');
        DB::statement("
            CREATE TABLE salary_accruals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NULL,
                user_id INTEGER NOT NULL,
                staff_group VARCHAR(50) NOT NULL,
                staff_name VARCHAR(100) NOT NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT '0',
                currency VARCHAR(10) NOT NULL DEFAULT 'USD',
                details TEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                paid_by INTEGER NULL,
                paid_at TIMESTAMP NULL,
                entry_id INTEGER NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                paid_usd DECIMAL(10,2) NULL,
                paid_uah DECIMAL(10,2) NULL,
                paid_rate DECIMAL(8,2) NULL
            )
        ");
        DB::statement('INSERT INTO salary_accruals SELECT * FROM salary_accruals_bak');
        DB::statement('DROP TABLE salary_accruals_bak');
        DB::statement('PRAGMA foreign_keys = ON');
    }
    public function down(): void {}
};
