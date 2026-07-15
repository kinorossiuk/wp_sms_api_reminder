<?php
declare(strict_types=1);

const ROSSI_MAX_ATTEMPTS = 5;
const ROSSI_ATTEMPT_WINDOW = 900;
const ROSSI_BLOCK_SECONDS = 1800;
const ROSSI_MAX_LOCKOUTS = 3;
const ROSSI_LOCKOUT_WINDOW = 604800;
const ROSSI_MAX_PROBES = 2;
const ROSSI_PROBE_WINDOW = 600;
const ROSSI_SESSION_SECONDS = 43200;

function rossi_security_dir(): string
{
    $configured = getenv('ROSSI_TOOLS_SECURITY_DIR');
    if (is_string($configured) && $configured !== '') {
        return rtrim($configured, '/');
    }

    $home = getenv('HOME');
    if (!is_string($home) || $home === '') {
        $home = dirname(__DIR__, 3);
    }

    return rtrim($home, '/') . '/.rossi-tools';
}

function rossi_read_attempt(string $path): array
{
    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return [];
    }

    flock($handle, LOCK_SH);
    $contents = stream_get_contents($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    $decoded = json_decode((string) $contents, true);
    return is_array($decoded) ? $decoded : [];
}

function rossi_record_failure(string $path, int $now): ?array
{
    $handle = @fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        return null;
    }

    rewind($handle);
    $decoded = json_decode((string) stream_get_contents($handle), true);
    $state = is_array($decoded) ? $decoded : [];
    $windowStarted = (int) ($state['window_started'] ?? 0);
    $count = (int) ($state['count'] ?? 0);
    if ($windowStarted === 0 || $now - $windowStarted > ROSSI_ATTEMPT_WINDOW) {
        $windowStarted = $now;
        $count = 0;
    }

    $count++;
    $blockedUntil = 0;
    $permanentlyBlocked = !empty($state['permanently_blocked']);
    $lockoutCount = (int) ($state['lockout_count'] ?? 0);
    $lockoutWindowStarted = (int) ($state['lockout_window_started'] ?? 0);

    if ($count >= ROSSI_MAX_ATTEMPTS) {
        if ($lockoutWindowStarted === 0 || $now - $lockoutWindowStarted > ROSSI_LOCKOUT_WINDOW) {
            $lockoutWindowStarted = $now;
            $lockoutCount = 0;
        }

        $lockoutCount++;
        $permanentlyBlocked = $lockoutCount >= ROSSI_MAX_LOCKOUTS;
        $blockedUntil = $permanentlyBlocked ? 0 : $now + ROSSI_BLOCK_SECONDS;
    }

    $state['count'] = $count;
    $state['window_started'] = $windowStarted;
    $state['blocked_until'] = $blockedUntil;
    $state['lockout_count'] = $lockoutCount;
    $state['lockout_window_started'] = $lockoutWindowStarted;
    $state['permanently_blocked'] = $permanentlyBlocked;
    $state['updated_at'] = $now;

    ftruncate($handle, 0);
    rewind($handle);
    $written = fwrite($handle, json_encode($state, JSON_UNESCAPED_SLASHES));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    @chmod($path, 0600);

    return $written === false ? null : $state;
}

function rossi_record_probe(string $path, int $now, string $requestTarget): ?array
{
    $handle = @fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        return null;
    }

    rewind($handle);
    $decoded = json_decode((string) stream_get_contents($handle), true);
    $state = is_array($decoded) ? $decoded : [];
    $probeWindowStarted = (int) ($state['probe_window_started'] ?? 0);
    $probeCount = (int) ($state['probe_count'] ?? 0);
    if ($probeWindowStarted === 0 || $now - $probeWindowStarted > ROSSI_PROBE_WINDOW) {
        $probeWindowStarted = $now;
        $probeCount = 0;
    }

    $probeCount++;
    $state['probe_count'] = $probeCount;
    $state['probe_window_started'] = $probeWindowStarted;
    $state['last_probe'] = substr($requestTarget, 0, 200);
    $state['permanently_blocked'] = !empty($state['permanently_blocked']) || $probeCount >= ROSSI_MAX_PROBES;
    $state['updated_at'] = $now;

    ftruncate($handle, 0);
    rewind($handle);
    $written = fwrite($handle, json_encode($state, JSON_UNESCAPED_SLASHES));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    @chmod($path, 0600);

    return $written === false ? null : $state;
}

function rossi_auth_gate(): array
{
    $nonce = bin2hex(random_bytes(16));
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'nonce-{$nonce}'; img-src 'self' data: blob:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store, private');

    $securityDir = rossi_security_dir();
    $configPath = $securityDir . '/security.php';
    if (!is_file($configPath)) {
        http_response_code(503);
        return ['status' => 'setup', 'nonce' => $nonce];
    }

    $config = require $configPath;
    $passwordHashes = [];
    if (is_array($config)) {
        $configuredHashes = $config['password_hashes'] ?? [];
        if (is_array($configuredHashes)) {
            foreach ($configuredHashes as $configuredHash) {
                if (is_string($configuredHash) && password_get_info($configuredHash)['algo'] !== 0) {
                    $passwordHashes[] = $configuredHash;
                }
            }
        }

        // v1.6.4 이전에 만든 설정도 그대로 로그인할 수 있도록 유지합니다.
        $legacyHash = (string) ($config['password_hash'] ?? '');
        if ($legacyHash !== '' && password_get_info($legacyHash)['algo'] !== 0) {
            $passwordHashes[] = $legacyHash;
        }
    }
    $passwordHashes = array_values(array_unique($passwordHashes));
    if ($passwordHashes === []) {
        http_response_code(503);
        return ['status' => 'setup', 'nonce' => $nonce];
    }

    $sessionDir = $securityDir . '/sessions';
    if (!is_dir($sessionDir) && !@mkdir($sessionDir, 0700, true) && !is_dir($sessionDir)) {
        http_response_code(503);
        return ['status' => 'storage-error', 'nonce' => $nonce];
    }
    @chmod($sessionDir, 0700);
    if (ini_set('session.save_path', $sessionDir) === false || ini_set('session.gc_maxlifetime', (string) ROSSI_SESSION_SECONDS) === false) {
        http_response_code(503);
        return ['status' => 'storage-error', 'nonce' => $nonce];
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('ROSSITOOLSSESSID');
    session_set_cookie_params([
        'lifetime' => ROSSI_SESSION_SECONDS,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    $now = time();
    $authenticated = !empty($_SESSION['authenticated']);
    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
    if ($authenticated && ($lastActivity === 0 || $now - $lastActivity > ROSSI_SESSION_SECONDS)) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $authenticated = false;
    }

    $attemptDir = $securityDir . '/attempts';
    if (!is_dir($attemptDir) && !@mkdir($attemptDir, 0700, true) && !is_dir($attemptDir)) {
        http_response_code(503);
        return ['status' => 'storage-error', 'nonce' => $nonce];
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $attemptPath = $attemptDir . '/' . hash('sha256', $ip) . '.json';
    $state = rossi_read_attempt($attemptPath);
    if (!empty($state['permanently_blocked'])) {
        http_response_code(403);
        return ['status' => 'permanently-blocked', 'nonce' => $nonce];
    }

    if ($authenticated) {
        $_SESSION['last_activity'] = $now;
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = (string) ($_POST['action'] ?? '');
    $csrf = (string) ($_POST['csrf'] ?? '');

    if ($method === 'POST' && $action === 'logout' && $authenticated) {
        if (hash_equals((string) $_SESSION['csrf'], $csrf)) {
            $_SESSION = [];
            session_destroy();
            header('Location: /', true, 303);
            exit;
        }
    }

    if ($authenticated) {
        return [
            'status' => 'authenticated',
            'nonce' => $nonce,
            'csrf' => (string) $_SESSION['csrf'],
        ];
    }

    $blockedUntil = (int) ($state['blocked_until'] ?? 0);
    $error = '';
    $remaining = max(0, ROSSI_MAX_ATTEMPTS - (int) ($state['count'] ?? 0));

    if ($blockedUntil > $now) {
        $retry = $blockedUntil - $now;
        http_response_code(429);
        header('Retry-After: ' . $retry);
        return [
            'status' => 'blocked',
            'nonce' => $nonce,
            'csrf' => (string) $_SESSION['csrf'],
            'blocked_seconds' => $retry,
        ];
    }

    if ($method === 'POST' && $action === 'login') {
        if (!hash_equals((string) $_SESSION['csrf'], $csrf)) {
            http_response_code(403);
            $error = '요청이 만료되었습니다. 페이지를 새로고침해 주세요.';
        } else {
            $password = (string) ($_POST['password'] ?? '');
            if (strlen($password) > 4096) {
                $password = '';
            }

            $passwordMatches = false;
            foreach ($passwordHashes as $passwordHash) {
                // 모든 해시를 확인해 어느 비밀번호가 사용됐는지 외부에 드러나지 않게 합니다.
                $passwordMatches = password_verify($password, $passwordHash) || $passwordMatches;
            }

            if ($passwordMatches) {
                @unlink($attemptPath);
                session_regenerate_id(true);
                $_SESSION['authenticated'] = true;
                $_SESSION['last_activity'] = $now;
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                header('Location: /', true, 303);
                exit;
            }

            $state = rossi_record_failure($attemptPath, $now);
            if ($state === null) {
                http_response_code(503);
                return ['status' => 'storage-error', 'nonce' => $nonce];
            }

            $remaining = max(0, ROSSI_MAX_ATTEMPTS - (int) $state['count']);
            if ($state['blocked_until'] > $now) {
                http_response_code(429);
                header('Retry-After: ' . ROSSI_BLOCK_SECONDS);
                return [
                    'status' => 'blocked',
                    'nonce' => $nonce,
                    'csrf' => (string) $_SESSION['csrf'],
                    'blocked_seconds' => ROSSI_BLOCK_SECONDS,
                ];
            }

            if (!empty($state['permanently_blocked'])) {
                http_response_code(403);
                return ['status' => 'permanently-blocked', 'nonce' => $nonce];
            }

            http_response_code(401);
            $error = '비밀번호가 올바르지 않습니다.';
        }
    }

    return [
        'status' => 'login',
        'nonce' => $nonce,
        'csrf' => (string) $_SESSION['csrf'],
        'error' => $error,
        'remaining' => $remaining,
    ];
}
