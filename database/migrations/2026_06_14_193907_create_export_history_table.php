<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type'); // project, task, risk
            $table->string('format'); // csv, xlsx
            $table->json('filters')->nullable(); // applied filters at export time
            $table->json('selected_columns')->nullable(); // chosen columns
            $table->unsignedInteger('record_count')->default(0);
            $table->string('file_path')->nullable(); // storage path
            $table->string('file_size')->nullable(); // human-readable
            $table->string('status')->default('completed'); // completed, failed, processing
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_history');
    }
};
