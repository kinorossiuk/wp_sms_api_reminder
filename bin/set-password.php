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

function read_hidden(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    $stty = trim((string) shell_exec('stty -g'));
    if ($stty === '') {
        fwrite(STDERR, "\n터미널 입력을 안전하게 숨길 수 없습니다.\n");
        exit(1);
    }

    shell_exec('stty -echo');
    $value = rtrim((string) fgets(STDIN), "\r\n");
    shell_exec('stty ' . escapeshellarg($stty));
    fwrite(STDOUT, "\n");

    return $value;
}

$password = read_hidden('새 사이트 비밀번호: ');
$confirmation = read_hidden('비밀번호 확인: ');

if ($password !== $confirmation) {
    fwrite(STDERR, "비밀번호가 일치하지 않습니다.\n");
    exit(1);
}

if (strlen($password) < 12) {
    fwrite(STDERR, "비밀번호는 12자 이상이어야 합니다.\n");
    exit(1);
}

$directory = rtrim($home, '/') . '/.rossi-tools';
if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
    fwrite(STDERR, "보안 설정 디렉터리를 만들 수 없습니다.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$contents = "<?php\nreturn ['password_hash' => " . var_export($hash, true) . "];\n";
$temporary = tempnam($directory, '.security-');
if ($temporary === false || file_put_contents($temporary, $contents, LOCK_EX) === false) {
    fwrite(STDERR, "보안 설정을 저장할 수 없습니다.\n");
    exit(1);
}

chmod($temporary, 0600);
$destination = $directory . '/security.php';
if (!rename($temporary, $destination)) {
    @unlink($temporary);
    fwrite(STDERR, "보안 설정을 적용할 수 없습니다.\n");
    exit(1);
}

fwrite(STDOUT, "사이트 비밀번호가 설정되었습니다.\n");
