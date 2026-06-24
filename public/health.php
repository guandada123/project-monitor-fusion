<?php

// ─── Project Monitor — 健康检查 ───
// 用于 Docker HEALTHCHECK / 负载均衡器
http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'app' => 'Project Monitor',
    'time' => date('c'),
    'php' => PHP_VERSION,
]);
