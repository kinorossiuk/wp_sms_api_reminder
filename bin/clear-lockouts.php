<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$home = getenv('HOME');
if (!is_string($home) || $home === '') {
    fwrite(STDERR, "HOME 경로를 확인할 수 없습니다.\n");
    exit(1);
}

$attemptDir = rtrim($home, '/') . '/.rossi-tools/attempts';
$removed = 0;
foreach (glob($attemptDir . '/*.json') ?: [] as $path) {
    if (is_file($path) && unlink($path)) {
        $removed++;
    }
}

fwrite(STDOUT, "잠금 기록 {$removed}개를 초기화했습니다.\n");
