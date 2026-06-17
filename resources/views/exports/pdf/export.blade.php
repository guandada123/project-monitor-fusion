<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>导出报告</title>
    <style>
        @page { margin: 20mm 15mm 20mm 15mm; }
        body {
            font-family: 'PingFang SC', 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #1e293b;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #6366f1;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 18pt;
            color: #6366f1;
            margin: 0 0 5px 0;
        }
        .header p {
            font-size: 9pt;
            color: #64748b;
            margin: 0;
        }
        .summary-section {
            margin-bottom: 20px;
            padding: 12px 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .summary-section h2 {
            font-size: 12pt;
            color: #334155;
            margin: 0 0 8px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #e2e8f0;
        }
        .summary-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .summary-item {
            flex: 1;
            min-width: 120px;
            padding: 8px 12px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .summary-item .label {
            font-size: 7.5pt;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-item .value {
            font-size: 14pt;
            font-weight: bold;
            color: #6366f1;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 8.5pt;
        }
        table thead th {
            background: #6366f1;
            color: #ffffff;
            padding: 8px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table tbody td {
            padding: 6px 10px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
        }
        table tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        table tbody tr:hover {
            background: #eef2ff;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 7.5pt;
            font-weight: 600;
        }
        .status-active { background: #dbeafe; color: #3b82f6; }
        .status-completed { background: #d1fae5; color: #10b981; }
        .status-on_hold { background: #fef3c7; color: #f59e0b; }
        .status-open { background: #fee2e2; color: #ef4444; }
        .status-acknowledged { background: #fef3c7; color: #f59e0b; }
        .status-closed { background: #d1fae5; color: #10b981; }
        .status-done { background: #d1fae5; color: #10b981; }
        .severity-critical { background: #fee2e2; color: #dc2626; font-weight: bold; }
        .severity-high { background: #ffedd5; color: #ea580c; }
        .severity-medium { background: #fef9c3; color: #ca8a04; }
        .severity-low { background: #e0f2fe; color: #0284c7; }
        .priority-critical { background: #fee2e2; color: #dc2626; }
        .priority-high { background: #ffedd5; color: #ea580c; }
        .priority-medium { background: #fef9c3; color: #ca8a04; }
        .priority-low { background: #f1f5f9; color: #64748b; }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7pt;
            color: #94a3b8;
            padding: 10px;
            border-top: 1px solid #e2e8f0;
        }
        .page-number:before {
            content: "第 " counter(page) " 页";
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 60pt;
            color: rgba(99, 102, 241, 0.04);
            z-index: -1;
            font-weight: bold;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="watermark">Project Monitor</div>

    <div class="header">
        <h1>{{ $title }}</h1>
        <p>生成时间：{{ $generatedAt }} &nbsp;|&nbsp; 共 {{ $recordCount }} 条记录</p>
    </div>

    @if(isset($summary) && !empty($summary))
    <div class="summary-section">
        <h2>📊 数据概览</h2>
        <div class="summary-grid">
            @foreach($summary as $item)
            <div class="summary-item">
                <div class="label">{{ $item['label'] }}</div>
                <div class="value">{{ $item['value'] }}</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
            <tr>
                @foreach($row as $cell)
                <td>{!! $cell !!}</td>
                @endforeach
            </tr>
            @empty
            <tr>
                <td colspan="{{ count($headers) }}" style="text-align: center; padding: 30px; color: #94a3b8;">
                    暂无数据
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <span>Project Monitor &mdash; </span>
        <span class="page-number"></span>
    </div>
</body>
</html>
