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

$maximumBytes = 1024 * 1024;
$keepBytes = 256 * 1024;
$logs = [
    $home . '/.rossi-tools/crunz-runner.log',
    $home . '/.rossi-tools/crunz-errors.log',
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
        fwrite(STDERR, 'Unable to read scheduler log: ' . $log . PHP_EOL);
        continue;
    }

    $tail = stream_get_contents($handle);
    fclose($handle);
    if ($tail === false || file_put_contents($log, $tail, LOCK_EX) === false) {
        fwrite(STDERR, 'Unable to trim scheduler log: ' . $log . PHP_EOL);
        continue;
    }
    chmod($log, 0600);
}
