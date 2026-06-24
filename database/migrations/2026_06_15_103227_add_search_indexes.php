<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // pg_trgm 是 PostgreSQL 专有扩展，SQLite 测试环境不执行
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // projects 表 GIN 三元组索引，加速 LIKE/ILIKE 中文模糊搜索
        DB::statement('CREATE INDEX IF NOT EXISTS idx_projects_name_gin ON projects USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_projects_description_gin ON projects USING gin (description gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_projects_slug_gin ON projects USING gin (slug gin_trgm_ops)');

        // tasks 表
        DB::statement('CREATE INDEX IF NOT EXISTS idx_tasks_title_gin ON tasks USING gin (title gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_tasks_description_gin ON tasks USING gin (description gin_trgm_ops)');

        // risks 表
        DB::statement('CREATE INDEX IF NOT EXISTS idx_risks_title_gin ON risks USING gin (title gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_risks_detail_gin ON risks USING gin (detail gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_risks_detail_gin');
        DB::statement('DROP INDEX IF EXISTS idx_risks_title_gin');
        DB::statement('DROP INDEX IF EXISTS idx_tasks_description_gin');
        DB::statement('DROP INDEX IF EXISTS idx_tasks_title_gin');
        DB::statement('DROP INDEX IF EXISTS idx_projects_slug_gin');
        DB::statement('DROP INDEX IF EXISTS idx_projects_description_gin');
        DB::statement('DROP INDEX IF EXISTS idx_projects_name_gin');
    }
};
