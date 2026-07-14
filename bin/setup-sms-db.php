<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__) . '/app/sms.php';

try {
    $config = rossi_sms_config();
    $pdo = rossi_sms_pdo($config);
    $schema = file_get_contents(dirname(__DIR__) . '/database/sms_schema.sql');
    if ($schema === false) {
        throw new RuntimeException('SMS 스키마 파일을 읽을 수 없습니다.');
    }
    $pdo->exec($schema);
    fwrite(STDOUT, "SMS 데이터베이스 테이블이 준비되었습니다.\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
