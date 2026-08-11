<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // projects.owner_id is a FK to users but lacks an index
        Schema::table('projects', function (Blueprint $table) {
            $table->index('owner_id');
        });

        // events.project_id is a FK to projects but lacks an index
        Schema::table('events', function (Blueprint $table) {
            $table->index('project_id');
        });

        // tasks.project_id 外键延迟至此建立：create_tasks_table 与 create_projects_table 共享
        // 时间戳 144729，scandir 顺序不确定会偶发"projects 不存在"。本迁移时间戳 170000 恒在最后，
        // 此时 projects/tasks 均已存在，外键建立确定性通过（修复 CI flaky 红灯）。
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['owner_id']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
        });
    }
};
