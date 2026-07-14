<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$home = getenv('HOME');
if (!is_string($home) || $home === '') {
    fwrite(STDERR, "HOME is not available.\n");
    exit(1);
}

$privateDir = $home . '/.rossi-tools';
if (!is_dir($privateDir) && !mkdir($privateDir, 0700, true) && !is_dir($privateDir)) {
    fwrite(STDERR, "Unable to create the private scheduler directory.\n");
    exit(1);
}
chmod($privateDir, 0700);

$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul'));
$payload = json_encode([
    'schema' => 1,
    'status' => 'ok',
    'checked_at' => $now->format(DateTimeInterface::ATOM),
    'checked_at_unix' => $now->getTimestamp(),
    'php_version' => PHP_VERSION,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$statusFile = $privateDir . '/cron-status.json';
$temporaryFile = tempnam($privateDir, '.cron-status-');
if ($temporaryFile === false) {
    fwrite(STDERR, "Unable to create the scheduler status file.\n");
    exit(1);
}

try {
    if (file_put_contents($temporaryFile, $payload . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write the scheduler status file.');
    }
    chmod($temporaryFile, 0600);
    if (!rename($temporaryFile, $statusFile)) {
        throw new RuntimeException('Unable to publish the scheduler status file.');
    }
} catch (Throwable $error) {
    @unlink($temporaryFile);
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'Scheduler health updated at ' . $now->format(DateTimeInterface::ATOM) . PHP_EOL);
