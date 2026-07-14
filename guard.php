<?php
declare(strict_types=1);

require __DIR__ . '/app/auth.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
http_response_code(403);

$securityDir = rossi_security_dir();
$attemptDir = $securityDir . '/attempts';
if (!is_dir($attemptDir) && !@mkdir($attemptDir, 0700, true) && !is_dir($attemptDir)) {
    exit("Forbidden\n");
}

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$requestTarget = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$attemptPath = $attemptDir . '/' . hash('sha256', $ip) . '.json';
rossi_record_probe($attemptPath, time(), $requestTarget);

exit("Forbidden\n");
