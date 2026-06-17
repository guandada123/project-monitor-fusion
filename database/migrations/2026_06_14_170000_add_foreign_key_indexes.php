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
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['owner_id']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
        });
    }
};
