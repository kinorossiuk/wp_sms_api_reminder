<?php
declare(strict_types=1);

const ROSSI_SMS_TIMEZONE = 'Asia/Seoul';
const ROSSI_SMS_CONFIG_FILE = 'sms.php';

final class RossiSmsProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly array $payload = [],
    ) {
        parent::__construct($message, $httpStatus);
    }
}

function rossi_sms_home(): string
{
    $home = getenv('HOME');
    if (is_string($home) && $home !== '') {
        return rtrim($home, '/');
    }

    return dirname(__DIR__, 3);
}

function rossi_sms_security_dir(): string
{
    return rossi_sms_home() . '/.rossi-tools';
}

function rossi_sms_config_path(): string
{
    return rossi_sms_security_dir() . '/' . ROSSI_SMS_CONFIG_FILE;
}

function rossi_sms_is_configured(): bool
{
    return is_file(rossi_sms_config_path());
}

function rossi_sms_raw_config(): array
{
    $path = rossi_sms_config_path();
    if (!is_file($path)) {
        return [];
    }
    $config = require $path;

    return is_array($config) ? $config : [];
}

function rossi_sms_write_config(array $config): void
{
    $directory = rossi_sms_security_dir();
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('SMS 보안 설정 디렉터리를 만들 수 없습니다.');
    }
    @chmod($directory, 0700);
    $contents = "<?php\nreturn " . var_export($config, true) . ";\n";
    $temporary = tempnam($directory, '.sms-');
    if ($temporary === false || file_put_contents($temporary, $contents, LOCK_EX) === false) {
        throw new RuntimeException('SMS 설정을 저장할 수 없습니다.');
    }
    @chmod($temporary, 0600);
    if (!rename($temporary, rossi_sms_config_path())) {
        @unlink($temporary);
        throw new RuntimeException('SMS 설정을 적용할 수 없습니다.');
    }
}

function rossi_sms_update_config(array $input): void
{
    $current = rossi_sms_raw_config();
    $database = is_array($current['database'] ?? null) ? $current['database'] : [];
    $solapi = is_array($current['solapi'] ?? null) ? $current['solapi'] : [];
    $security = is_array($current['security'] ?? null) ? $current['security'] : [];
    $limits = is_array($current['limits'] ?? null) ? $current['limits'] : [];

    foreach (['host', 'name', 'user'] as $key) {
        $value = trim((string) ($input['db_' . $key] ?? ''));
        if ($value !== '') {
            $database[$key] = $value;
        }
    }
    $port = trim((string) ($input['db_port'] ?? ''));
    if ($port !== '') {
        if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
            throw new InvalidArgumentException('MySQL 포트가 올바르지 않습니다.');
        }
        $database['port'] = (int) $port;
    }
    $dbPassword = (string) ($input['db_password'] ?? '');
    if ($dbPassword !== '') {
        $database['password'] = $dbPassword;
    }

    $apiKey = trim((string) ($input['solapi_api_key'] ?? ''));
    if ($apiKey !== '') {
        $solapi['api_key'] = $apiKey;
    }
    $apiSecret = (string) ($input['solapi_api_secret'] ?? '');
    if ($apiSecret !== '') {
        $solapi['api_secret'] = $apiSecret;
    }
    $senderInput = trim((string) ($input['solapi_sender'] ?? ''));
    if ($senderInput !== '') {
        $solapi['sender'] = rossi_sms_normalize_phone($senderInput);
    }

    $allowedInput = trim((string) ($input['allowed_recipients'] ?? ''));
    if ($allowedInput !== '') {
        $allowed = [];
        foreach (explode(',', $allowedInput) as $phone) {
            $allowed[] = rossi_sms_normalize_phone($phone);
        }
        $limits['allowed_recipients'] = array_values(array_unique($allowed));
    }
    $dailyMax = trim((string) ($input['daily_max'] ?? ''));
    if ($dailyMax !== '') {
        if (!ctype_digit($dailyMax) || (int) $dailyMax < 1 || (int) $dailyMax > 1000) {
            throw new InvalidArgumentException('하루 예약 한도는 1~1000 사이여야 합니다.');
        }
        $limits['daily_max'] = (int) $dailyMax;
    }

    $database['port'] = (int) ($database['port'] ?? 3306);
    $security['encryption_key'] = is_string($security['encryption_key'] ?? null)
        ? $security['encryption_key']
        : base64_encode(random_bytes(32));
    $config = ['database' => $database, 'solapi' => $solapi, 'security' => $security, 'limits' => $limits];
    rossi_sms_config_validate($config);
    rossi_sms_write_config($config);
}

function rossi_sms_config(): array
{
    if (!rossi_sms_is_configured()) {
        throw new RuntimeException('SMS 설정이 아직 완료되지 않았습니다.');
    }

    $config = rossi_sms_raw_config();
    rossi_sms_config_validate($config);

    return $config;
}

function rossi_sms_config_validate(array $config): void
{

    foreach (['database', 'solapi', 'security', 'limits'] as $key) {
        if (!is_array($config[$key] ?? null)) {
            throw new RuntimeException('SMS 설정이 불완전합니다.');
        }
    }
    foreach (['host', 'name', 'user', 'password'] as $key) {
        if (!is_string($config['database'][$key] ?? null) || $config['database'][$key] === '') {
            throw new RuntimeException('데이터베이스 설정이 불완전합니다.');
        }
    }
    foreach (['api_key', 'api_secret', 'sender'] as $key) {
        if (!is_string($config['solapi'][$key] ?? null) || $config['solapi'][$key] === '') {
            throw new RuntimeException('SOLAPI 설정이 불완전합니다.');
        }
    }
    if (!is_string($config['security']['encryption_key'] ?? null)) {
        throw new RuntimeException('SMS 암호화 키가 없습니다.');
    }
    if (!function_exists('curl_init') || !function_exists('openssl_encrypt') || !extension_loaded('pdo_mysql')) {
        throw new RuntimeException('이 호스팅에는 SMS에 필요한 PHP 확장이 없습니다.');
    }

    if (!is_array($config['limits']['allowed_recipients'] ?? null) || $config['limits']['allowed_recipients'] === []) {
        throw new RuntimeException('최소 한 개의 허용 수신번호를 설정해 주세요.');
    }
}

function rossi_sms_pdo(array $config): PDO
{
    $database = $config['database'];
    $port = (int) ($database['port'] ?? 3306);
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $database['host'],
        $port,
        $database['name']
    );

    return new PDO($dsn, $database['user'], $database['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function rossi_sms_key(array $config): string
{
    $key = base64_decode((string) $config['security']['encryption_key'], true);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('SMS 암호화 키가 올바르지 않습니다.');
    }

    return $key;
}

function rossi_sms_encrypt(string $plainText, array $config): string
{
    $iv = random_bytes(12);
    $tag = '';
    $cipherText = openssl_encrypt(
        $plainText,
        'aes-256-gcm',
        rossi_sms_key($config),
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );
    if ($cipherText === false) {
        throw new RuntimeException('SMS 데이터를 암호화할 수 없습니다.');
    }

    return base64_encode($iv . $tag . $cipherText);
}

function rossi_sms_decrypt(string $encoded, array $config): string
{
    $payload = base64_decode($encoded, true);
    if ($payload === false || strlen($payload) < 29) {
        throw new RuntimeException('암호화된 SMS 데이터가 올바르지 않습니다.');
    }
    $iv = substr($payload, 0, 12);
    $tag = substr($payload, 12, 16);
    $cipherText = substr($payload, 28);
    $plainText = openssl_decrypt(
        $cipherText,
        'aes-256-gcm',
        rossi_sms_key($config),
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );
    if ($plainText === false) {
        throw new RuntimeException('암호화된 SMS 데이터를 읽을 수 없습니다.');
    }

    return $plainText;
}

function rossi_sms_normalize_phone(string $value): string
{
    $phone = preg_replace('/\D+/', '', $value) ?? '';
    if (!preg_match('/^\d{8,12}$/', $phone)) {
        throw new InvalidArgumentException('수신번호 형식이 올바르지 않습니다.');
    }

    return $phone;
}

function rossi_sms_message_units(string $text): int
{
    $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if ($characters === false) {
        throw new InvalidArgumentException('메시지 인코딩이 올바르지 않습니다.');
    }
    $units = 0;
    foreach ($characters as $character) {
        $units += ord($character[0]) <= 0x7f ? 1 : 2;
    }

    return $units;
}

function rossi_sms_message_type(string $text): string
{
    return rossi_sms_message_units($text) <= 90 ? 'SMS' : 'LMS';
}

function rossi_sms_validate_message(string $text): string
{
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
    if ($text === '') {
        throw new InvalidArgumentException('메시지 내용을 입력해 주세요.');
    }
    if (rossi_sms_message_units($text) > 2000) {
        throw new InvalidArgumentException('메시지는 LMS 기준 2,000바이트 이하여야 합니다.');
    }

    return $text;
}

function rossi_sms_validate_schedule(string $value): DateTimeImmutable
{
    $timezone = new DateTimeZone(ROSSI_SMS_TIMEZONE);
    $format = strlen($value) === 19 ? 'Y-m-d\TH:i:s' : 'Y-m-d\TH:i';
    $date = DateTimeImmutable::createFromFormat($format, $value, $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        throw new InvalidArgumentException('예약 시각이 올바르지 않습니다.');
    }
    $now = new DateTimeImmutable('now', $timezone);
    if ($date < $now->modify('+1 minute')) {
        throw new InvalidArgumentException('예약 시각은 현재부터 1분 이후로 설정해 주세요.');
    }
    if ($date > $now->modify('+6 months')) {
        throw new InvalidArgumentException('예약은 최대 6개월 이내로 설정할 수 있습니다.');
    }

    return $date;
}

function rossi_sms_allowed_recipient(string $phone, array $config): bool
{
    $allowed = $config['limits']['allowed_recipients'] ?? [];
    if (!is_array($allowed) || $allowed === []) {
        return false;
    }
    foreach ($allowed as $candidate) {
        if (is_string($candidate) && hash_equals(rossi_sms_normalize_phone($candidate), $phone)) {
            return true;
        }
    }

    return false;
}

function rossi_sms_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
}

function rossi_sms_provider_request(array $config, string $method, string $path, ?array $body = null): array
{
    $date = gmdate('Y-m-d\TH:i:s\Z');
    $salt = bin2hex(random_bytes(16));
    $signature = hash_hmac('sha256', $date . $salt, $config['solapi']['api_secret']);
    $authorization = sprintf(
        'HMAC-SHA256 apiKey=%s, date=%s, salt=%s, signature=%s',
        $config['solapi']['api_key'],
        $date,
        $salt,
        $signature
    );
    $url = 'https://api.solapi.com' . $path;
    $encodedBody = $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $curl = curl_init();
    if ($curl === false) {
        throw new RossiSmsProviderException('SOLAPI 연결을 시작할 수 없습니다.');
    }

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => array_filter([
            'Authorization: ' . $authorization,
            'Accept: application/json',
            $encodedBody === null ? null : 'Content-Type: application/json',
        ]),
    ]);
    if ($encodedBody !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $encodedBody);
    }

    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($response === false) {
        throw new RossiSmsProviderException('SOLAPI 연결 실패: ' . $curlError);
    }
    $decoded = json_decode($response, true);
    $payload = is_array($decoded) ? $decoded : [];
    if ($httpStatus < 200 || $httpStatus >= 300) {
        $message = (string) ($payload['message'] ?? $payload['errorMessage'] ?? 'SOLAPI 요청이 거부되었습니다.');
        throw new RossiSmsProviderException($message, $httpStatus, $payload);
    }

    return $payload;
}

function rossi_sms_account_summary(array $config): array
{
    $balance = rossi_sms_provider_request($config, 'GET', '/cash/v1/balance');
    $pricing = rossi_sms_provider_request($config, 'GET', '/pricing/v1/messaging?countryId=82&serviceMethod=MT');
    $available = max(0.0, (float) ($balance['balance'] ?? 0));
    $smsPrice = max(0.0, (float) ($pricing['sms'] ?? 0));
    $lmsPrice = max(0.0, (float) ($pricing['lms'] ?? 0));

    return [
        'balance' => $available,
        'point' => max(0.0, (float) ($balance['point'] ?? 0)),
        'sms_price' => $smsPrice,
        'lms_price' => $lmsPrice,
        'sms_count' => $smsPrice > 0 ? (int) floor($available / $smsPrice) : null,
        'lms_count' => $lmsPrice > 0 ? (int) floor($available / $lmsPrice) : null,
    ];
}

function rossi_sms_insert_creating(PDO $pdo, array $config, string $phone, string $message, DateTimeImmutable $scheduledAt): array
{
    $dailyLimit = max(1, (int) ($config['limits']['daily_max'] ?? 20));
    $today = (new DateTimeImmutable('now', new DateTimeZone(ROSSI_SMS_TIMEZONE)))->setTime(0, 0);
    $tomorrow = $today->modify('+1 day');
    $countStatement = $pdo->prepare(
        'SELECT COUNT(*) FROM sms_schedules WHERE created_at >= :from AND created_at < :to AND status NOT IN (\'FAILED\', \'CANCELLED\')'
    );
    $countStatement->execute([
        ':from' => $today->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ':to' => $tomorrow->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
    ]);
    if ((int) $countStatement->fetchColumn() >= $dailyLimit) {
        throw new RuntimeException('오늘의 SMS 예약 한도에 도달했습니다.');
    }

    $publicId = rossi_sms_uuid();
    $nowUtc = gmdate('Y-m-d H:i:s');
    $statement = $pdo->prepare(
        'INSERT INTO sms_schedules (public_id, recipient_ciphertext, recipient_last4, message_ciphertext, message_units, message_type, scheduled_at_utc, scheduled_at_kst, status, created_at, updated_at) VALUES (:public_id, :recipient_ciphertext, :recipient_last4, :message_ciphertext, :message_units, :message_type, :scheduled_at_utc, :scheduled_at_kst, \'CREATING\', :created_at, :updated_at)'
    );
    $statement->execute([
        ':public_id' => $publicId,
        ':recipient_ciphertext' => rossi_sms_encrypt($phone, $config),
        ':recipient_last4' => substr($phone, -4),
        ':message_ciphertext' => rossi_sms_encrypt($message, $config),
        ':message_units' => rossi_sms_message_units($message),
        ':message_type' => rossi_sms_message_type($message),
        ':scheduled_at_utc' => $scheduledAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ':scheduled_at_kst' => $scheduledAt->format('Y-m-d H:i:s'),
        ':created_at' => $nowUtc,
        ':updated_at' => $nowUtc,
    ]);

    return ['id' => (int) $pdo->lastInsertId(), 'public_id' => $publicId];
}

function rossi_sms_schedule(array $config, string $phoneValue, string $messageValue, string $scheduledValue): string
{
    return rossi_sms_dispatch($config, $phoneValue, $messageValue, rossi_sms_validate_schedule($scheduledValue));
}

function rossi_sms_send_now(array $config, string $phoneValue, string $messageValue): string
{
    return rossi_sms_dispatch($config, $phoneValue, $messageValue, null);
}

function rossi_sms_dispatch(array $config, string $phoneValue, string $messageValue, ?DateTimeImmutable $scheduledAt): string
{
    $phone = rossi_sms_normalize_phone($phoneValue);
    $message = rossi_sms_validate_message($messageValue);
    $sender = rossi_sms_normalize_phone((string) $config['solapi']['sender']);
    if (!rossi_sms_allowed_recipient($phone, $config)) {
        throw new RuntimeException('허용된 수신번호로만 예약할 수 있습니다.');
    }

    $pdo = rossi_sms_pdo($config);
    $record = rossi_sms_insert_creating(
        $pdo,
        $config,
        $phone,
        $message,
        $scheduledAt ?? new DateTimeImmutable('now', new DateTimeZone(ROSSI_SMS_TIMEZONE))
    );
    try {
        $payload = [
            'messages' => [[
                'to' => $phone,
                'from' => $sender,
                'text' => $message,
                'autoTypeDetect' => true,
            ]],
            'strict' => true,
            'allowDuplicates' => false,
        ];
        if ($scheduledAt !== null) {
            $payload['scheduledDate'] = $scheduledAt->format(DateTimeInterface::ATOM);
        }
        $response = rossi_sms_provider_request($config, 'POST', '/messages/v4/send-many/detail', $payload);
        $group = is_array($response['groupInfo'] ?? null) ? $response['groupInfo'] : [];
        $messages = is_array($response['messageList'] ?? null) ? $response['messageList'] : [];
        $firstMessage = $messages === [] ? [] : reset($messages);
        if (!is_array($firstMessage) || empty($group['groupId'])) {
            throw new RossiSmsProviderException('SOLAPI가 예약 접수 정보를 반환하지 않았습니다.', 200, $response);
        }
        $statement = $pdo->prepare(
            'UPDATE sms_schedules SET status = :status, provider_group_id = :group_id, provider_message_id = :message_id, provider_status = :provider_status, updated_at = :updated_at WHERE id = :id'
        );
        $statement->execute([
            ':status' => $scheduledAt === null ? 'SENDING' : 'SCHEDULED',
            ':group_id' => (string) $group['groupId'],
            ':message_id' => (string) ($firstMessage['messageId'] ?? ''),
            ':provider_status' => (string) ($group['status'] ?? 'SCHEDULED'),
            ':updated_at' => gmdate('Y-m-d H:i:s'),
            ':id' => $record['id'],
        ]);
    } catch (RossiSmsProviderException $error) {
        $status = $error->httpStatus === 0 ? 'UNKNOWN' : 'FAILED';
        $statement = $pdo->prepare(
            'UPDATE sms_schedules SET status = :status, error_code = :error_code, error_message = :error_message, updated_at = :updated_at WHERE id = :id'
        );
        $statement->execute([
            ':status' => $status,
            ':error_code' => $error->httpStatus === 0 ? 'NETWORK_UNKNOWN' : (string) $error->httpStatus,
            ':error_message' => substr($error->getMessage(), 0, 500),
            ':updated_at' => gmdate('Y-m-d H:i:s'),
            ':id' => $record['id'],
        ]);
        throw new RuntimeException($status === 'UNKNOWN' ? 'SOLAPI 접수 여부를 확인할 수 없습니다. 중복 발송 방지를 위해 자동 재시도하지 않았습니다.' : 'SOLAPI 예약 등록에 실패했습니다: ' . $error->getMessage());
    }

    return $record['public_id'];
}

function rossi_sms_list(array $config, int $limit = 50): array
{
    $pdo = rossi_sms_pdo($config);
    $statement = $pdo->prepare('SELECT * FROM sms_schedules ORDER BY scheduled_at_utc DESC LIMIT :limit');
    $statement->bindValue(':limit', max(1, min($limit, 100)), PDO::PARAM_INT);
    $statement->execute();
    $rows = $statement->fetchAll();
    foreach ($rows as &$row) {
        try {
            $row['recipient'] = rossi_sms_decrypt((string) $row['recipient_ciphertext'], $config);
            $row['message'] = rossi_sms_decrypt((string) $row['message_ciphertext'], $config);
        } catch (Throwable) {
            $row['recipient'] = '복호화 오류';
            $row['message'] = '';
        }
    }
    unset($row);

    return $rows;
}

function rossi_sms_cancel(array $config, string $publicId): void
{
    if (!preg_match('/^[a-f0-9-]{36}$/', $publicId)) {
        throw new InvalidArgumentException('예약 식별자가 올바르지 않습니다.');
    }
    $pdo = rossi_sms_pdo($config);
    $statement = $pdo->prepare('SELECT * FROM sms_schedules WHERE public_id = :public_id LIMIT 1');
    $statement->execute([':public_id' => $publicId]);
    $row = $statement->fetch();
    if (!is_array($row)) {
        throw new RuntimeException('예약 정보를 찾을 수 없습니다.');
    }
    if ((string) $row['status'] !== 'SCHEDULED' || (string) $row['provider_group_id'] === '') {
        throw new RuntimeException('현재 취소할 수 있는 예약 상태가 아닙니다.');
    }

    try {
        rossi_sms_provider_request($config, 'DELETE', '/messages/v4/groups/' . rawurlencode((string) $row['provider_group_id']) . '/schedule');
        $update = $pdo->prepare('UPDATE sms_schedules SET status = \'CANCELLED\', provider_status = \'PENDING\', cancelled_at = :cancelled_at, updated_at = :updated_at WHERE id = :id');
        $update->execute([':cancelled_at' => gmdate('Y-m-d H:i:s'), ':updated_at' => gmdate('Y-m-d H:i:s'), ':id' => $row['id']]);
    } catch (RossiSmsProviderException $error) {
        $update = $pdo->prepare('UPDATE sms_schedules SET status = \'UNKNOWN\', error_code = :error_code, error_message = :error_message, updated_at = :updated_at WHERE id = :id');
        $update->execute([':error_code' => $error->httpStatus === 0 ? 'CANCEL_UNKNOWN' : (string) $error->httpStatus, ':error_message' => substr($error->getMessage(), 0, 500), ':updated_at' => gmdate('Y-m-d H:i:s'), ':id' => $row['id']]);
        throw new RuntimeException('SOLAPI 취소 결과를 확인할 수 없습니다. SOLAPI 콘솔에서 상태를 확인해 주세요.');
    }
}

function rossi_sms_sync_statuses(array $config, int $limit = 5): array
{
    $pdo = rossi_sms_pdo($config);
    $statement = $pdo->prepare("SELECT * FROM sms_schedules WHERE status IN ('SCHEDULED', 'SENDING', 'UNKNOWN') AND provider_group_id IS NOT NULL AND provider_group_id <> '' ORDER BY updated_at ASC LIMIT :limit");
    $statement->bindValue(':limit', max(1, min($limit, 10)), PDO::PARAM_INT);
    $statement->execute();
    $rows = $statement->fetchAll();
    $updated = 0;
    foreach ($rows as $row) {
        try {
            $group = rossi_sms_provider_request($config, 'GET', '/messages/v4/groups/' . rawurlencode((string) $row['provider_group_id']));
            $providerStatus = (string) ($group['status'] ?? 'UNKNOWN');
            $status = match ($providerStatus) {
                'SCHEDULED' => 'SCHEDULED',
                'PENDING', 'SENDING', 'PROCESSING' => 'SENDING',
                'COMPLETE' => ((int) ($group['count']['sentFailed'] ?? 0) > 0 ? 'PARTIAL_FAILED' : 'COMPLETE'),
                'FAILED', 'SYSTEM-ERROR' => 'FAILED',
                default => 'UNKNOWN',
            };
            $update = $pdo->prepare('UPDATE sms_schedules SET status = :status, provider_status = :provider_status, completed_at = :completed_at, updated_at = :updated_at WHERE id = :id');
            $update->execute([
                ':status' => $status,
                ':provider_status' => $providerStatus,
                ':completed_at' => in_array($status, ['COMPLETE', 'PARTIAL_FAILED', 'FAILED'], true) ? gmdate('Y-m-d H:i:s') : null,
                ':updated_at' => gmdate('Y-m-d H:i:s'),
                ':id' => $row['id'],
            ]);
            $updated++;
        } catch (Throwable) {
            // 다음 heartbeat에서 다시 확인합니다. 비밀 정보는 로그에 남기지 않습니다.
        }
    }

    return ['checked' => count($rows), 'updated' => $updated];
}
