<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metric_id')->constrained()->cascadeOnDelete();
            $table->timestamp('captured_at');
            $table->decimal('value', 15, 4)->nullable();
            $table->jsonb('json_value')->nullable();
            $table->timestamps();

            $table->index(['metric_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_snapshots');
    }
};
