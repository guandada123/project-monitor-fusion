<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            // NOTE: 外键延迟到 2026_06_14_170000_add_foreign_key_indexes 建立。
            // 原因：本迁移与 create_projects_table 共享时间戳 144729，Linux scandir 顺序不确定，
            // 内联 ->constrained() 会偶发在 projects 建表前执行 → CI 报 "relation projects does not exist"。
            $table->foreignId('project_id')->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('todo'); // todo, in_progress, review, done, blocked
            $table->string('priority')->default('medium'); // critical, high, medium, low
            $table->decimal('estimate', 10, 2)->nullable(); // estimated hours
            $table->decimal('spent', 10, 2)->default(0); // actual hours spent
            $table->date('start_at')->nullable();
            $table->date('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->jsonb('tags')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('project_id');
            $table->index('assignee_id');
            $table->index('status');
            $table->index('priority');
            $table->index(['project_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
