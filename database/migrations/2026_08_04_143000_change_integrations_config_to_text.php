<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * integrations.config 由模型 cast 为 encrypted:array（加密密文文本），
     * 与 jsonb 列类型不匹配——密文非合法 JSON，migrate --seed 报
     * SQLSTATE[22P02] invalid input syntax for type json。
     * 列改为 text 与 cast 语义一致（保留凭据加密，读取仍返回数组）。
     */
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->text('config')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->jsonb('config')->nullable()->change();
        });
    }
};
