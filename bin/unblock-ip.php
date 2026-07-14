<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ($argc !== 2) {
    fwrite(STDERR, "사용법: php bin/unblock-ip.php <IP주소>\n");
    exit(1);
}

$ip = trim((string) $argv[1]);
if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
    fwrite(STDERR, "올바른 IPv4 또는 IPv6 주소를 입력해 주세요.\n");
    exit(1);
}

$home = getenv('HOME');
if (!is_string($home) || $home === '') {
    fwrite(STDERR, "HOME 경로를 확인할 수 없습니다.\n");
    exit(1);
}

$path = rtrim($home, '/') . '/.rossi-tools/attempts/' . hash('sha256', $ip) . '.json';
if (!is_file($path)) {
    fwrite(STDOUT, "{$ip}의 차단 기록이 없습니다.\n");
    exit(0);
}

if (!unlink($path)) {
    fwrite(STDERR, "{$ip}의 차단 기록을 삭제할 수 없습니다.\n");
    exit(1);
}

fwrite(STDOUT, "{$ip}의 로그인 실패 및 차단 기록을 해제했습니다.\n");
