<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 删除 slow_queries 表上两个无效的布尔列索引。
 *
 * 依据（2026-10-17 取证）：
 *  - 选择性：is_slow = 100.00% 全真（常量列，索引永远无法被规划器用于过滤）；
 *    is_critical = 71.55%。
 *  - 实际使用：pg_stat_user_indexes 中两者 idx_scan 均为 0。
 *  - 表规模：1037 行 / 912 kB —— 顺序扫描必然更快，索引是纯开销。
 *  - 附带收益：减少可被存储层异常（Docker Desktop VM 强杀致 zero page）波及的索引页面。
 *
 * 保留：pkey、time_index、captured_at_index、captured_at_time_index
 * （三者的 idx_scan 分别为 31/37/42，确有使用）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slow_queries', function (Blueprint $table): void {
            $table->dropIndex(['is_slow']);
            $table->dropIndex(['is_critical']);
        });
    }

    public function down(): void
    {
        Schema::table('slow_queries', function (Blueprint $table): void {
            $table->index('is_slow');
            $table->index('is_critical');
        });
    }
};
