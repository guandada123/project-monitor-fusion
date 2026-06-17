<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('source')->default('manual'); // github, jira, feishu_base, csv, manual
            $table->string('external_id')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('active'); // active, completed, archived, on_hold
            $table->text('description')->nullable();
            $table->date('start_at')->nullable();
            $table->date('end_at')->nullable();
            $table->jsonb('meta')->nullable(); // flexible metadata
            $table->jsonb('tags')->nullable();
            $table->jsonb('config')->nullable(); // project-specific config (KPI sets, risk rules override)
            $table->timestamps();
            $table->softDeletes();

            $table->index('source');
            $table->index('status');
            $table->index(['source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
