<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // github, jira, feishu_base, csv
            $table->string('name');
            $table->jsonb('config')->nullable(); // connection credentials, webhook secrets
            $table->string('status')->default('active'); // active, paused, error
            $table->timestamp('last_sync_at')->nullable();
            $table->jsonb('sync_state')->nullable(); // cursor, last event id etc.
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
