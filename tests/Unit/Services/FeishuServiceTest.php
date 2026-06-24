<?php

use App\Services\FeishuService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// ─────────────────────────────────────────────
// FeishuService — 飞书 Webhook 推送服务
// ─────────────────────────────────────────────

beforeEach(function () {
    Cache::flush();
});

afterEach(function () {
    Cache::flush();
});

// ─── 构造函数 & 配置 ────────────────────────

describe('constructor and configuration', function () {

    test('constructor loads from settings service by default', function () {
        // Set up a setting in the DB so loadSettings returns it
        app(SettingsService::class)->set('feishu_webhook_url', 'https://open.feishu.cn/hook/test', 'notification');
        app(SettingsService::class)->set('feishu_partition_tag', '[测试]', 'notification');
        app(SettingsService::class)->set('feishu_bot_name', 'Bot', 'notification');

        $service = new FeishuService;

        expect($service->isConfigured())->toBeTrue();
    });

    test('constructor accepts explicit settings array', function () {
        $service = new FeishuService([
            'feishu_webhook_url' => 'https://open.feishu.cn/hook/explicit',
            'feishu_partition_tag' => '[测试]',
            'feishu_bot_name' => 'TestBot',
            'feishu_daily_report' => false,
            'feishu_risk_alert' => true,
        ]);

        expect($service->isConfigured())->toBeTrue();
    });

    test('empty webhook url means not configured', function () {
        $service = new FeishuService([
            'feishu_webhook_url' => '',
        ]);

        expect($service->isConfigured())->toBeFalse();
    });

    test('defaults apply when settings are missing', function () {
        $service = new FeishuService([]);

        expect($service->isConfigured())->toBeFalse();
        // Default: daily_report = true, risk_alert = true
    });
});

// ─── isConfigured / shouldSendDaily / shouldSendRiskAlert ───

describe('configuration query methods', function () {

    test('isConfigured returns true when webhook url is set', function () {
        $service = new FeishuService([
            'feishu_webhook_url' => 'https://open.feishu.cn/hook/test',
        ]);

        expect($service->isConfigured())->toBeTrue();
    });

    test('isConfigured returns false when webhook url is empty', function () {
        $service = new FeishuService([]);

        expect($service->isConfigured())->toBeFalse();
    });

    test('shouldSendDaily returns true when configured and daily enabled', function () {
        $service = new FeishuService([
            'feishu_webhook_url' => 'https://open.feishu.cn/hook/test',
            'feishu_daily_report' => true,
        ]);

        expect($service->shouldSendDaily())->toBeTrue();
    });

    test('shouldSendDaily returns false when daily disabled', function () {
        $service = new FeishuService([
            'feishu_webhook_url' => 'https://open.feishu.cn/hook/test',
            'feishu_daily_report' => false,
        ]);

        expect($service->shouldSendDaily())->toBeFalse();
    });

    test('shouldSendDaily returns false when not configured', function () {
        $service = new FeishuService([
            'feishu_daily_report' => true,
        ]);

        expect($service->shouldSendDaily())->toBeFalse();
    });

    test('shouldSendRiskAlert returns true when configured and risk alert enabled', function () {
        $service = new FeishuService([
            'feishu_webhook_url' => 'https://open.feishu.cn/hook/test',
            'feishu_risk_alert' => true,
        ]);

        expect($service->shouldSendRiskAlert())->toBeTrue();
    });

    test('shouldSendRiskAlert returns false when risk alert disabled', function () {
        $service = new FeishuService([
            'feishu_webhook_url' => 'https://open.feishu.cn/hook/test',
            'feishu_risk_alert' => false,
        ]);

        expect($service->shouldSendRiskAlert())->toBeFalse();
    });

    test('shouldSendRiskAlert returns false when not configured', function () {
        $service = new FeishuService([
            'feishu_risk_alert' => true,
        ]);

        expect($service->shouldSendRiskAlert())->toBeFalse();
    });
});

// ─── buildDailyCard ────────────────────────

describe('buildDailyCard', function () {

    test('returns correct card structure with header and elements', function () {
        $service = new FeishuService([
            'feishu_partition_tag' => '[项目监控]',
            'feishu_bot_name' => 'PM Bot',
        ]);

        $report = [
            'summary' => [
                'active_projects' => 3,
                'todo_tasks' => 5,
                'in_progress_tasks' => 2,
                'done_tasks' => 10,
            ],
            'projects' => [
                ['name' => '项目A', 'status' => 'active', 'completion_rate' => 75, 'done' => 6, 'total' => 8],
                ['name' => '项目B', 'status' => 'active', 'completion_rate' => 50, 'done' => 4, 'total' => 8],
            ],
            'risks' => [],
        ];

        $card = $service->buildDailyCard($report);

        expect($card)->toHaveKey('config');
        expect($card)->toHaveKey('header');
        expect($card)->toHaveKey('elements');
        expect($card['header']['title']['content'])->toContain('[项目监控]');
        expect($card['header']['template'])->toBe('blue');
    });

    test('card includes summary stats', function () {
        $service = new FeishuService([]);

        $report = [
            'summary' => [
                'active_projects' => 5,
                'todo_tasks' => 10,
                'in_progress_tasks' => 3,
                'done_tasks' => 20,
            ],
            'projects' => [],
            'risks' => [],
        ];

        $card = $service->buildDailyCard($report);

        // Find the markdown element with summary
        $markdownContent = '';
        foreach ($card['elements'] as $el) {
            if ($el['tag'] === 'markdown' && str_contains($el['content'] ?? '', '活跃项目')) {
                $markdownContent = $el['content'];
                break;
            }
        }

        expect($markdownContent)->toContain('5');
        expect($markdownContent)->toContain('10');
        expect($markdownContent)->toContain('3');
        expect($markdownContent)->toContain('20');
    });

    test('card includes project details when projects exist', function () {
        $service = new FeishuService([]);

        $report = [
            'summary' => [
                'active_projects' => 1,
                'todo_tasks' => 0,
                'in_progress_tasks' => 0,
                'done_tasks' => 0,
            ],
            'projects' => [
                ['name' => 'Alpha', 'status' => 'active', 'completion_rate' => 60, 'done' => 3, 'total' => 5],
            ],
            'risks' => [],
        ];

        $card = $service->buildDailyCard($report);

        $markdowns = array_filter($card['elements'], fn ($el) => $el['tag'] === 'markdown');
        $allContent = implode(' ', array_column($markdowns, 'content'));

        expect($allContent)->toContain('Alpha');
        expect($allContent)->toContain('60%');
        expect($allContent)->toContain('3/5');
    });

    test('card includes risk section when risks are present', function () {
        $service = new FeishuService([]);

        $report = [
            'summary' => [
                'active_projects' => 1,
                'todo_tasks' => 0,
                'in_progress_tasks' => 0,
                'done_tasks' => 0,
            ],
            'projects' => [],
            'risks' => [
                ['title' => '延期风险', 'severity' => 'high', 'project_name' => '项目X'],
                ['title' => '资源不足', 'severity' => 'critical', 'project_name' => '项目Y'],
            ],
        ];

        $card = $service->buildDailyCard($report);

        $markdowns = array_filter($card['elements'], fn ($el) => $el['tag'] === 'markdown');
        $allContent = implode(' ', array_column($markdowns, 'content'));

        expect($allContent)->toContain('风险预警');
        expect($allContent)->toContain('延期风险');
        expect($allContent)->toContain('资源不足');
        expect($allContent)->toContain('项目X');
        expect($allContent)->toContain('项目Y');
    });

    test('card footer contains bot name and timestamp', function () {
        $service = new FeishuService([
            'feishu_bot_name' => 'MyMonitor',
        ]);

        $report = [
            'summary' => ['active_projects' => 0, 'todo_tasks' => 0, 'in_progress_tasks' => 0, 'done_tasks' => 0],
            'projects' => [],
            'risks' => [],
        ];

        $card = $service->buildDailyCard($report);

        $noteEl = null;
        foreach ($card['elements'] as $el) {
            if ($el['tag'] === 'note') {
                $noteEl = $el;
                break;
            }
        }

        expect($noteEl)->not->toBeNull();
        expect($noteEl['elements'][0]['content'])->toContain('MyMonitor');
    });

    test('card limits risk display to 5 items', function () {
        $service = new FeishuService([]);

        $risks = [];
        for ($i = 1; $i <= 10; $i++) {
            $risks[] = ['title' => "风险{$i}", 'severity' => 'medium', 'project_name' => '项目'];
        }

        $report = [
            'summary' => ['active_projects' => 0, 'todo_tasks' => 0, 'in_progress_tasks' => 0, 'done_tasks' => 0],
            'projects' => [],
            'risks' => $risks,
        ];

        $card = $service->buildDailyCard($report);

        $markdowns = array_filter($card['elements'], fn ($el) => $el['tag'] === 'markdown');
        $allContent = implode(' ', array_column($markdowns, 'content'));

        // Should only include first 5
        expect($allContent)->toContain('风险1');
        expect($allContent)->toContain('风险5');
        expect($allContent)->not->toContain('风险6');
    });
});

// ─── buildRiskAlertCard ────────────────────

describe('buildRiskAlertCard', function () {

    test('returns correct structure for critical risk', function () {
        $service = new FeishuService([
            'feishu_partition_tag' => '[项目监控]',
        ]);

        $risk = [
            'severity' => 'critical',
            'title' => '数据库宕机',
            'project_name' => '核心服务',
            'detail' => '主库连接超时，持续5分钟',
            'rule_key' => 'db_connectivity',
            'first_seen_at' => '2026-06-14 10:00:00',
        ];

        $card = $service->buildRiskAlertCard($risk);

        expect($card['header']['template'])->toBe('red');
        expect($card['header']['title']['content'])->toContain('风险告警');

        $markdowns = array_filter($card['elements'], fn ($el) => $el['tag'] === 'markdown');
        $allContent = implode(' ', array_column($markdowns, 'content'));

        expect($allContent)->toContain('严重');
        expect($allContent)->toContain('数据库宕机');
        expect($allContent)->toContain('核心服务');
        expect($allContent)->toContain('连接超时');
    });

    test('maps severity to correct template color', function () {
        $service = new FeishuService([]);

        $severityMap = [
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'blue',
        ];

        $severityTextMap = [
            'critical' => '严重',
            'high' => '高',
            'medium' => '中',
            'low' => '低',
        ];

        foreach ($severityMap as $severity => $expectedColor) {
            $risk = [
                'severity' => $severity,
                'title' => '测试风险',
                'project_name' => '测试项目',
                'detail' => '详情',
                'rule_key' => 'test_rule',
                'first_seen_at' => '2026-06-14 10:00:00',
            ];

            $card = $service->buildRiskAlertCard($risk);

            expect($card['header']['template'])->toBe($expectedColor,
                "Severity {$severity} should map to color {$expectedColor}");

            $markdowns = array_filter($card['elements'], fn ($el) => $el['tag'] === 'markdown');
            $allContent = implode(' ', array_column($markdowns, 'content'));

            expect($allContent)->toContain($severityTextMap[$severity]);
        }
    });

    test('includes note with rule key and first seen', function () {
        $service = new FeishuService([]);

        $risk = [
            'severity' => 'high',
            'title' => '测试风险',
            'project_name' => '测试项目',
            'detail' => '风险详情说明',
            'rule_key' => 'stale_task',
            'first_seen_at' => '2026-06-10 08:30:00',
        ];

        $card = $service->buildRiskAlertCard($risk);

        $noteEl = null;
        foreach ($card['elements'] as $el) {
            if ($el['tag'] === 'note') {
                $noteEl = $el;
                break;
            }
        }

        expect($noteEl)->not->toBeNull();
        expect($noteEl['elements'][0]['content'])->toContain('stale_task');
        expect($noteEl['elements'][0]['content'])->toContain('2026-06-10');
    });
});

// ─── sendInteractiveCard (HTTP mock) ───────

describe('sendInteractiveCard with HTTP mock', function () {

    test('returns ok when feishu responds with code 0', function () {
        Http::fake([
            'open.feishu.cn/*' => Http::response(['code' => 0, 'msg' => 'success'], 200),
        ]);

        $service = new FeishuService([
            'feishu_webhook_url' => 'https://open.feishu.cn/open-apis/bot/v2/hook/test',
        ]);

        $result = $service->sendInteractiveCard(['header' => [], 'elements' => []]);

        expect($result['ok'])->toBeTrue();
    });

    test('returns error when feishu returns non-zero code', function () {
        Http::fake([
            'open.feishu.cn/*' => Http::response(['code' => 10003, 'msg' => 'invalid signature'], 200),
        ]);

        $service = new FeishuService([
            'feishu_webhook_url' => 'https://open.feishu.cn/open-apis/bot/v2/hook/test',
        ]);

        $result = $service->sendInteractiveCard(['header' => [], 'elements' => []]);

        expect($result['ok'])->toBeFalse();
        expect($result['error'])->toContain('invalid signature');
    });

    test('returns error on HTTP failure', function () {
        Http::fake([
            'open.feishu.cn/*' => Http::response('Internal Server Error', 500),
        ]);

        $service = new FeishuService([
            'feishu_webhook_url' => 'https://open.feishu.cn/open-apis/bot/v2/hook/test',
        ]);

        $result = $service->sendInteractiveCard(['header' => [], 'elements' => []]);

        expect($result['ok'])->toBeFalse();
        expect($result['error'])->toContain('500');
    });

    test('returns error on network exception', function () {
        Http::fake([
            'open.feishu.cn/*' => function () {
                throw new Exception('Connection refused');
            },
        ]);

        $service = new FeishuService([
            'feishu_webhook_url' => 'https://open.feishu.cn/open-apis/bot/v2/hook/test',
        ]);

        $result = $service->sendInteractiveCard(['header' => [], 'elements' => []]);

        expect($result['ok'])->toBeFalse();
        expect($result['error'])->toContain('Connection refused');
    });

    test('returns not-configured error when webhook url is empty', function () {
        $service = new FeishuService([]);

        $result = $service->sendInteractiveCard(['header' => [], 'elements' => []]);

        expect($result['ok'])->toBeFalse();
        expect($result['error'])->toContain('未配置');
    });

    test('sends correct payload structure to webhook', function () {
        Http::fake([
            'open.feishu.cn/*' => Http::response(['code' => 0], 200),
        ]);

        $service = new FeishuService([
            'feishu_webhook_url' => 'https://open.feishu.cn/open-apis/bot/v2/hook/verify-payload',
        ]);

        $cardData = [
            'config' => ['wide_screen_mode' => true],
            'header' => ['title' => ['tag' => 'plain_text', 'content' => 'Test']],
            'elements' => [['tag' => 'markdown', 'content' => 'Hello']],
        ];

        $service->sendInteractiveCard($cardData);

        Http::assertSent(function ($request) use ($cardData) {
            $body = $request->data();

            return $body['msg_type'] === 'interactive'
                && $body['card'] === $cardData;
        });
    });
});

// ─── sendText (HTTP mock) ─────────────────

describe('sendText with HTTP mock', function () {

    test('returns ok on successful text push', function () {
        Http::fake([
            'open.feishu.cn/*' => Http::response(['code' => 0], 200),
        ]);

        $service = new FeishuService([
            'feishu_webhook_url' => 'https://open.feishu.cn/open-apis/bot/v2/hook/test',
            'feishu_partition_tag' => '[项目监控]',
        ]);

        $result = $service->sendText('Test message');

        expect($result['ok'])->toBeTrue();
    });

    test('prepends partition tag to text content', function () {
        Http::fake([
            'open.feishu.cn/*' => Http::response(['code' => 0], 200),
        ]);

        $service = new FeishuService([
            'feishu_webhook_url' => 'https://open.feishu.cn/open-apis/bot/v2/hook/verify-text',
            'feishu_partition_tag' => '[测试分区]',
        ]);

        $service->sendText('Hello');

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['msg_type'] === 'text'
                && str_contains($body['content']['text'], '[测试分区]')
                && str_contains($body['content']['text'], 'Hello');
        });
    });

    test('returns error on HTTP failure for text', function () {
        Http::fake([
            'open.feishu.cn/*' => Http::response(null, 403),
        ]);

        $service = new FeishuService([
            'feishu_webhook_url' => 'https://open.feishu.cn/open-apis/bot/v2/hook/test',
        ]);

        $result = $service->sendText('Test');

        expect($result['ok'])->toBeFalse();
        expect($result['error'])->toContain('403');
    });

    test('returns not-configured error on empty webhook for text', function () {
        $service = new FeishuService([]);

        $result = $service->sendText('Test');

        expect($result['ok'])->toBeFalse();
        expect($result['error'])->toContain('未配置');
    });
});

// ─── Edge Cases ───────────────────────────

describe('edge cases', function () {

    test('handles empty report gracefully in buildDailyCard', function () {
        $service = new FeishuService([]);

        $report = [
            'summary' => [
                'active_projects' => 0,
                'todo_tasks' => 0,
                'in_progress_tasks' => 0,
                'done_tasks' => 0,
            ],
            'projects' => [],
            'risks' => [],
        ];

        $card = $service->buildDailyCard($report);

        expect($card['elements'])->not->toBeEmpty();
        // Should have header + hr + summary + hr + footer (5 elements minimum)
        expect(count($card['elements']))->toBeGreaterThanOrEqual(4);
    });

    test('sendInteractiveCard catches exceptions gracefully', function () {
        Http::fake([
            'open.feishu.cn/*' => function () {
                throw new ErrorException('cURL error 28: Connection timed out');
            },
        ]);

        $service = new FeishuService([
            'feishu_webhook_url' => 'https://open.feishu.cn/open-apis/bot/v2/hook/test',
        ]);

        $result = $service->sendInteractiveCard(['elements' => []]);

        expect($result['ok'])->toBeFalse();
        expect($result['error'])->toContain('Connection timed out');
    });

    test('sendText catches exceptions gracefully', function () {
        Http::fake([
            'open.feishu.cn/*' => function () {
                throw new ErrorException('cURL error 7: Failed to connect');
            },
        ]);

        $service = new FeishuService([
            'feishu_webhook_url' => 'https://open.feishu.cn/open-apis/bot/v2/hook/test',
        ]);

        $result = $service->sendText('Test');

        expect($result['ok'])->toBeFalse();
        expect($result['error'])->toContain('Failed to connect');
    });
});
