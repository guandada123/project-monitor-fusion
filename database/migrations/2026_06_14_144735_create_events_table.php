<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('source'); // github, jira, feishu_base
            $table->string('external_id')->nullable();
            $table->string('event_type'); // issue.created, project.updated, etc.
            $table->timestamp('occurred_at');
            $table->jsonb('payload');
            $table->string('fingerprint')->nullable(); // dedup hash
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->text('error')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();

            $table->index('source');
            $table->index('event_type');
            $table->index('fingerprint');
            $table->index('processed_at');
            $table->index(['source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
