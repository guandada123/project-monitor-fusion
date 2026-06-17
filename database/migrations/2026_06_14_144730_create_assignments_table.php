<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('assignee'); // assignee, reviewer, observer
            $table->integer('allocation_percent')->default(100); // 0-100
            $table->timestamps();

            $table->unique(['task_id', 'user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
