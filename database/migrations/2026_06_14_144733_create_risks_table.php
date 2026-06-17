<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('severity')->default('medium'); // critical, high, medium, low
            $table->string('rule_key'); // e.g. overdue_tasks_ratio, resource_bottleneck
            $table->string('title');
            $table->text('detail')->nullable();
            $table->jsonb('evidence')->nullable(); // links, data points
            $table->string('status')->default('open'); // open, acknowledged, closed
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->integer('occurrence_count')->default(1);
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('severity');
            $table->index('status');
            $table->index('rule_key');
            $table->index(['project_id', 'rule_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risks');
    }
};
