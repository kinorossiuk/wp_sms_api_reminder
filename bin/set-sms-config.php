<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

function prompt_sms(string $label, bool $hidden = false): string
{
    fwrite(STDOUT, $label);
    if (!$hidden) {
        return trim((string) fgets(STDIN));
    }
    $stty = trim((string) shell_exec('stty -g'));
    if ($stty === '') {
        fwrite(STDERR, "\n입력을 안전하게 숨길 수 없습니다.\n");
        exit(1);
    }
    shell_exec('stty -echo');
    $value = rtrim((string) fgets(STDIN), "\r\n");
    shell_exec('stty ' . escapeshellarg($stty));
    fwrite(STDOUT, "\n");

    return $value;
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

$host = prompt_sms('MySQL 호스트 [localhost]: ') ?: 'localhost';
$port = prompt_sms('MySQL 포트 [3306]: ') ?: '3306';
$name = prompt_sms('MySQL 데이터베이스명: ');
$user = prompt_sms('MySQL 사용자명: ');
$password = prompt_sms('MySQL 비밀번호: ', true);
$apiKey = prompt_sms('SOLAPI API Key: ', true);
$apiSecret = prompt_sms('SOLAPI API Secret: ', true);
$sender = preg_replace('/\D+/', '', prompt_sms('SOLAPI 활성 발신번호: ')) ?? '';
$allowedRaw = prompt_sms('허용 수신번호(쉼표로 구분, 최소 1개): ');
$allowed = array_values(array_filter(array_map(static fn (string $value): string => preg_replace('/\D+/', '', trim($value)) ?? '', explode(',', $allowedRaw))));
$dailyMax = (int) (prompt_sms('하루 예약 한도 [20]: ') ?: '20');

if ($name === '' || $user === '' || $password === '' || $apiKey === '' || $apiSecret === '' || !preg_match('/^\d{8,12}$/', $sender) || $allowed === [] || $dailyMax < 1 || !ctype_digit($port)) {
    fwrite(STDERR, "입력값을 다시 확인해 주세요.\n");
    exit(1);
}
foreach ($allowed as $phone) {
    if (!preg_match('/^\d{8,12}$/', $phone)) {
        fwrite(STDERR, "허용 수신번호 형식이 올바르지 않습니다.\n");
        exit(1);
    }
}

$config = [
    'database' => ['host' => $host, 'port' => (int) $port, 'name' => $name, 'user' => $user, 'password' => $password],
    'solapi' => ['api_key' => $apiKey, 'api_secret' => $apiSecret, 'sender' => $sender],
    'security' => ['encryption_key' => base64_encode(random_bytes(32))],
    'limits' => ['daily_max' => $dailyMax, 'allowed_recipients' => $allowed],
];
$contents = "<?php\nreturn " . var_export($config, true) . ";\n";
$temporary = tempnam($directory, '.sms-');
if ($temporary === false || file_put_contents($temporary, $contents, LOCK_EX) === false) {
    fwrite(STDERR, "SMS 설정을 저장할 수 없습니다.\n");
    exit(1);
}
chmod($temporary, 0600);
if (!rename($temporary, $directory . '/sms.php')) {
    @unlink($temporary);
    fwrite(STDERR, "SMS 설정을 적용할 수 없습니다.\n");
    exit(1);
}
fwrite(STDOUT, "SMS 비공개 설정이 저장되었습니다. 다음으로 setup-sms-db.php를 실행하세요.\n");
