<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 255)->unique()->comment('配置键名');
            $table->text('value')->nullable()->comment('配置值（JSON 序列化存储）');
            $table->string('group', 100)->nullable()->index()->comment('配置分组（general/notification 等）');
            $table->timestamps();
        });

        // 自动迁移 settings.json 数据到数据库
        $this->importFromJson();
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }

    private function importFromJson(): void
    {
        $jsonPath = storage_path('app/settings.json');
        if (!file_exists($jsonPath)) {
            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        if (empty($data) || !is_array($data)) {
            return;
        }

        $keyGroupMap = [
            'naming_rule'       => 'general',
            'default_template'  => 'general',
            'feishu_daily_report'  => 'notification',
            'feishu_risk_alert'    => 'notification',
            'feishu_daily_time'    => 'notification',
            'feishu_webhook_url'   => 'notification',
            'feishu_partition_tag' => 'notification',
            'feishu_bot_name'      => 'notification',
        ];

        $now = now();

        foreach ($data as $key => $value) {
            $group = $keyGroupMap[$key] ?? 'general';
            $serialized = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

            DB::table('settings')->insert([
                'key'       => $key,
                'value'     => $serialized,
                'group'     => $group,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        echo "✓ settings.json 数据已迁移至 settings 表（" . count($data) . " 条）\n";
    }
};
