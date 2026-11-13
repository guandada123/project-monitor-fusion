<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 补齐 tasks.creator_id 的索引与外键（2026-11-05，接前一支迁移）。
 *
 * 为什么要单独一支：前一支 `2026_11_05_114500` 里把 `->index()` 链在
 * `->constrained()->nullOnDelete()` 后面**不生效** —— 迁移报成功，但
 * `pg_indexes` 里查不到索引（Laravel 把 index() 当成了 ForeignKeyDefinition 的修饰符）。
 * 补跑时又因重复 rollback 把外键删掉了（列与迁移记录仍在），所以才需要这里做幂等补齐：
 * 索引、外键各自先查存在性，缺哪个补哪个。
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasIndex = (int) DB::selectOne(
            "select count(*) as c from pg_indexes where tablename = 'tasks' and indexname = 'tasks_creator_id_index'"
        )->c;

        if ($hasIndex === 0) {
            Schema::table('tasks', function (Blueprint $table) {
                // PostgreSQL 的外键不自动建索引（MySQL 才会）；assignee_id 已有同级索引，
                // 「我创建的任务」属同一访问模式。
                $table->index('creator_id');
            });
        }

        $hasFk = (int) DB::selectOne(
            "select count(*) as c from pg_constraint where conrelid = 'tasks'::regclass and conname = 'tasks_creator_id_foreign'"
        )->c;

        if ($hasFk === 0) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->foreign('creator_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // 与 up() 对称：外键/索引都可能已被手工删过（11-05 就因重复 rollback 丢过一次
        // 外键，列与迁移记录还留着）。无条件 drop 会让 rollback 直接抛错、迁移卡在半路，
        // 所以两者各自先查存在性，缺了就当已完成。
        $hasFk = (int) DB::selectOne(
            "select count(*) as c from pg_constraint where conrelid = 'tasks'::regclass and conname = 'tasks_creator_id_foreign'"
        )->c;

        if ($hasFk > 0) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropForeign(['creator_id']);
            });
        }

        $hasIndex = (int) DB::selectOne(
            "select count(*) as c from pg_indexes where tablename = 'tasks' and indexname = 'tasks_creator_id_index'"
        )->c;

        if ($hasIndex > 0) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropIndex(['creator_id']);
            });
        }
    }
};
