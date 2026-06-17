<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key')->unique(); // e.g. completion_rate, overdue_ratio
            $table->string('name');
            $table->string('kind')->default('system'); // system, custom
            $table->jsonb('definition')->nullable(); // JSONLogic DSL definition
            $table->jsonb('dimensions')->nullable(); // grouping dimensions
            $table->jsonb('filters')->nullable(); // filter criteria
            $table->string('schedule_cron')->nullable(); // auto-compute cron
            $table->timestamp('last_computed_at')->nullable();
            $table->decimal('current_value', 15, 4)->nullable();
            $table->jsonb('current_json_value')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};
