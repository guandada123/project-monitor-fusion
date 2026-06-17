<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slow_queries', function (Blueprint $table) {
            $table->id();
            $table->text('query');              // SQL 原文
            $table->longText('bindings')->nullable(); // JSON 序列化的绑定参数
            $table->decimal('time', 10, 2);     // 耗时（毫秒）
            $table->string('connection_name', 50)->nullable();
            $table->string('caller')->nullable();  // 调用点 文件:行号
            $table->string('request_uri')->nullable();
            $table->string('request_method', 10)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_slow')->default(false);     // >= 500ms
            $table->boolean('is_critical')->default(false); // >= 1000ms
            $table->timestamp('captured_at')->useCurrent();

            // 索引
            $table->index('time');
            $table->index('captured_at');
            $table->index('is_slow');
            $table->index('is_critical');
            $table->index(['captured_at', 'time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slow_queries');
    }
};
