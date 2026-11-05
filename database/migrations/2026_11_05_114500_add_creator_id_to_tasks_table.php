<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 补齐 tasks.creator_id（2026-11-05）。
 *
 * 背景：TaskController::store() 写入 creator_id、show() 里 load('creator')，
 * 但 tasks 表从来没有这列、Task 模型也没有 creator() 关系。两个后果都不响：
 *   - store()：creator_id 不在 $fillable → mass assignment 静默丢弃，接口照样 201；
 *   - show()：load('creator') → RelationNotFoundException → GET /api/tasks/{id} 直接 500。
 *
 * 与 2026-10-24 的 sort_order 同族（代码先行、迁移缺席）。本次补齐列 + 关系，
 * 让「创建人」真的被记录。nullable + nullOnDelete：存量任务 creator_id 为 NULL，
 * 删用户时置空而不是连带删任务。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creator_id');
        });
    }
};
