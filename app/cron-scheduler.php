<?php
declare(strict_types=1);

const ROSSI_CRON_TIMEZONE = 'Asia/Seoul';
const ROSSI_CRON_MAX_RUNTIME_SECONDS = 20;

function rossi_cron_home(): string
{
    $home = getenv('HOME');
    if (is_string($home) && $home !== '') {
        return rtrim($home, '/');
    }

    return dirname(__DIR__, 3);
}

function rossi_cron_private_dir(): string
{
    return rossi_cron_home() . '/.rossi-tools';
}

function rossi_cron_prepare_private_dir(): bool
{
    $directory = rossi_cron_private_dir();
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        return false;
    }

    @chmod($directory, 0700);
    return is_writable($directory);
}

function rossi_cron_log(string $message): void
{
    if (!rossi_cron_prepare_private_dir()) {
        return;
    }

    $message = str_replace(["\r", "\n"], ' ', $message);
    $line = sprintf(
        "[%s] %s\n",
        (new DateTimeImmutable('now', new DateTimeZone(ROSSI_CRON_TIMEZONE)))->format(DateTimeInterface::ATOM),
        substr($message, 0, 1000)
    );
    $path = rossi_cron_private_dir() . '/cron-scheduler.log';
    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    @chmod($path, 0600);
}

function rossi_cron_read_json(string $path): array
{
    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return [];
    }

    @flock($handle, LOCK_SH);
    $contents = stream_get_contents($handle);
    @flock($handle, LOCK_UN);
    fclose($handle);
    $decoded = json_decode((string) $contents, true);

    return is_array($decoded) ? $decoded : [];
}

function rossi_cron_write_json(string $path, array $data): bool
{
    $directory = dirname($path);
    $temporary = tempnam($directory, '.cron-');
    if ($temporary === false) {
        return false;
    }

    try {
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write temporary state.');
        }
        @chmod($temporary, 0600);
        if (!rename($temporary, $path)) {
            throw new RuntimeException('Unable to publish state.');
        }
    } catch (Throwable) {
        @unlink($temporary);
        return false;
    }

    return true;
}

function rossi_cron_tasks(): array
{
    return [
        'health' => [
            'schedule' => ['type' => 'interval', 'seconds' => 300],
            'retry_seconds' => 60,
            'handler' => 'rossi_cron_task_health',
        ],
        'sms-status-sync' => [
            'schedule' => ['type' => 'interval', 'seconds' => 300],
            'retry_seconds' => 120,
            'handler' => 'rossi_cron_task_sms_status_sync',
        ],
        'log-maintenance' => [
            'schedule' => ['type' => 'daily', 'at' => '03:20'],
            'retry_seconds' => 300,
            'handler' => 'rossi_cron_task_log_maintenance',
        ],
    ];
}

function rossi_cron_task_is_due(array $task, array $state, DateTimeImmutable $now): bool
{
    $lastStarted = (int) ($state['last_started_at'] ?? 0);
    $retrySeconds = max(60, (int) ($task['retry_seconds'] ?? 300));
    if ($lastStarted > 0 && $now->getTimestamp() - $lastStarted < $retrySeconds) {
        return false;
    }

    $lastSuccess = (int) ($state['last_success_at'] ?? 0);
    $schedule = is_array($task['schedule'] ?? null) ? $task['schedule'] : [];
    if (($schedule['type'] ?? '') === 'interval') {
        $seconds = max(60, (int) ($schedule['seconds'] ?? 300));
        return $lastSuccess === 0 || $now->getTimestamp() - $lastSuccess >= $seconds;
    }

    if (($schedule['type'] ?? '') === 'daily') {
        $at = (string) ($schedule['at'] ?? '00:00');
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $at)) {
            return false;
        }
        [$hour, $minute] = array_map('intval', explode(':', $at));
        $scheduled = $now->setTime($hour, $minute, 0);
        return $now >= $scheduled && $lastSuccess < $scheduled->getTimestamp();
    }

    return false;
}

function rossi_cron_run_due_tasks(bool $force = false): array
{
    if (!rossi_cron_prepare_private_dir()) {
        throw new RuntimeException('Private scheduler directory is unavailable.');
    }

    $lockPath = rossi_cron_private_dir() . '/cron-scheduler.lock';
    $lock = @fopen($lockPath, 'c+');
    if ($lock === false) {
        throw new RuntimeException('Scheduler lock is unavailable.');
    }
    @chmod($lockPath, 0600);
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return ['status' => 'busy', 'ran' => [], 'failed' => []];
    }

    $statePath = rossi_cron_private_dir() . '/cron-state.json';
    $allState = rossi_cron_read_json($statePath);
    $taskState = is_array($allState['tasks'] ?? null) ? $allState['tasks'] : [];
    $now = new DateTimeImmutable('now', new DateTimeZone(ROSSI_CRON_TIMEZONE));
    $startedAt = microtime(true);
    $ran = [];
    $failed = [];

    foreach (rossi_cron_tasks() as $name => $task) {
        $currentState = is_array($taskState[$name] ?? null) ? $taskState[$name] : [];
        if (!$force && !rossi_cron_task_is_due($task, $currentState, $now)) {
            continue;
        }
        if (microtime(true) - $startedAt >= ROSSI_CRON_MAX_RUNTIME_SECONDS) {
            rossi_cron_log('Runtime budget reached before task: ' . $name);
            break;
        }

        $attemptedAt = time();
        $currentState['last_started_at'] = $attemptedAt;
        $currentState['attempts'] = (int) ($currentState['attempts'] ?? 0) + 1;
        $taskState[$name] = $currentState;
        $allState['tasks'] = $taskState;
        if (!rossi_cron_write_json($statePath, $allState)) {
            $failed[] = $name;
            rossi_cron_log('Unable to save pre-run state for task: ' . $name);
            continue;
        }

        try {
            $handler = $task['handler'] ?? null;
            if (!is_string($handler) || !is_callable($handler)) {
                throw new RuntimeException('Task handler is not callable.');
            }
            $handler($now);
            $currentState['last_success_at'] = time();
            $currentState['last_error'] = null;
            $ran[] = $name;
        } catch (Throwable $error) {
            $currentState['last_error_at'] = time();
            $currentState['last_error'] = substr($error->getMessage(), 0, 500);
            $failed[] = $name;
            rossi_cron_log('Task failed [' . $name . ']: ' . $error->getMessage());
        }
        $taskState[$name] = $currentState;
    }

    $allState = [
        'schema' => 1,
        'updated_at' => time(),
        'tasks' => $taskState,
    ];
    if (!rossi_cron_write_json($statePath, $allState)) {
        rossi_cron_log('Unable to save scheduler state.');
    }

    flock($lock, LOCK_UN);
    fclose($lock);

    return ['status' => $failed === [] ? 'ok' : 'error', 'ran' => $ran, 'failed' => $failed];
}

function rossi_cron_task_health(DateTimeImmutable $now): void
{
    $status = [
        'schema' => 1,
        'status' => 'ok',
        'source' => 'external-heartbeat',
        'checked_at' => $now->format(DateTimeInterface::ATOM),
        'checked_at_unix' => $now->getTimestamp(),
        'php_version' => PHP_VERSION,
    ];
    if (!rossi_cron_write_json(rossi_cron_private_dir() . '/cron-status.json', $status)) {
        throw new RuntimeException('Unable to update scheduler health status.');
    }
}

function rossi_cron_task_log_maintenance(DateTimeImmutable $now): void
{
    $maximumBytes = 1024 * 1024;
    $keepBytes = 256 * 1024;
    $logs = [
        rossi_cron_private_dir() . '/cron-scheduler.log',
    ];

    foreach ($logs as $log) {
        clearstatcache(true, $log);
        $size = is_file($log) ? filesize($log) : false;
        if ($size === false || $size <= $maximumBytes) {
            continue;
        }
        $handle = fopen($log, 'rb');
        if ($handle === false || fseek($handle, -$keepBytes, SEEK_END) !== 0) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('Unable to read scheduler log: ' . basename($log));
        }
        $tail = stream_get_contents($handle);
        fclose($handle);
        if ($tail === false || file_put_contents($log, $tail, LOCK_EX) === false) {
            throw new RuntimeException('Unable to trim scheduler log: ' . basename($log));
        }
        @chmod($log, 0600);
    }
}

function rossi_cron_task_sms_status_sync(DateTimeImmutable $now): void
{
    require_once __DIR__ . '/sms.php';
    if (!rossi_sms_is_configured()) {
        return;
    }
    try {
        $result = rossi_sms_sync_statuses(rossi_sms_config());
        rossi_cron_write_json(rossi_cron_private_dir() . '/sms-sync-status.json', [
            'schema' => 1,
            'checked_at' => $now->format(DateTimeInterface::ATOM),
            'checked' => $result['checked'],
            'updated' => $result['updated'],
        ]);
    } catch (Throwable $error) {
        rossi_cron_log('SMS status sync skipped: ' . substr($error->getMessage(), 0, 300));
    }
}

function rossi_cron_token_is_valid(string $provided): bool
{
    if (strlen($provided) < 32 || strlen($provided) > 256) {
        return false;
    }
    $path = rossi_cron_private_dir() . '/cron-token.php';
    if (!is_file($path)) {
        return false;
    }
    $config = require $path;
    $expected = is_array($config) ? (string) ($config['token_hash'] ?? '') : '';

    return preg_match('/^[a-f0-9]{64}$/', $expected) === 1
        && hash_equals($expected, hash('sha256', $provided));
}

function rossi_cron_handle_http_request(): never
{
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Content-Type: text/plain; charset=utf-8');

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $token = (string) ($_SERVER['HTTP_X_ROSSI_CRON_TOKEN'] ?? '');
    if ($method !== 'POST' || $length > 1024 || !rossi_cron_token_is_valid($token)) {
        http_response_code(404);
        exit;
    }

    try {
        $result = rossi_cron_run_due_tasks();
        if (($result['status'] ?? '') === 'error') {
            http_response_code(500);
            exit;
        }
        http_response_code(204);
    } catch (Throwable $error) {
        rossi_cron_log('Scheduler request failed: ' . $error->getMessage());
        http_response_code(500);
    }
    exit;
}
