<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            // Step 1: Add the column as nullable first (existing rows get NULL)
            $table->foreignId('created_by')
                ->nullable()
                ->after('error_message')
                ->constrained('users')
                ->cascadeOnDelete();
        });

        // Step 2: Assign all existing integrations to the first admin user
        // (assumes at least one user exists; if not, default to 0 meaning "orphaned")
        $firstUserId = DB::table('users')->orderBy('id')->value('id') ?? 0;
        if ($firstUserId > 0) {
            DB::table('integrations')->whereNull('created_by')->update(['created_by' => $firstUserId]);
        }

        // Step 3: Make the column NOT NULL now that all rows have a value
        Schema::table('integrations', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
