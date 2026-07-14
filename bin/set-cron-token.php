<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$home = getenv('HOME');
if (!is_string($home) || $home === '') {
    fwrite(STDERR, "HOME 경로를 확인할 수 없습니다.\n");
    exit(1);
}

$directory = rtrim($home, '/') . '/.rossi-tools';
if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
    fwrite(STDERR, "보안 설정 디렉터리를 만들 수 없습니다.\n");
    exit(1);
}
chmod($directory, 0700);

$token = 'rct_' . bin2hex(random_bytes(32));
$hash = hash('sha256', $token);
$contents = "<?php\nreturn ['token_hash' => " . var_export($hash, true) . "];\n";
$temporary = tempnam($directory, '.cron-token-');
if ($temporary === false || file_put_contents($temporary, $contents, LOCK_EX) === false) {
    fwrite(STDERR, "cron 토큰을 저장할 수 없습니다.\n");
    exit(1);
}
chmod($temporary, 0600);

$destination = $directory . '/cron-token.php';
if (!rename($temporary, $destination)) {
    @unlink($temporary);
    fwrite(STDERR, "cron 토큰을 적용할 수 없습니다.\n");
    exit(1);
}

fwrite(STDOUT, "아래 토큰은 다시 표시되지 않습니다. cron-job.org의 요청 헤더에만 저장하세요.\n\n");
fwrite(STDOUT, $token . "\n\n");
fwrite(STDOUT, "Header name: X-Rossi-Cron-Token\n");
