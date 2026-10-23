<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 补齐 tasks.sort_order 列（2026-10-24）。
 *
 * 背景：BoardController::index() 与 TaskController::reorder() 早已使用该列，
 * 但从未有迁移创建它 —— 该半成品功能在两个驱动上的表现完全不同：
 *   - SQLite：`order by "sort_order"` 会因兼容性怪癖把不存在的双引号标识符
 *     当作字符串字面量，查询静默成功但排序无效（测试因此长期绿灯）；
 *   - PostgreSQL：正确抛出 `column "sort_order" does not exist`，
 *     /api/board 与 PUT /api/tasks/reorder 直接 500。
 *
 * 测试库切 pgsql 后该差异暴露（见 phpunit.xml 注释）。此处补列使两驱动一致。
 * 默认值 0 + 查询侧 id 兜底排序，存量数据顺序不变。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->integer('sort_order')->default(0);
            // 看板按项目取任务后依 sort_order 排序，复合索引覆盖该访问模式
            $table->index(['project_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
